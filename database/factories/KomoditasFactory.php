<?php

namespace Database\Factories;

use App\Enums\KelompokKomoditas;
use App\Enums\SatuanHarga;
use App\Models\Komoditas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Komoditas>
 */
class KomoditasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = 'Beras '.fake()->unique()->word();

        return [
            'kode' => Str::slug($nama),
            'nama' => $nama,
            'kelompok' => KelompokKomoditas::Beras,
            'satuan' => SatuanHarga::Kilogram,
        ];
    }

    /**
     * Varian minyak goreng, yang seperti beras dicatat PIHPS per kilogram.
     */
    public function minyakGoreng(): static
    {
        return $this->state(function (): array {
            $nama = 'Minyak Goreng '.fake()->unique()->word();

            return [
                'kode' => Str::slug($nama),
                'nama' => $nama,
                'kelompok' => KelompokKomoditas::MinyakGoreng,
                'satuan' => SatuanHarga::Kilogram,
            ];
        });
    }
}
