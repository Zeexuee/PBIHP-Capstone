<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Pulau;
use App\Models\HargaNasional;
use App\Models\IndikatorHarga;
use App\Models\Komoditas;
use App\Models\Provinsi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RingkasanControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_the_latest_national_price_with_its_thirty_day_change(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium']));

        $response->assertOk()
            ->assertJsonPath('data.tanggal', '2026-10-01')
            ->assertJsonPath('data.harga_nasional', 14700)
            ->assertJsonPath('data.perubahan.tanggal_pembanding', '2026-09-01')
            ->assertJsonPath('data.perubahan.harga_pembanding', 14000)
            ->assertJsonPath('data.perubahan.persen', 5)
            ->assertJsonPath('data.komoditas.satuan', 'kg');
    }

    public function test_returns_the_cheapest_and_priciest_province_and_the_gap_between_them(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium']));

        $response->assertOk()
            ->assertJsonPath('data.termurah.nama', 'Jawa Tengah')
            ->assertJsonPath('data.termurah.selisih_persen', -4.76)
            ->assertJsonPath('data.termahal.nama', 'Sumatera Barat')
            ->assertJsonPath('data.rentang.rupiah', 2000)
            ->assertJsonPath('data.rentang.persen', 14.29)
            ->assertJsonPath('data.cakupan.jumlah_provinsi', 3)
            ->assertJsonPath('data.cakupan.jumlah_berdata', 3);
    }

    public function test_limits_the_cheapest_and_priciest_province_to_the_selected_island(): void
    {
        $this->siapkanData();

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium', 'pulau' => 'jawa']));

        $response->assertOk()
            ->assertJsonPath('data.termurah.nama', 'Jawa Tengah')
            ->assertJsonPath('data.termahal.nama', 'DKI Jakarta')
            ->assertJsonPath('data.harga_nasional', 14700)
            ->assertJsonPath('data.cakupan.jumlah_provinsi', 2);
    }

    public function test_counts_only_provinces_with_data_on_the_latest_date(): void
    {
        $data = $this->siapkanData();
        IndikatorHarga::whereBelongsTo($data['sumatera_barat'])->delete();

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium']));

        $response->assertOk()
            ->assertJsonPath('data.termahal.nama', 'DKI Jakarta')
            ->assertJsonPath('data.cakupan.jumlah_provinsi', 3)
            ->assertJsonPath('data.cakupan.jumlah_berdata', 2);
    }

    public function test_returns_an_empty_summary_when_no_national_price_exists(): void
    {
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium']));

        $response->assertOk()
            ->assertJsonPath('data.tanggal', null)
            ->assertJsonPath('data.termurah', null)
            ->assertJsonPath('data.cakupan.jumlah_berdata', 0);
    }

    public function test_returns_422_when_the_commodity_is_missing(): void
    {
        $response = $this->getJson(route('api.v1.ringkasan'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['komoditas' => 'Komoditas wajib diisi.']);
    }

    public function test_returns_422_for_an_unknown_commodity(): void
    {
        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'gula-pasir']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['komoditas' => 'Komoditas tidak dikenal.']);
    }

    public function test_returns_422_for_a_producer_price_of_cooking_oil(): void
    {
        Komoditas::factory()->minyakGoreng()->create(['kode' => 'minyak-goreng-kemasan']);

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'minyak-goreng-kemasan', 'tingkat' => 'produsen']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tingkat' => 'Harga produsen hanya tersedia untuk beras.']);
    }

    public function test_returns_422_in_indonesian_for_an_unknown_price_level_or_island(): void
    {
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.ringkasan', ['komoditas' => 'beras-medium', 'tingkat' => 'grosir', 'pulau' => 'papua']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tingkat' => 'Tingkat harga tidak valid.',
                'pulau' => 'Pulau tidak valid.',
            ]);
    }

    /**
     * @return array{komoditas: Komoditas, jawa_tengah: Provinsi, dki_jakarta: Provinsi, sumatera_barat: Provinsi}
     */
    private function siapkanData(): array
    {
        $komoditas = Komoditas::factory()->create(['kode' => 'beras-medium']);
        $jawaTengah = Provinsi::factory()->create(['kode' => '33', 'nama' => 'Jawa Tengah', 'pulau' => Pulau::Jawa]);
        $dkiJakarta = Provinsi::factory()->create(['kode' => '31', 'nama' => 'DKI Jakarta', 'pulau' => Pulau::Jawa]);
        $sumateraBarat = Provinsi::factory()->create(['kode' => '13', 'nama' => 'Sumatera Barat', 'pulau' => Pulau::Sumatra]);

        HargaNasional::factory()->for($komoditas)->create(['tanggal' => '2026-09-01', 'harga' => 14000]);
        HargaNasional::factory()->for($komoditas)->create(['tanggal' => '2026-10-01', 'harga' => 14700]);

        foreach ([[$jawaTengah, 14000, -700, -4.76], [$dkiJakarta, 15000, 300, 2.04], [$sumateraBarat, 16000, 1300, 8.84]] as [$provinsi, $harga, $selisih, $persen]) {
            IndikatorHarga::factory()->for($komoditas)->for($provinsi)->create([
                'tanggal' => '2026-10-01',
                'harga' => $harga,
                'rata_rata_nasional' => 14700,
                'selisih' => $selisih,
                'selisih_persen' => $persen,
            ]);
        }

        return ['komoditas' => $komoditas, 'jawa_tengah' => $jawaTengah, 'dki_jakarta' => $dkiJakarta, 'sumatera_barat' => $sumateraBarat];
    }
}
