<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModelPrediksi;
use App\Enums\SumberData;
use App\Enums\TingkatHarga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PrediksiRequest;
use App\Http\Resources\KomoditasResource;
use App\Http\Resources\ProvinsiResource;
use App\Models\HargaHarian;
use App\Models\Komoditas;
use App\Models\MetrikModel;
use App\Models\Prediksi;
use App\Models\Provinsi;
use Carbon\CarbonPeriod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Prediksi harga konsumen satu provinsi beserta metrik evaluasi model.
 *
 * Sesuai kriteria penerimaan Tabel 3.6, model terpilih hanya ditampilkan bila
 * MAPE di bawah ambang, R² tidak kurang dari ambang, dan galatnya lebih kecil
 * daripada garis dasar Naive. Bila tidak lolos, dasbor menampilkan garis dasar
 * Naive beserta keterangannya.
 *
 * Prakiraan diminta lebih dahulu dari layanan API model Python. Bila layanan
 * tidak diatur, tidak dapat dihubungi, atau jawabannya tidak dapat dipakai,
 * prakiraan dibaca dari tabel prediksi yang ditulis saat pelatihan, sehingga
 * dasbor tetap tampil walaupun layanan mati.
 */
class PrediksiController extends Controller
{
    public const SUMBER_LAYANAN = 'layanan_model';

    public const SUMBER_BASIS_DATA = 'basis_data';

    public const KUNCI_LAYANAN_TIDAK_TERSAMBUNG = 'layanan_model.tidak_tersambung';

    public function __invoke(PrediksiRequest $request): JsonResponse
    {
        $komoditas = $request->komoditas();
        $provinsi = $request->provinsi();

        $metrik = MetrikModel::whereBelongsTo($komoditas)->get()->keyBy(fn (MetrikModel $baris): string => $baris->model->value);
        $evaluasi = $this->evaluasi($metrik);

        $terpilih = $metrik->firstWhere('terpilih', true);
        $modelTampil = $terpilih !== null && $evaluasi[$terpilih->model->value]['lolos']
            ? $terpilih->model
            : ModelPrediksi::Naive;

        $historis = $this->historis($komoditas, $provinsi);
        $layanan = $this->prakiraanLayanan($komoditas, $provinsi, $historis);

        if ($layanan !== null) {
            $sumber = self::SUMBER_LAYANAN;
            $dihasilkanPada = $layanan['dihasilkan_pada'];
            $titikTampil = $layanan['prakiraan'][$modelTampil->value];
            $titikGarisDasar = $layanan['prakiraan'][ModelPrediksi::Naive->value];
        } else {
            $prediksi = Prediksi::whereBelongsTo($komoditas)
                ->whereBelongsTo($provinsi)
                ->whereIn('model', [$modelTampil, ModelPrediksi::Naive])
                ->orderBy('tanggal_sasaran')
                ->get()
                ->groupBy(fn (Prediksi $baris): string => $baris->model->value);

            $sumber = self::SUMBER_BASIS_DATA;
            $dihasilkanPada = $prediksi->flatten()->max('dihasilkan_pada')?->toIso8601String();
            $titikTampil = $this->titik($prediksi->get($modelTampil->value, collect()));
            $titikGarisDasar = $this->titik($prediksi->get(ModelPrediksi::Naive->value, collect()));
        }

        return response()->json(['data' => [
            'komoditas' => KomoditasResource::make($komoditas),
            'provinsi' => ProvinsiResource::make($provinsi),
            'model' => ['kode' => $modelTampil->value, 'label' => $modelTampil->label()],
            'memakai_garis_dasar' => $modelTampil === ModelPrediksi::Naive,
            'sumber_prakiraan' => $sumber,
            'dihasilkan_pada' => $dihasilkanPada,
            'historis' => $historis,
            'prediksi' => $titikTampil,
            'garis_dasar' => $modelTampil === ModelPrediksi::Naive ? [] : $titikGarisDasar,
            'kriteria' => config('dasbor.kriteria'),
            'metrik' => $metrik->values()->map(fn (MetrikModel $baris): array => [
                'model' => $baris->model->value,
                'label' => $baris->model->label(),
                'mape' => (float) $baris->mape,
                'rmse' => (float) $baris->rmse,
                'mae' => (float) $baris->mae,
                'r2' => $baris->r2 === null ? null : (float) $baris->r2,
                'terpilih' => $baris->terpilih,
                'periode_uji_mulai' => $baris->periode_uji_mulai->toDateString(),
                'periode_uji_selesai' => $baris->periode_uji_selesai->toDateString(),
                ...$evaluasi[$baris->model->value],
            ]),
        ]]);
    }

