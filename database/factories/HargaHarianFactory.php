<?php

namespace Database\Factories;

use App\Enums\JenisPasar;
use App\Enums\SumberData;
use App\Enums\TingkatHarga;
use App\Models\HargaHarian;
use App\Models\Komoditas;
use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HargaHarian>
 */
class HargaHarianFactory extends Factory
{
    /**
     * Harga konsumen di pasar tradisional, yaitu data utama dari PIHPS.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tanggal' => fake()->dateTimeBetween('2024-01-01', '2026-09-30')->format('Y-m-d'),
            'provinsi_id' => Provinsi::factory(),
            'komoditas_id' => Komoditas::factory(),
            'tingkat_harga' => TingkatHarga::Konsumen,
            'jenis_pasar' => JenisPasar::Tradisional,
            'sumber' => SumberData::Pihps,
            'harga' => fake()->randomFloat(2, 10000, 20000),
        ];
    }

    /**
     * Harga yang tercatat di tingkat produsen, tanpa jenis pasar.
     */
    public function produsen(): static
    {
        return $this->state(fn (): array => [
            'tingkat_harga' => TingkatHarga::Produsen,
            'jenis_pasar' => JenisPasar::Umum,
        ]);
    }

    /**
     * Harga konsumen di pasar modern, yang bersifat pelengkap.
     */
    public function pasarModern(): static
    {
        return $this->state(fn (): array => [
            'jenis_pasar' => JenisPasar::Modern,
        ]);
    }
}
