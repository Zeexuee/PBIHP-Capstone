<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensi waktu. Penanda hari besar dan musim panen disimpan di sini untuk
     * penanda pada grafik tren. Model prediksi memakainya sebagai fitur hanya
     * pada putaran pemodelan pertama (CATATAN_DATA.md bagian 8).
     */
    public function up(): void
    {
        Schema::create('kalender', function (Blueprint $table) {
            $table->date('tanggal')->primary();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedTinyInteger('minggu_iso');
            $table->unsignedTinyInteger('hari_iso');
            $table->boolean('akhir_pekan');
            $table->string('hari_besar', 40)->nullable();
            $table->boolean('musim_panen')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kalender');
    }
};
