<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PerbandinganRequest;
use App\Http\Resources\KomoditasResource;
use App\Http\Resources\ProvinsiResource;
use App\Models\HargaNasional;
use App\Models\IndikatorHarga;
use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Perbandingan harga seluruh provinsi pada satu tanggal, diurutkan dari yang
 * termurah, beserta selisihnya terhadap rata-rata nasional PIHPS.
 */
class PerbandinganController extends Controller
{
    public function __invoke(PerbandinganRequest $request): JsonResponse
    {
        $komoditas = $request->komoditas();
        $tingkat = $request->tingkat();
        $pulau = $request->pulau();

        $nasional = HargaNasional::whereBelongsTo($komoditas)->where('tingkat_harga', $tingkat);
        $tanggal = $request->tanggal()?->toDateString() ?? $nasional->clone()->max('tanggal');

        $indikator = IndikatorHarga::with('provinsi')
            ->whereBelongsTo($komoditas)
            ->where('tingkat_harga', $tingkat)
            ->where('tanggal', $tanggal)
            ->when($pulau, fn (Builder $kueri): Builder => $kueri->whereRelation('provinsi', 'pulau', $pulau))
            ->orderBy('harga')
            ->orderBy('provinsi_id')
            ->get();

        $tanpaData = Provinsi::when($pulau, fn (Builder $kueri): Builder => $kueri->where('pulau', $pulau))
            ->whereNotIn('id', $indikator->pluck('provinsi_id'))
            ->orderBy('kode')
            ->get();

        $rataRata = $tanggal === null ? null : $nasional->clone()->where('tanggal', $tanggal)->value('harga');

        return response()->json(['data' => [
            'komoditas' => KomoditasResource::make($komoditas),
            'tingkat_harga' => $tingkat->value,
            'tanggal' => $tanggal,
            'rata_rata_nasional' => $rataRata === null ? null : (float) $rataRata,
            'provinsi' => $indikator->values()->map(fn (IndikatorHarga $baris, int $urutan): array => [
                'peringkat' => $urutan + 1,
                ...ProvinsiResource::make($baris->provinsi)->resolve(),
                'harga' => (float) $baris->harga,
                'selisih' => (float) $baris->selisih,
                'selisih_persen' => (float) $baris->selisih_persen,
            ]),
            'provinsi_tanpa_data' => ProvinsiResource::collection($tanpaData),
        ]]);
    }
}
