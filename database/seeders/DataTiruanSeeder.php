<?php

namespace Database\Seeders;

use App\Enums\KelompokKomoditas;
use App\Enums\ModelPrediksi;
use App\Enums\StatusMuat;
use App\Enums\SumberData;
use App\Enums\TingkatHarga;
use App\Models\HargaHarian;
use App\Models\HargaNasional;
use App\Models\IndikatorHarga;
use App\Models\Komoditas;
use App\Models\MetrikModel;
use App\Models\Prediksi;
use App\Models\Provinsi;
use App\Models\RiwayatMuat;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Data tiruan untuk mengembangkan dasbor sebelum ETL selesai.
 *
 * DATA INI BUATAN PROGRAM DAN TIDAK BOLEH DILAPORKAN SEBAGAI HASIL PENELITIAN.
 *
 * Harga dibangkitkan secara deterministik dari harga dasar per komoditas, faktor
 * per provinsi, tren tahunan, kenaikan menjelang Idul Fitri, penurunan harga beras
 * pada musim panen, kenaikan bertahap minyak goreng setelah 1 Juli 2026, dan galat
 * acak yang berkorelasi antarhari.
 *
 * Bentuknya meniru data PIHPS: hanya hari kerja, seluruh harga per kilogram,
 * harga konsumen dari pasar tradisional, dan harga produsen hanya untuk beras.
 *
 * Celah data yang sengaja dibuat:
 * - Kalimantan Utara (kode 65) tanpa data 10 sampai 21 Agustus 2026.
 * - Jawa Timur (kode 35) tanpa data 7 sampai 18 September 2026.
 * - Sekitar satu persen hari pada setiap deret hilang secara acak.
 *
 * Harga nasional tetap tersedia pada hari-hari celah tersebut, sama seperti
 * angka nasional PIHPS yang tidak bergantung pada satu provinsi.
 */
class DataTiruanSeeder extends Seeder
{
    private const int BENIH_ACAK = 2026;

    private const int UKURAN_POTONGAN = 500;

    private const float RASIO_PRODUSEN = 0.86;

    private const float FAKTOR_NASIONAL = 1.01;

    private const string TANGGAL_B50 = '2026-07-01';

    /**
     * Harga dasar per kilogram, setara rata-rata PIHPS pasar tradisional pada
     * Januari 2024 dibagi rata-rata faktor provinsi.
     *
     * @var array<string, float>
     */
    private const array HARGA_DASAR = [
        'beras-medium' => 14800,
        'beras-premium' => 16100,
        'minyak-goreng-kemasan' => 19300,
        'minyak-goreng-curah' => 15000,
    ];

    /**
     * Pengali harga per provinsi menurut kode BPS.
     *
     * @var array<string, float>
     */
    private const array FAKTOR_PROVINSI = [
        '11' => 1.03, '12' => 1.00, '13' => 1.05, '14' => 1.04, '15' => 1.01,
        '16' => 0.97, '17' => 1.02, '18' => 0.99, '19' => 1.06, '21' => 1.08,
        '31' => 1.04, '32' => 0.98, '33' => 0.96, '34' => 0.97, '35' => 0.95,
        '36' => 1.00, '61' => 1.05, '62' => 1.06, '63' => 1.03, '64' => 1.07,
        '65' => 1.10,
    ];

    /**
     * Rentang tanggal tanpa data per kode provinsi.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array CELAH_DATA = [
        '65' => ['2026-08-10', '2026-08-21'],
        '35' => ['2026-09-07', '2026-09-18'],
    ];

    /**
     * MAPE dan R² tiruan per komoditas dan model.
     *
     * @var array<string, array<string, array{0: float, 1: float}>>
     */
    private const array METRIK = [
        'beras-medium' => ['naive' => [0.82, 0.84], 'random_forest' => [0.71, 0.88], 'xgboost' => [0.64, 0.90]],
        'beras-premium' => ['naive' => [0.78, 0.85], 'random_forest' => [0.69, 0.89], 'xgboost' => [0.66, 0.90]],
        'minyak-goreng-kemasan' => ['naive' => [1.12, 0.80], 'random_forest' => [0.94, 0.86], 'xgboost' => [0.98, 0.85]],
        'minyak-goreng-curah' => ['naive' => [1.35, 0.78], 'random_forest' => [1.18, 0.83], 'xgboost' => [1.09, 0.85]],
    ];