    /**
     * Menilai setiap model terhadap kriteria penerimaan. Garis dasar Naive tidak
     * dibandingkan dengan dirinya sendiri, sehingga nilainya null.
     *
     * @param  Collection<string, MetrikModel>  $metrik
     * @return array<string, array{lolos_mape: bool, lolos_r2: bool, mengalahkan_naive: ?bool, lolos: bool}>
     */
    private function evaluasi(Collection $metrik): array
    {
        $kriteria = config('dasbor.kriteria');
        $naive = $metrik->get(ModelPrediksi::Naive->value);
        $hasil = [];

        foreach ($metrik as $kode => $baris) {
            $lolosMape = (float) $baris->mape < $kriteria['mape_maks'];
            $lolosR2 = $baris->r2 !== null && (float) $baris->r2 >= $kriteria['r2_min'];
            $mengalahkanNaive = $baris->model === ModelPrediksi::Naive
                ? null
                : $naive !== null
                    && (float) $baris->mape < (float) $naive->mape
                    && (float) $baris->rmse < (float) $naive->rmse
                    && (float) $baris->mae < (float) $naive->mae;

            $hasil[$kode] = [
                'lolos_mape' => $lolosMape,
                'lolos_r2' => $lolosR2,
                'mengalahkan_naive' => $mengalahkanNaive,
                'lolos' => $lolosMape && $lolosR2 && $mengalahkanNaive === true,
            ];
        }

        return $hasil;
    }

    /**
     * Harga konsumen di pasar tradisional pada hari kerja dalam beberapa hari
     * terakhir, dengan null pada hari kerja tanpa data. Akhir pekan tidak
     * dimuat karena PIHPS tidak mencatat harga pada hari itu.
     *
     * @return array{tanggal: list<string>, harga: list<?float>}
     */
    private function historis(Komoditas $komoditas, Provinsi $provinsi): array
    {
        $kueri = HargaHarian::whereBelongsTo($komoditas)
            ->whereBelongsTo($provinsi)
            ->where('tingkat_harga', TingkatHarga::Konsumen)
            ->where('jenis_pasar', TingkatHarga::Konsumen->jenisPasarUtama())
            ->where('sumber', SumberData::Pihps);

        $terakhir = $kueri->clone()->max('tanggal');

        if ($terakhir === null) {
            return ['tanggal' => [], 'harga' => []];
        }

        $selesai = Carbon::parse($terakhir);
        $mulai = $selesai->copy()->subDays(config('dasbor.prediksi.hari_historis') - 1);

        $harga = $kueri->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get(['tanggal', 'harga'])
            ->mapWithKeys(fn (HargaHarian $baris): array => [$baris->tanggal->toDateString() => (float) $baris->harga]);

        $tanggal = [];
        $nilai = [];

        foreach (CarbonPeriod::create($mulai, $selesai) as $hari) {
            if ($hari->isWeekend()) {
                continue;
            }

            $kunci = $hari->toDateString();
            $tanggal[] = $kunci;
            $nilai[] = $harga->get($kunci);
        }

        return ['tanggal' => $tanggal, 'harga' => $nilai];
    }

