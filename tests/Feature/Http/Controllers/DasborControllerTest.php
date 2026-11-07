<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\HargaNasional;
use App\Models\Komoditas;
use Database\Seeders\KomoditasSeeder;
use Database\Seeders\ProvinsiSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DasborControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_renders_the_dashboard_with_every_section(): void
    {
        $this->withoutVite();
        $this->seed([ProvinsiSeeder::class, KomoditasSeeder::class]);

        $response = $this->get(route('dasbor'));

        $response->assertOk()
            ->assertSee('Harga Beras dan Minyak Goreng')
            ->assertSee('id="ringkasan"', false)
            ->assertSee('id="tren"', false)
            ->assertSee('id="perbandingan"', false)
            ->assertSee('id="prediksi"', false)
            ->assertSee('id="tentang-data"', false);
    }

    public function test_lists_every_commodity_with_its_unit_and_every_province(): void
    {
        $this->withoutVite();
        $this->seed([ProvinsiSeeder::class, KomoditasSeeder::class]);

        $response = $this->get(route('dasbor'));

        $response->assertOk()
            ->assertSee('Beras Medium (per kg)')
            ->assertSee('Minyak Goreng Curah (per kg)')
            ->assertSee('Kalimantan Utara')
            ->assertSee('Kepulauan Bangka Belitung');
    }

    public function test_shows_the_dummy_data_warning_while_dummy_data_is_enabled(): void
    {
        $this->withoutVite();
        config(['dasbor.data_tiruan' => true]);

        $response = $this->get(route('dasbor'));

        $response->assertOk()->assertSee('Jangan mengutip angka atau tangkapan layar halaman ini sebagai hasil penelitian.');
    }

    public function test_hides_the_dummy_data_warning_once_real_data_is_loaded(): void
    {
        $this->withoutVite();
        config(['dasbor.data_tiruan' => false]);

        $response = $this->get(route('dasbor'));

        $response->assertOk()->assertDontSee('Jangan mengutip angka atau tangkapan layar halaman ini sebagai hasil penelitian.');
    }

    public function test_shows_the_latest_data_date_in_indonesian(): void
    {
        $this->withoutVite();
        $komoditas = Komoditas::factory()->create();
        HargaNasional::factory()->for($komoditas)->create(['tanggal' => '2024-01-01']);
        HargaNasional::factory()->for($komoditas)->create(['tanggal' => '2026-10-02']);

        $response = $this->get(route('dasbor'));

        $response->assertOk()
            ->assertSee('2 Oktober 2026')
            ->assertSee('1 Januari 2024 sampai 2 Oktober 2026');
    }

    public function test_passes_the_api_addresses_to_the_script_as_json(): void
    {
        $this->withoutVite();

        $response = $this->get(route('dasbor'));

        $response->assertOk()
            ->assertSee('<script type="application/json" id="konfigurasi-dasbor">', false)
            ->assertSee(json_encode(route('api.v1.ringkasan')), false);
    }

    public function test_renders_without_error_when_the_database_holds_no_prices(): void
    {
        $this->withoutVite();

        $response = $this->get(route('dasbor'));

        $response->assertOk()->assertSee('belum ada data');
    }
}