    public function __construct(
        public string $tanggalAwal = '2024-01-01',
        public string $tanggalAkhir = '2026-10-02',
        public int $hariPrediksi = 30,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DataTiruanSeeder tidak boleh dijalankan di production.');

            return;
        }

        if ($this->tabelFaktaSudahBerisi()) {
            $this->command?->warn('Tabel fakta sudah berisi data. Jalankan php artisan migrate:fresh --seed lebih dahulu bila ingin memuat ulang data tiruan.');

            return;
        }

        $this->call([ProvinsiSeeder::class, KomoditasSeeder::class, KalenderSeeder::class]);

        $provinsi = Provinsi::orderBy('kode')->get();
        $komoditas = Komoditas::orderBy('kode')->get();
        $tanggal = $this->daftarTanggal();
        $dibuatPada = now();

        $penanda = $this->tandaiDataTiruan($dibuatPada);

        [$deret, $nasional] = $this->bangkitkanDeret($provinsi, $komoditas, $tanggal);

        $this->simpanHargaHarian($deret, $tanggal, $dibuatPada);
        $this->simpanHargaNasional($nasional, $tanggal, $dibuatPada);
        $this->simpanIndikator($deret, $nasional, $tanggal, $dibuatPada);
        $this->simpanPrediksi($deret, $dibuatPada);
        $this->simpanMetrik($komoditas, $dibuatPada);

