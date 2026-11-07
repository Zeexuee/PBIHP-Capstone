<?php

namespace Database\Seeders;

use App\Enums\KelompokKomoditas;
use App\Enums\SatuanHarga;
use App\Models\Komoditas;
use Illuminate\Database\Seeder;

/**
 * Varian komoditas yang diteliti beserta satuan bakunya.
 *
 * Seluruh varian memakai rupiah per kilogram, satuan yang diterbitkan PIHPS
 * untuk beras maupun minyak goreng, sehingga tidak ada konversi satuan.
 * Padanan varian PIHPS dan rincian keputusannya ada di CATATAN_DATA.md.
 */
class KomoditasSeeder extends Seeder
{
    /**
     * @var list<array{kode: string, nama: string, kelompok: KelompokKomoditas, satuan: SatuanHarga}>
     */
    private const array KOMODITAS = [
        ['kode' => 'beras-medium', 'nama' => 'Beras Medium', 'kelompok' => KelompokKomoditas::Beras, 'satuan' => SatuanHarga::Kilogram],
        ['kode' => 'beras-premium', 'nama' => 'Beras Premium', 'kelompok' => KelompokKomoditas::Beras, 'satuan' => SatuanHarga::Kilogram],
        ['kode' => 'minyak-goreng-kemasan', 'nama' => 'Minyak Goreng Kemasan', 'kelompok' => KelompokKomoditas::MinyakGoreng, 'satuan' => SatuanHarga::Kilogram],
        ['kode' => 'minyak-goreng-curah', 'nama' => 'Minyak Goreng Curah', 'kelompok' => KelompokKomoditas::MinyakGoreng, 'satuan' => SatuanHarga::Kilogram],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = array_map(fn (array $komoditas): array => [
            'kode' => $komoditas['kode'],
            'nama' => $komoditas['nama'],
            'kelompok' => $komoditas['kelompok']->value,
            'satuan' => $komoditas['satuan']->value,
        ], self::KOMODITAS);

        Komoditas::query()->upsert($rows, ['kode'], ['nama', 'kelompok', 'satuan']);
    }
}
