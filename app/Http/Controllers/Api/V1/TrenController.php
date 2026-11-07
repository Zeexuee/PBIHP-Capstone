<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KelompokKomoditas;
use App\Enums\SumberData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrenRequest;
use App\Http\Resources\KomoditasResource;
use App\Models\HargaHarian;
use App\Models\HargaNasional;
use App\Models\Kalender;
use App\Models\Provinsi;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deret waktu harga nasional dan provinsi terpilih.
 *
 * Periode tanpa data dikirim sebagai null, bukan diisi atau disambung, agar
 * celah data terlihat apa adanya di grafik. Pada agregasi mingguan dan bulanan,
 * nilai adalah rata-rata hari yang memiliki data dalam periode tersebut.
 */
class TrenController extends Controller
{
    public function __invoke(TrenRequest $request): JsonResponse
    {
        $komoditas = $request->komoditas();
        $tingkat = $request->tingkat();
        $agregasi = $request->agregasi();
        [$mulai, $selesai] = $this->rentang($request);

        $kodeProvinsi = $request->kodeProvinsi();
        $provinsi = Provinsi::whereIn('kode', $kodeProvinsi)->get()->keyBy('kode');

        $hargaNasional = HargaNasional::whereBelongsTo($komoditas)
            ->where('tingkat_harga', $tingkat)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get(['tanggal', 'harga']);

        $hargaProvinsi = HargaHarian::whereBelongsTo($komoditas)
            ->where('tingkat_harga', $tingkat)
            ->where('jenis_pasar', $tingkat->jenisPasarUtama())
            ->where('sumber', SumberData::Pihps)
            ->whereIn('provinsi_id', $provinsi->pluck('id'))
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get(['tanggal', 'provinsi_id', 'harga'])
            ->groupBy('provinsi_id');

        $ember = $this->daftarEmber($mulai, $selesai, $agregasi);

        $seri = [[
            'kode' => null,
            'nama' => 'Rata-rata nasional',
            'harga' => $this->ratakan($hargaNasional, $ember, $agregasi),
        ]];

        foreach ($kodeProvinsi as $kode) {
            $satuProvinsi = $provinsi[$kode];

            $seri[] = [
                'kode' => $satuProvinsi->kode,
                'nama' => $satuProvinsi->nama,
                'harga' => $this->ratakan($hargaProvinsi->get($satuProvinsi->id, collect()), $ember, $agregasi),
            ];
        }

        $kalender = Kalender::whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->orderBy('tanggal')
            ->get();

        return response()->json(['data' => [
            'komoditas' => KomoditasResource::make($komoditas),
            'tingkat_harga' => $tingkat->value,
            'agregasi' => $agregasi,
            'mulai' => $mulai->toDateString(),
            'selesai' => $selesai->toDateString(),
            'tanggal' => array_keys($ember),
            'seri' => $seri,
            'penanda' => $kalender->whereNotNull('hari_besar')->values()->map(fn (Kalender $hari): array => [
                'tanggal' => $hari->tanggal->toDateString(),
                'nama' => $hari->hari_besar,
            ]),
            'musim_panen' => $komoditas->kelompok === KelompokKomoditas::Beras
                ? $this->rentangBerurutan($kalender->where('musim_panen', true))
                : [],
        ]]);
    }

    /**
     * Rentang bawaan berakhir pada tanggal data nasional terakhir.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rentang(TrenRequest $request): array
    {
        $panjang = config('dasbor.tren.rentang_bawaan_hari') - 1;

        $tanggalTerakhir = HargaNasional::whereBelongsTo($request->komoditas())
            ->where('tingkat_harga', $request->tingkat())
            ->max('tanggal');

        $selesai = $request->selesai()
            ?? ($request->mulai()?->copy()->addDays($panjang))
            ?? ($tanggalTerakhir === null ? today() : Carbon::parse($tanggalTerakhir));

        $mulai = $request->mulai() ?? $selesai->copy()->subDays($panjang);

        return [$mulai->startOfDay(), $selesai->startOfDay()];
    }

    /**
     * Kunci setiap periode dalam rentang, berurutan dari yang paling awal.
     * Kunci harian adalah tanggal itu sendiri, kunci mingguan adalah hari Senin,
     * dan kunci bulanan adalah tanggal 1.
     *
     * Deret harian hanya memuat hari kerja. PIHPS tidak mencatat harga pada
     * Sabtu dan Minggu, sehingga akhir pekan bukan data yang hilang dan tidak
     * boleh tampil sebagai celah di grafik.
     *
     * @return array<string, true>
     */
    private function daftarEmber(Carbon $mulai, Carbon $selesai, string $agregasi): array
    {
        $ember = [];

        foreach (CarbonPeriod::create($mulai, $selesai) as $hari) {
            if ($agregasi === 'harian' && $hari->isWeekend()) {
                continue;
            }

            $ember[$this->kunciEmber(Carbon::instance($hari), $agregasi)] = true;
        }

        return $ember;
    }

    private function kunciEmber(Carbon $hari, string $agregasi): string
    {
        return match ($agregasi) {
            'mingguan' => $hari->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'bulanan' => $hari->copy()->startOfMonth()->toDateString(),
            default => $hari->toDateString(),
        };
    }

    /**
     * @param  Collection<int, HargaHarian|HargaNasional>  $baris
     * @param  array<string, true>  $ember
     * @return list<?float>
     */
    private function ratakan(Collection $baris, array $ember, string $agregasi): array
    {
        $perEmber = $baris->groupBy(fn (HargaHarian|HargaNasional $satu): string => $this->kunciEmber($satu->tanggal, $agregasi));

        $hasil = [];

        foreach (array_keys($ember) as $kunci) {
            $anggota = $perEmber->get($kunci);
            $hasil[] = $anggota === null ? null : round($anggota->avg(fn (HargaHarian|HargaNasional $satu): float => (float) $satu->harga));
        }

        return $hasil;
    }

    /**
     * Menggabungkan tanggal yang berurutan menjadi rentang mulai dan selesai.
     *
     * @param  Collection<int, Kalender>  $hari
     * @return list<array{mulai: string, selesai: string}>
     */
    private function rentangBerurutan(Collection $hari): array
    {
        $rentang = [];

        foreach ($hari as $satuHari) {
            $tanggal = $satuHari->tanggal;
            $terakhir = array_key_last($rentang);

            if ($terakhir !== null && Carbon::parse($rentang[$terakhir]['selesai'])->addDay()->isSameDay($tanggal)) {
                $rentang[$terakhir]['selesai'] = $tanggal->toDateString();

                continue;
            }

            $rentang[] = ['mulai' => $tanggal->toDateString(), 'selesai' => $tanggal->toDateString()];
        }

        return $rentang;
    }
}
