<?php

namespace Tests\Feature\Models;

use App\Enums\JenisPasar;
use App\Enums\SumberData;
use App\Enums\TingkatHarga;
use App\Models\HargaHarian;
use App\Models\Komoditas;
use App\Models\Provinsi;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HargaHarianTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_second_price_for_the_same_day_province_commodity_level_market_and_source(): void
    {
        $harga = HargaHarian::factory()->create(['tanggal' => '2026-03-02']);

        $this->expectException(UniqueConstraintViolationException::class);

        HargaHarian::factory()
            ->for($harga->provinsi)
            ->for($harga->komoditas)
            ->create(['tanggal' => '2026-03-02']);
    }

    public function test_accepts_a_same_day_price_from_a_different_market_type(): void
    {
        $tradisional = HargaHarian::factory()->create(['tanggal' => '2026-03-02']);

        $modern = HargaHarian::factory()
            ->pasarModern()
            ->for($tradisional->provinsi)
            ->for($tradisional->komoditas)
            ->create(['tanggal' => '2026-03-02']);

        $this->assertSame(2, HargaHarian::count());
        $this->assertSame(JenisPasar::Modern, $modern->fresh()->jenis_pasar);
    }

    public function test_upsert_on_the_unique_key_updates_the_price_instead_of_adding_a_row(): void
    {
        $harga = HargaHarian::factory()->create(['tanggal' => '2026-03-02', 'harga' => 15000]);

        HargaHarian::query()->upsert([[
            'tanggal' => '2026-03-02',
            'provinsi_id' => $harga->provinsi_id,
            'komoditas_id' => $harga->komoditas_id,
            'tingkat_harga' => TingkatHarga::Konsumen->value,
            'jenis_pasar' => JenisPasar::Tradisional->value,
            'sumber' => SumberData::Pihps->value,
            'harga' => 15500,
        ]], HargaHarian::KUNCI_UNIK, ['harga']);

        $this->assertSame(1, HargaHarian::count());
        $this->assertSame('15500.00', $harga->fresh()->harga);
    }

    public function test_price_without_a_market_type_is_stored_as_a_general_market(): void
    {
        $provinsi = Provinsi::factory()->create();
        $komoditas = Komoditas::factory()->create();

        $harga = HargaHarian::create([
            'tanggal' => '2026-03-02',
            'provinsi_id' => $provinsi->id,
            'komoditas_id' => $komoditas->id,
            'tingkat_harga' => TingkatHarga::Konsumen,
            'sumber' => SumberData::Pihps,
            'harga' => 15000,
        ]);

        $this->assertSame(JenisPasar::Umum, $harga->jenis_pasar);
        $this->assertDatabaseHas('harga_harian', ['id' => $harga->id, 'jenis_pasar' => 'umum']);
    }

    public function test_a_province_with_recorded_prices_cannot_be_deleted(): void
    {
        $harga = HargaHarian::factory()->create();

        $this->expectException(QueryException::class);

        $harga->provinsi->delete();
    }
}
