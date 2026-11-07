<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Pulau;
use App\Models\HargaHarian;
use App\Models\HargaNasional;
use App\Models\Komoditas;
use App\Models\Provinsi;
use Database\Seeders\KalenderSeeder;
use Database\Seeders\ProvinsiSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrenControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_null_for_days_without_province_data_instead_of_filling_them(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', [
            'komoditas' => 'beras-medium',
            'provinsi' => ['35'],
            'mulai' => '2026-09-28',
            'selesai' => '2026-10-02',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.tanggal', ['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'])
            ->assertJsonPath('data.seri.0.nama', 'Rata-rata nasional')
            ->assertJsonPath('data.seri.0.harga', [14500, 14500, 14600, 14600, 14700])
            ->assertJsonPath('data.seri.1.nama', 'Jawa Timur')
            ->assertJsonPath('data.seri.1.harga', [14000, null, 14200, null, null]);
    }

    public function test_weekly_aggregation_averages_only_the_days_that_have_data(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', [
            'komoditas' => 'beras-medium',
            'provinsi' => ['35'],
            'mulai' => '2026-09-28',
            'selesai' => '2026-10-05',
            'agregasi' => 'mingguan',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.tanggal', ['2026-09-28', '2026-10-05'])
            ->assertJsonPath('data.seri.1.harga', [14100, null]);
    }

    public function test_monthly_aggregation_labels_each_month_by_its_first_day(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', [
            'komoditas' => 'beras-medium',
            'mulai' => '2026-09-15',
            'selesai' => '2026-10-10',
            'agregasi' => 'bulanan',
        ]));

        $response->assertOk()->assertJsonPath('data.tanggal', ['2026-09-01', '2026-10-01']);
    }

    public function test_keeps_provinces_in_the_requested_order(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium', 'provinsi' => ['35', '31']]));

        $response->assertOk()
            ->assertJsonCount(3, 'data.seri')
            ->assertJsonPath('data.seri.1.kode', '35')
            ->assertJsonPath('data.seri.2.kode', '31');
    }

    public function test_defaults_to_the_last_180_days_ending_on_the_latest_national_date(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium']));

        $response->assertOk()
            ->assertJsonPath('data.mulai', '2026-04-06')
            ->assertJsonPath('data.selesai', '2026-10-02')
            ->assertJsonCount(130, 'data.tanggal');
    }

    public function test_daily_series_skips_weekends_because_pihps_records_working_days_only(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.tren', [
            'komoditas' => 'beras-medium',
            'provinsi' => ['35'],
            'mulai' => '2026-10-02',
            'selesai' => '2026-10-05',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.tanggal', ['2026-10-02', '2026-10-05'])
            ->assertJsonPath('data.seri.0.harga', [14700, null]);
    }

    public function test_producer_series_reads_producer_prices_recorded_without_a_market_type(): void
    {
        $komoditas = Komoditas::factory()->create(['kode' => 'beras-medium']);
        $jawaTimur = Provinsi::factory()->create(['kode' => '35', 'nama' => 'Jawa Timur', 'pulau' => Pulau::Jawa]);
        HargaHarian::factory()->produsen()->for($komoditas)->for($jawaTimur)->create(['tanggal' => '2026-10-01', 'harga' => 12500]);
        HargaHarian::factory()->for($komoditas)->for($jawaTimur)->create(['tanggal' => '2026-10-01', 'harga' => 14600]);

        $response = $this->getJson(route('api.v1.tren', [
            'komoditas' => 'beras-medium',
            'tingkat' => 'produsen',
            'provinsi' => ['35'],
            'mulai' => '2026-10-01',
            'selesai' => '2026-10-01',
        ]));

        $response->assertOk()->assertJsonPath('data.seri.1.harga', [12500]);
    }

    public function test_returns_holiday_markers_and_the_harvest_season_for_rice(): void
    {
        $this->seed(KalenderSeeder::class);
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium', 'mulai' => '2026-02-01', 'selesai' => '2026-05-31']));

        $response->assertOk()
            ->assertJsonPath('data.penanda', [
                ['tanggal' => '2026-02-17', 'nama' => 'Tahun Baru Imlek'],
                ['tanggal' => '2026-02-19', 'nama' => 'Awal Ramadan'],
                ['tanggal' => '2026-03-21', 'nama' => 'Idul Fitri'],
                ['tanggal' => '2026-05-27', 'nama' => 'Idul Adha'],
            ])
            ->assertJsonPath('data.musim_panen', [['mulai' => '2026-03-01', 'selesai' => '2026-04-30']]);
    }

    public function test_does_not_return_a_harvest_season_for_cooking_oil(): void
    {
        $this->seed(KalenderSeeder::class);
        Komoditas::factory()->minyakGoreng()->create(['kode' => 'minyak-goreng-kemasan']);

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'minyak-goreng-kemasan', 'mulai' => '2026-02-01', 'selesai' => '2026-05-31']));

        $response->assertOk()
            ->assertJsonPath('data.musim_panen', [])
            ->assertJsonCount(4, 'data.penanda');
    }

    public function test_returns_422_when_more_than_five_provinces_are_selected(): void
    {
        $this->seed(ProvinsiSeeder::class);
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium', 'provinsi' => ['11', '12', '13', '14', '15', '16']]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provinsi' => 'Pilih paling banyak 5 provinsi agar grafik tetap terbaca.']);
    }

    public function test_returns_422_when_the_range_is_longer_than_the_limit(): void
    {
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium', 'mulai' => '2024-01-01', 'selesai' => '2027-12-31']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['selesai' => 'Rentang tanggal paling panjang 1100 hari.']);
    }

    public function test_returns_422_when_the_end_date_is_before_the_start_date(): void
    {
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.tren', ['komoditas' => 'beras-medium', 'mulai' => '2026-10-02', 'selesai' => '2026-09-01']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['selesai' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.']);
    }

    private function siapkanData(): void
    {
        $komoditas = Komoditas::factory()->create(['kode' => 'beras-medium']);
        $jawaTimur = Provinsi::factory()->create(['kode' => '35', 'nama' => 'Jawa Timur', 'pulau' => Pulau::Jawa]);
        Provinsi::factory()->create(['kode' => '31', 'nama' => 'DKI Jakarta', 'pulau' => Pulau::Jawa]);

        $nasional = ['2026-09-28' => 14500, '2026-09-29' => 14500, '2026-09-30' => 14600, '2026-10-01' => 14600, '2026-10-02' => 14700];

        foreach ($nasional as $tanggal => $harga) {
            HargaNasional::factory()->for($komoditas)->create(['tanggal' => $tanggal, 'harga' => $harga]);
        }

        foreach (['2026-09-28' => 14000, '2026-09-30' => 14200] as $tanggal => $harga) {
            HargaHarian::factory()->for($komoditas)->for($jawaTimur)->create(['tanggal' => $tanggal, 'harga' => $harga]);
        }

        HargaHarian::factory()->pasarModern()->for($komoditas)->for($jawaTimur)->create(['tanggal' => '2026-10-01', 'harga' => 99999]);
    }
}