    /**
     * Prakiraan dari layanan API model, atau null bila tidak dapat dipakai.
     *
     * Jawaban layanan hanya dipakai bila bentuknya sesuai kontrak dan
     * prakiraannya berangkat dari tanggal dan harga terakhir yang sama dengan
     * basis data aplikasi ini. Pemeriksaan kedua mencegah, misalnya, dasbor
     * berdata tiruan menampilkan prakiraan dari layanan yang membaca data asli.
     *
     * @param  array{tanggal: list<string>, harga: list<?float>}  $historis
     * @return array{dihasilkan_pada: string, prakiraan: array<string, list<array{tanggal: string, harga: float}>>}|null
     */
    private function prakiraanLayanan(Komoditas $komoditas, Provinsi $provinsi, array $historis): ?array
    {
        $url = config('services.layanan_model.url');

        if (blank($url) || $historis['tanggal'] === [] || Cache::has(self::KUNCI_LAYANAN_TIDAK_TERSAMBUNG)) {
            return null;
        }

        try {
            $respons = Http::baseUrl($url)
                ->acceptJson()
                ->connectTimeout(config('services.layanan_model.batas_waktu_sambung'))
                ->timeout(config('services.layanan_model.batas_waktu'))
                ->get('v1/prakiraan', ['komoditas' => $komoditas->kode, 'provinsi' => $provinsi->kode]);
        } catch (ConnectionException $galat) {
            $jeda = config('services.layanan_model.jeda_setelah_gagal');
            Cache::put(self::KUNCI_LAYANAN_TIDAK_TERSAMBUNG, true, now()->addSeconds($jeda));
            Log::warning("Layanan model tidak dapat dihubungi, prakiraan dibaca dari tabel prediksi. Layanan dicoba lagi setelah {$jeda} detik.", [
                'galat' => $galat->getMessage(),
            ]);

            return null;
        }

        if ($respons->failed()) {
            Log::warning('Layanan model membalas galat, prakiraan dibaca dari tabel prediksi.', [
                'status' => $respons->status(),
                'pesan' => $respons->json('message'),
            ]);

            return null;
        }

        $aturan = [
            'data.tanggal_asal' => ['required', 'date_format:Y-m-d'],
            'data.harga_asal' => ['required', 'numeric'],
            'data.dihasilkan_pada' => ['required', 'date'],
        ];

        foreach (ModelPrediksi::cases() as $model) {
            $aturan["data.prakiraan.{$model->value}"] = ['present', 'array'];
            $aturan["data.prakiraan.{$model->value}.*.tanggal"] = ['required', 'date_format:Y-m-d'];
            $aturan["data.prakiraan.{$model->value}.*.harga"] = ['required', 'numeric'];
        }

        $validator = Validator::make(is_array($respons->json()) ? $respons->json() : [], $aturan);

        if ($validator->fails()) {
            Log::warning('Jawaban layanan model tidak sesuai kontrak, prakiraan dibaca dari tabel prediksi.', [
                'galat' => $validator->errors()->all(),
            ]);

            return null;
        }

        $data = $validator->validated()['data'];
        $tanggalTerakhir = last($historis['tanggal']);
        $hargaTerakhir = last($historis['harga']);

        if ($data['tanggal_asal'] !== $tanggalTerakhir || $hargaTerakhir === null || abs((float) $data['harga_asal'] - $hargaTerakhir) > 0.005) {
            Log::warning('Prakiraan layanan model berangkat dari data yang berbeda dengan basis data aplikasi, prakiraan dibaca dari tabel prediksi.', [
                'layanan' => [$data['tanggal_asal'], $data['harga_asal']],
                'basis_data' => [$tanggalTerakhir, $hargaTerakhir],
            ]);

            return null;
        }

        return [
            'dihasilkan_pada' => Carbon::parse($data['dihasilkan_pada'])->toIso8601String(),
            'prakiraan' => collect($data['prakiraan'])
                ->map(fn (array $titik): array => array_map(
                    fn (array $satu): array => ['tanggal' => $satu['tanggal'], 'harga' => (float) $satu['harga']],
                    array_values($titik),
                ))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Prediksi>  $prediksi
     * @return list<array{tanggal: string, harga: float}>
     */
    private function titik(Collection $prediksi): array
    {
        return $prediksi->map(fn (Prediksi $baris): array => [
            'tanggal' => $baris->tanggal_sasaran->toDateString(),
            'harga' => (float) $baris->harga_prediksi,
        ])->values()->all();
    }
}
