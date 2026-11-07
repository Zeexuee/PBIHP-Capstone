<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ModelPrediksi;
use App\Models\HargaHarian;
use App\Models\Komoditas;
use App\Models\MetrikModel;
use App\Models\Prediksi;
use App\Models\Provinsi;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrediksiControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_the_selected_model_when_it_meets_every_acceptance_criterion(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonPath('data.model.kode', 'xgboost')
            ->assertJsonPath('data.model.label', 'XGBoost')
            ->assertJsonPath('data.memakai_garis_dasar', false)
            ->assertJsonPath('data.prediksi', [['tanggal' => '2026-10-05', 'harga' => 15050], ['tanggal' => '2026-10-06', 'harga' => 15080]])
            ->assertJsonPath('data.garis_dasar', [['tanggal' => '2026-10-05', 'harga' => 15000], ['tanggal' => '2026-10-06', 'harga' => 15000]]);
    }

    public function test_falls_back_to_the_naive_baseline_when_the_selected_model_does_not_beat_it(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 130, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonPath('data.model.kode', 'naive')
            ->assertJsonPath('data.memakai_garis_dasar', true)
            ->assertJsonPath('data.prediksi.0.harga', 15000)
            ->assertJsonPath('data.garis_dasar', []);
    }

    public function test_falls_back_to_the_naive_baseline_when_mape_exceeds_the_threshold(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 3.2, rmseTerpilih: 110, maeTerpilih: 90, mapeNaive: 3.5);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()->assertJsonPath('data.memakai_garis_dasar', true);
    }

    public function test_reports_each_criterion_for_every_model(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 130, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $metrik = collect($response->json('data.metrik'))->keyBy('model');

        $this->assertSame(['lolos_mape' => true, 'lolos_r2' => true, 'mengalahkan_naive' => false, 'lolos' => false], collect($metrik['xgboost'])->only(['lolos_mape', 'lolos_r2', 'mengalahkan_naive', 'lolos'])->all());
        $this->assertNull($metrik['naive']['mengalahkan_naive']);
        $this->assertSame(['mape_maks' => 3, 'r2_min' => 0.7], $response->json('data.kriteria'));
    }

    public function test_returns_the_working_days_of_the_last_sixty_days_of_traditional_market_history_with_null_for_missing_days(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonCount(44, 'data.historis.tanggal')
            ->assertJsonPath('data.historis.tanggal.0', '2026-08-04')
            ->assertJsonPath('data.historis.tanggal.43', '2026-10-02')
            ->assertJsonPath('data.historis.harga.43', 15000)
            ->assertJsonPath('data.historis.tanggal.42', '2026-10-01')
            ->assertJsonPath('data.historis.harga.42', null)
            ->assertJsonPath('data.historis.harga.41', 14980);
    }

    public function test_uses_the_model_service_forecast_when_the_service_is_available(): void
    {
        $this->aturLayanan(Http::response($this->jawabanLayanan()));
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonPath('data.sumber_prakiraan', 'layanan_model')
            ->assertJsonPath('data.model.kode', 'xgboost')
            ->assertJsonPath('data.dihasilkan_pada', '2026-10-05T09:00:00+07:00')
            ->assertJsonPath('data.prediksi', [['tanggal' => '2026-10-05', 'harga' => 15120], ['tanggal' => '2026-10-06', 'harga' => 15160]])
            ->assertJsonPath('data.garis_dasar', [['tanggal' => '2026-10-05', 'harga' => 15000], ['tanggal' => '2026-10-06', 'harga' => 15000]]);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'http://layanan.test/v1/prakiraan')
            && $request['komoditas'] === 'beras-medium'
            && $request['provinsi'] === '31');
    }

    public function test_uses_the_service_baseline_when_the_selected_model_does_not_beat_it(): void
    {
        $this->aturLayanan(Http::response($this->jawabanLayanan()));
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 130, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonPath('data.sumber_prakiraan', 'layanan_model')
            ->assertJsonPath('data.memakai_garis_dasar', true)
            ->assertJsonPath('data.prediksi.1.harga', 15000)
            ->assertJsonPath('data.garis_dasar', []);
    }

    public function test_falls_back_to_the_prediction_table_when_the_service_cannot_be_reached(): void
    {
        $this->aturLayanan(Http::failedConnection());

        $this->assertMemakaiTabelPrediksi();
    }

    public function test_does_not_retry_an_unreachable_service_until_the_pause_ends(): void
    {
        $this->aturLayanan(Http::failedConnection());
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);
        $url = route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']);

        $this->getJson($url)->assertJsonPath('data.sumber_prakiraan', 'basis_data');
        $this->getJson($url)->assertJsonPath('data.sumber_prakiraan', 'basis_data');
        Http::assertSentCount(1);

        $this->travel(31)->seconds();

        $this->getJson($url)->assertJsonPath('data.sumber_prakiraan', 'basis_data');
        Http::assertSentCount(2);
    }

    public function test_falls_back_to_the_prediction_table_when_the_service_returns_an_error(): void
    {
        $this->aturLayanan(Http::response(['message' => 'Model belum siap dipakai.'], 503));

        $this->assertMemakaiTabelPrediksi();
    }

    public function test_falls_back_to_the_prediction_table_when_the_service_response_breaks_the_contract(): void
    {
        $jawaban = $this->jawabanLayanan();
        unset($jawaban['data']['prakiraan']['xgboost']);
        $this->aturLayanan(Http::response($jawaban));

        $this->assertMemakaiTabelPrediksi();
    }

    public function test_falls_back_to_the_prediction_table_when_the_service_forecast_starts_from_other_data(): void
    {
        $jawaban = $this->jawabanLayanan();
        $jawaban['data']['harga_asal'] = 16000;
        $this->aturLayanan(Http::response($jawaban));

        $this->assertMemakaiTabelPrediksi();
    }

    public function test_does_not_call_the_model_service_when_it_is_not_configured(): void
    {
        Http::fake();
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()->assertJsonPath('data.sumber_prakiraan', 'basis_data');
        Http::assertNothingSent();
    }

    public function test_returns_422_when_the_province_is_missing(): void
    {
        Komoditas::factory()->create(['kode' => 'beras-medium']);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provinsi' => 'Pilih satu provinsi untuk melihat prediksi.']);
    }

    /**
     * @param  \Closure|PromiseInterface  $jawaban
     */
    private function aturLayanan(mixed $jawaban): void
    {
        config(['services.layanan_model.url' => 'http://layanan.test']);
        Http::preventStrayRequests();
        Http::fake(['layanan.test/v1/prakiraan*' => $jawaban]);
    }

    /**
     * Jawaban layanan yang berangkat dari harga terakhir di siapkanPrediksi().
     *
     * @return array{data: array<string, mixed>}
     */
    private function jawabanLayanan(): array
    {
        $titik = fn (int $hariPertama, int $hariKedua): array => [
            ['tanggal' => '2026-10-05', 'harga' => $hariPertama],
            ['tanggal' => '2026-10-06', 'harga' => $hariKedua],
        ];

        return ['data' => [
            'komoditas' => 'beras-medium',
            'provinsi' => '31',
            'tanggal_asal' => '2026-10-02',
            'harga_asal' => 15000,
            'tanggal_model' => '2026-10-02',
            'putaran_pemodelan' => 2,
            'terpilih' => 'xgboost',
            'dihasilkan_pada' => '2026-10-05T09:00:00.123456+07:00',
            'prakiraan' => [
                'naive' => $titik(15000, 15000),
                'random_forest' => $titik(15010, 15020),
                'xgboost' => $titik(15120, 15160),
            ],
        ]];
    }

    private function assertMemakaiTabelPrediksi(): void
    {
        $this->siapkanPrediksi(mapeTerpilih: 0.64, rmseTerpilih: 110, maeTerpilih: 90);

        $response = $this->getJson(route('api.v1.prediksi', ['komoditas' => 'beras-medium', 'provinsi' => '31']));

        $response->assertOk()
            ->assertJsonPath('data.sumber_prakiraan', 'basis_data')
            ->assertJsonPath('data.prediksi', [['tanggal' => '2026-10-05', 'harga' => 15050], ['tanggal' => '2026-10-06', 'harga' => 15080]]);
        Http::assertSentCount(1);
    }

    private function siapkanPrediksi(float $mapeTerpilih, float $rmseTerpilih, float $maeTerpilih, float $mapeNaive = 0.82): void
    {
        $komoditas = Komoditas::factory()->create(['kode' => 'beras-medium']);
        $provinsi = Provinsi::factory()->create(['kode' => '31', 'nama' => 'DKI Jakarta']);

        MetrikModel::factory()->naive()->for($komoditas)->create(['mape' => $mapeNaive, 'rmse' => 120, 'mae' => 100, 'r2' => 0.84]);
        MetrikModel::factory()->terpilih()->for($komoditas)->create([
            'model' => ModelPrediksi::Xgboost,
            'mape' => $mapeTerpilih,
            'rmse' => $rmseTerpilih,
            'mae' => $maeTerpilih,
            'r2' => 0.9,
        ]);

        HargaHarian::factory()->for($komoditas)->for($provinsi)->create(['tanggal' => '2026-10-02', 'harga' => 15000]);
        HargaHarian::factory()->for($komoditas)->for($provinsi)->create(['tanggal' => '2026-09-30', 'harga' => 14980]);
        HargaHarian::factory()->pasarModern()->for($komoditas)->for($provinsi)->create(['tanggal' => '2026-10-01', 'harga' => 99999]);

        foreach (['2026-10-05' => [15000, 15050], '2026-10-06' => [15000, 15080]] as $tanggal => [$naive, $xgboost]) {
            Prediksi::factory()->for($komoditas)->for($provinsi)->create(['model' => ModelPrediksi::Naive, 'tanggal_sasaran' => $tanggal, 'harga_prediksi' => $naive]);
            Prediksi::factory()->for($komoditas)->for($provinsi)->create(['model' => ModelPrediksi::Xgboost, 'tanggal_sasaran' => $tanggal, 'harga_prediksi' => $xgboost]);
        }
    }
}
