<?php

namespace Tests\Feature\Seeders;

use App\Enums\KelompokKomoditas;
use App\Enums\SatuanHarga;
use App\Models\Komoditas;
use Database\Seeders\KomoditasSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KomoditasSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prices_rice_and_cooking_oil_per_kilogram_as_published_by_pihps(): void
    {
        $this->seed(KomoditasSeeder::class);

        $satuanPerKode = Komoditas::orderBy('kode')
            ->get()
            ->mapWithKeys(fn (Komoditas $komoditas): array => [$komoditas->kode => $komoditas->satuan])
            ->all();

        $this->assertSame([
            'beras-medium' => SatuanHarga::Kilogram,
            'beras-premium' => SatuanHarga::Kilogram,
            'minyak-goreng-curah' => SatuanHarga::Kilogram,
            'minyak-goreng-kemasan' => SatuanHarga::Kilogram,
        ], $satuanPerKode);
    }

    public function test_every_commodity_in_a_group_shares_the_same_unit(): void
    {
        $this->seed(KomoditasSeeder::class);

        $satuanPerKelompok = Komoditas::all()
            ->groupBy(fn (Komoditas $komoditas): string => $komoditas->kelompok->value)
            ->map(fn (Collection $anggota): array => $anggota->pluck('satuan')->unique()->values()->all())
            ->all();

        $this->assertSame([SatuanHarga::Kilogram], $satuanPerKelompok[KelompokKomoditas::Beras->value]);
        $this->assertSame([SatuanHarga::Kilogram], $satuanPerKelompok[KelompokKomoditas::MinyakGoreng->value]);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_commodities(): void
    {
        $this->seed(KomoditasSeeder::class);

        $this->seed(KomoditasSeeder::class);

        $this->assertSame(4, Komoditas::count());
    }
}