        $penanda->update([
            'status' => StatusMuat::Berhasil,
            'baris_dimuat' => HargaHarian::count(),
            'selesai_pada' => now(),
        ]);
    }

    /**
     * Penanda ditulis sebelum data apa pun dimuat, sehingga seeder yang gagal di
     * tengah jalan tetap meninggalkan penanda dan ETL tetap menolak memuat data asli.
     */
    private function tandaiDataTiruan(Carbon $dibuatPada): RiwayatMuat
    {
        return RiwayatMuat::create([
            'sumber' => RiwayatMuat::SUMBER_TIRUAN,
            'status' => StatusMuat::Berjalan,
            'tanggal_data_awal' => $this->tanggalAwal,
            'tanggal_data_akhir' => $this->tanggalAkhir,
            'rincian' => ['keterangan' => 'Data buatan DataTiruanSeeder, bukan data harga sebenarnya.'],
            'mulai_pada' => $dibuatPada,
        ]);
    }

    private function tabelFaktaSudahBerisi(): bool
    {
        return RiwayatMuat::query()->exists()
            || HargaHarian::query()->exists()
            || HargaNasional::query()->exists()
            || IndikatorHarga::query()->exists()
            || Prediksi::query()->exists()
            || MetrikModel::query()->exists();
    }

    /**
     * Hari kerja dalam rentang data, karena PIHPS tidak mencatat harga pada
     * Sabtu dan Minggu.
     *
     * @return list<string>
     */
    private function daftarTanggal(): array
    {
        $tanggal = [];

        foreach (CarbonPeriod::create($this->tanggalAwal, $this->tanggalAkhir) as $hari) {
            if (! $hari->isWeekend()) {
                $tanggal[] = $hari->toDateString();
            }
        }

        return $tanggal;
    }

    /**
     * Membangkitkan seluruh deret harga provinsi dan menghitung harga nasional
     * dari deret lengkap sebelum celah diterapkan.
     *
     * @param  Collection<int, Provinsi>  $provinsi
     * @param  Collection<int, Komoditas>  $komoditas
     * @param  list<string>  $tanggal
     * @return array{0: list<array{provinsi: Provinsi, komoditas: Komoditas, tingkat: TingkatHarga, harga: array<int, ?float>}>, 1: array<int, array<string, array<int, float>>>}
     */
    private function bangkitkanDeret(Collection $provinsi, Collection $komoditas, array $tanggal): array
    {
        $deret = [];
        $jumlah = [];

        foreach ($komoditas as $satuKomoditas) {
            $faktorHarian = $this->faktorHarian($satuKomoditas, $tanggal);

            foreach ($this->tingkatUntuk($satuKomoditas) as $tingkat) {
                foreach ($provinsi as $satuProvinsi) {
                    $harga = $this->deretLengkap($satuProvinsi->kode, $satuKomoditas, $tingkat, $faktorHarian);

                    foreach ($harga as $i => $nilai) {
                        $jumlah[$satuKomoditas->id][$tingkat->value][$i] = ($jumlah[$satuKomoditas->id][$tingkat->value][$i] ?? 0) + $nilai;
                    }

                    $deret[] = [
                        'provinsi' => $satuProvinsi,
                        'komoditas' => $satuKomoditas,
                        'tingkat' => $tingkat,
                        'harga' => $this->terapkanCelah($satuProvinsi->kode, $satuKomoditas, $tingkat, $harga, $tanggal),
                    ];
                }
            }
        }

        $jumlahProvinsi = $provinsi->count();
        $nasional = [];

        foreach ($jumlah as $komoditasId => $perTingkat) {
            foreach ($perTingkat as $tingkat => $perHari) {
                foreach ($perHari as $i => $total) {
                    $nasional[$komoditasId][$tingkat][$i] = round($total / $jumlahProvinsi * self::FAKTOR_NASIONAL);
                }
            }
        }

        return [$deret, $nasional];
    }

    /**
     * @return list<TingkatHarga>
     */
    private function tingkatUntuk(Komoditas $komoditas): array
    {
        return $komoditas->kelompok === KelompokKomoditas::Beras
            ? [TingkatHarga::Konsumen, TingkatHarga::Produsen]
            : [TingkatHarga::Konsumen];
    }

    /**
     * Pengali harian gabungan dari tren, Idul Fitri, musim panen, dan B50.
     *
     * @param  list<string>  $tanggal
     * @return array<int, float>
     */
    private function faktorHarian(Komoditas $komoditas, array $tanggal): array
    {
        $awal = Carbon::parse('2024-01-01');
        $b50 = Carbon::parse(self::TANGGAL_B50);
        $idulFitri = array_map(
            fn (string $hari): Carbon => Carbon::parse($hari),
            array_keys(array_filter(KalenderSeeder::HARI_BESAR, fn (string $nama): bool => $nama === 'Idul Fitri')),
        );
        $adalahBeras = $komoditas->kelompok === KelompokKomoditas::Beras;
        $lajuTahunan = $adalahBeras ? 0.03 : 0.02;
        $puncakLebaran = $adalahBeras ? 0.035 : 0.03;

        $faktor = [];

        foreach ($tanggal as $i => $hari) {
            $tanggalIni = Carbon::parse($hari);
            $nilai = 1 + $awal->diffInDays($tanggalIni) / 365 * $lajuTahunan;

            foreach ($idulFitri as $lebaran) {
                $selisihHari = (int) $lebaran->diffInDays($tanggalIni, false);

                if ($selisihHari >= -21 && $selisihHari <= 0) {
                    $nilai += $puncakLebaran * (1 + $selisihHari / 21);
                } elseif ($selisihHari > 0 && $selisihHari <= 10) {
                    $nilai += $puncakLebaran * (1 - $selisihHari / 10);
                }
            }

            if ($adalahBeras && in_array($tanggalIni->month, KalenderSeeder::BULAN_MUSIM_PANEN, true)) {
                $nilai -= 0.025;
            }

            if (! $adalahBeras && $tanggalIni->greaterThanOrEqualTo($b50)) {
                $nilai += 0.04 * min(1, $b50->diffInDays($tanggalIni) / 60);
            }

            $faktor[$i] = $nilai;
        }

        return $faktor;
    }

    /**
     * Deret harga tanpa celah, dengan galat acak yang berkorelasi antarhari.
     *
     * @param  array<int, float>  $faktorHarian
     * @return array<int, float>
     */
    private function deretLengkap(string $kodeProvinsi, Komoditas $komoditas, TingkatHarga $tingkat, array $faktorHarian): array
    {
        $acak = $this->pengacak("harga|{$kodeProvinsi}|{$komoditas->kode}|{$tingkat->value}");
        $dasar = self::HARGA_DASAR[$komoditas->kode]
            * self::FAKTOR_PROVINSI[$kodeProvinsi]
            * ($tingkat === TingkatHarga::Produsen ? self::RASIO_PRODUSEN : 1.0);

        $galat = 0.0;
        $harga = [];

        foreach ($faktorHarian as $i => $faktor) {
            $galat = 0.85 * $galat + $this->acakNormal($acak) * 0.0025;
            $harga[$i] = round($dasar * $faktor * (1 + $galat));
        }

        return $harga;
    }

    /**
     * @param  array<int, float>  $harga
     * @param  list<string>  $tanggal
     * @return array<int, ?float>
     */
    private function terapkanCelah(string $kodeProvinsi, Komoditas $komoditas, TingkatHarga $tingkat, array $harga, array $tanggal): array
    {
        $acak = $this->pengacak("celah|{$kodeProvinsi}|{$komoditas->kode}|{$tingkat->value}");
        $celah = self::CELAH_DATA[$kodeProvinsi] ?? null;

        foreach ($harga as $i => $nilai) {
            $dalamCelah = $celah !== null && $tanggal[$i] >= $celah[0] && $tanggal[$i] <= $celah[1];
            $hilangAcak = $acak->getInt(1, 100) === 1;

            if ($dalamCelah || $hilangAcak) {
                $harga[$i] = null;
            }
        }

        return $harga;
    }

    /**
     * @param  list<array{provinsi: Provinsi, komoditas: Komoditas, tingkat: TingkatHarga, harga: array<int, ?float>}>  $deret
     * @param  list<string>  $tanggal
     */
    private function simpanHargaHarian(array $deret, array $tanggal, Carbon $dibuatPada): void
    {
        $baris = [];

        foreach ($deret as $satuDeret) {
            foreach ($satuDeret['harga'] as $i => $harga) {
                if ($harga === null) {
                    continue;
                }

                $baris[] = [
                    'tanggal' => $tanggal[$i],
                    'provinsi_id' => $satuDeret['provinsi']->id,
                    'komoditas_id' => $satuDeret['komoditas']->id,
                    'tingkat_harga' => $satuDeret['tingkat']->value,
                    'jenis_pasar' => $satuDeret['tingkat']->jenisPasarUtama()->value,
                    'sumber' => SumberData::Pihps->value,
                    'harga' => $harga,
                    'created_at' => $dibuatPada,
                    'updated_at' => $dibuatPada,
                ];

                $baris = $this->kirimBilaPenuh(HargaHarian::class, $baris);
            }
        }

        $this->kirim(HargaHarian::class, $baris);
    }

    /**
     * @param  array<int, array<string, array<int, float>>>  $nasional
     * @param  list<string>  $tanggal
     */
    private function simpanHargaNasional(array $nasional, array $tanggal, Carbon $dibuatPada): void
    {
        $baris = [];

        foreach ($nasional as $komoditasId => $perTingkat) {
            foreach ($perTingkat as $tingkat => $perHari) {
                foreach ($perHari as $i => $harga) {
                    $baris[] = [
                        'tanggal' => $tanggal[$i],
                        'komoditas_id' => $komoditasId,
                        'tingkat_harga' => $tingkat,
                        'harga' => $harga,
                        'created_at' => $dibuatPada,
                        'updated_at' => $dibuatPada,
                    ];

                    $baris = $this->kirimBilaPenuh(HargaNasional::class, $baris);
                }
            }
        }

        $this->kirim(HargaNasional::class, $baris);
    }

    /**
     * @param  list<array{provinsi: Provinsi, komoditas: Komoditas, tingkat: TingkatHarga, harga: array<int, ?float>}>  $deret
     * @param  array<int, array<string, array<int, float>>>  $nasional
     * @param  list<string>  $tanggal
     */
    private function simpanIndikator(array $deret, array $nasional, array $tanggal, Carbon $dibuatPada): void
    {
        $baris = [];

        foreach ($deret as $satuDeret) {
            $acuan = $nasional[$satuDeret['komoditas']->id][$satuDeret['tingkat']->value];

            foreach ($satuDeret['harga'] as $i => $harga) {
                if ($harga === null) {
                    continue;
                }

                $selisih = $harga - $acuan[$i];

                $baris[] = [
                    'tanggal' => $tanggal[$i],
                    'provinsi_id' => $satuDeret['provinsi']->id,
                    'komoditas_id' => $satuDeret['komoditas']->id,
                    'tingkat_harga' => $satuDeret['tingkat']->value,
                    'harga' => $harga,
                    'rata_rata_nasional' => $acuan[$i],
                    'selisih' => $selisih,
                    'selisih_persen' => round($selisih / $acuan[$i] * 100, 2),
                    'created_at' => $dibuatPada,
                    'updated_at' => $dibuatPada,
                ];

                $baris = $this->kirimBilaPenuh(IndikatorHarga::class, $baris);
            }
        }

        $this->kirim(IndikatorHarga::class, $baris);
    }

    /**
     * Prediksi harga konsumen untuk setiap hari kerja dalam rentang hariPrediksi
     * hari setelah data terakhir. Garis dasar Naive mengulang harga terakhir
     * yang teramati.
     *
     * @param  list<array{provinsi: Provinsi, komoditas: Komoditas, tingkat: TingkatHarga, harga: array<int, ?float>}>  $deret
     */
    private function simpanPrediksi(array $deret, Carbon $dibuatPada): void
    {
        $hariTerakhir = Carbon::parse($this->tanggalAkhir);
        $baris = [];

        foreach ($deret as $satuDeret) {
            if ($satuDeret['tingkat'] !== TingkatHarga::Konsumen) {
                continue;
            }

            $hargaTerakhir = $this->hargaTerakhir($satuDeret['harga']);
            $naik = $satuDeret['komoditas']->kelompok === KelompokKomoditas::MinyakGoreng ? 0.0006 : 0.0003;

            for ($h = 1; $h <= $this->hariPrediksi; $h++) {
                $tanggalSasaran = $hariTerakhir->copy()->addDays($h);

                if ($tanggalSasaran->isWeekend()) {
                    continue;
                }

                $perkiraan = [
                    ModelPrediksi::Naive->value => $hargaTerakhir,
                    ModelPrediksi::RandomForest->value => round($hargaTerakhir * (1 + $naik * $h)),
                    ModelPrediksi::Xgboost->value => round($hargaTerakhir * (1 + $naik * 0.8 * $h + 0.002 * sin($h / 3))),
                ];

                foreach ($perkiraan as $model => $harga) {
                    $baris[] = [
                        'provinsi_id' => $satuDeret['provinsi']->id,
                        'komoditas_id' => $satuDeret['komoditas']->id,
                        'model' => $model,
                        'tanggal_sasaran' => $tanggalSasaran->toDateString(),
                        'harga_prediksi' => $harga,
                        'dihasilkan_pada' => $dibuatPada,
                        'created_at' => $dibuatPada,
                        'updated_at' => $dibuatPada,
                    ];

                    $baris = $this->kirimBilaPenuh(Prediksi::class, $baris);
                }
            }
        }

        $this->kirim(Prediksi::class, $baris);
    }

    /**
     * Metrik tiruan yang memenuhi kriteria Tabel 3.6: MAPE di bawah 3 persen,
     * R² minimal 0,70, dan model terpilih mengalahkan garis dasar Naive.
     *
     * @param  Collection<int, Komoditas>  $komoditas
     */
    private function simpanMetrik(Collection $komoditas, Carbon $dibuatPada): void
    {
        $akhir = Carbon::parse($this->tanggalAkhir);
        $mulai = $akhir->copy()->subDays(182)->max(Carbon::parse($this->tanggalAwal));
        $baris = [];

        foreach ($komoditas as $satuKomoditas) {
            $metrik = self::METRIK[$satuKomoditas->kode];
            $modelTerpilih = $metrik[ModelPrediksi::RandomForest->value][0] <= $metrik[ModelPrediksi::Xgboost->value][0]
                ? ModelPrediksi::RandomForest->value
                : ModelPrediksi::Xgboost->value;

            foreach ($metrik as $model => [$mape, $r2]) {
                $mae = round(self::HARGA_DASAR[$satuKomoditas->kode] * $mape / 100, 4);

                $baris[] = [
                    'komoditas_id' => $satuKomoditas->id,
                    'model' => $model,
                    'mape' => $mape,
                    'rmse' => round($mae * 1.25, 4),
                    'mae' => $mae,
                    'r2' => $r2,
                    'periode_uji_mulai' => $mulai->toDateString(),
                    'periode_uji_selesai' => $akhir->toDateString(),
                    'terpilih' => $model === $modelTerpilih,
                    'created_at' => $dibuatPada,
                    'updated_at' => $dibuatPada,
                ];
            }
        }

        MetrikModel::query()->insert($baris);
    }

    /**
     * @param  array<int, ?float>  $harga
     */
    private function hargaTerakhir(array $harga): float
    {
        $teramati = array_filter($harga, fn (?float $nilai): bool => $nilai !== null);

        return end($teramati);
    }

    private function pengacak(string $label): Randomizer
    {
        return new Randomizer(new Mt19937(self::BENIH_ACAK + crc32($label)));
    }

    /**
     * Bilangan acak berdistribusi normal baku dengan metode Box-Muller.
     */
    private function acakNormal(Randomizer $acak): float
    {
        $u1 = max($acak->nextFloat(), PHP_FLOAT_EPSILON);
        $u2 = $acak->nextFloat();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function kirimBilaPenuh(string $model, array $baris): array
    {
        if (count($baris) < self::UKURAN_POTONGAN) {
            return $baris;
        }

        $this->kirim($model, $baris);

        return [];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $baris
     */
    private function kirim(string $model, array $baris): void
    {
        if ($baris !== []) {
            $model::query()->insert($baris);
        }
    }
}
