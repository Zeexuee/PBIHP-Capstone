<?php

namespace Database\Factories;

use App\Enums\StatusMuat;
use App\Models\RiwayatMuat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatMuat>
 */
class RiwayatMuatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sumber' => 'pihps',
            'status' => StatusMuat::Berhasil,
            'tanggal_data_awal' => '2026-09-01',
            'tanggal_data_akhir' => '2026-09-30',
            'baris_dimuat' => fake()->numberBetween(100, 1000),
            'baris_dilewati' => 0,
            'rincian' => [],
            'mulai_pada' => now()->subMinute(),
            'selesai_pada' => now(),
        ];
    }

    /**
     * Penanda data buatan DataTiruanSeeder.
     */
    public function tiruan(): static
    {
        return $this->state(fn (): array => [
            'sumber' => RiwayatMuat::SUMBER_TIRUAN,
        ]);
    }
}
