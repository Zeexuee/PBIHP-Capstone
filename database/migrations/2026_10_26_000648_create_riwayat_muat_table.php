<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan setiap kali data dimuat ke tabel fakta, oleh DataTiruanSeeder
     * maupun pipeline ETL Python.
     *
     * Tabel ini juga menjadi pengaman: ETL menolak memuat data asli selama ada
     * baris bersumber 'tiruan', agar data asli tidak tercampur data buatan.
     * Kolom rincian menyimpan jumlah baris yang dilewati beserta alasannya.
     */
    public function up(): void
    {
        Schema::create('riwayat_muat', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 20)->index();
            $table->string('status', 20);
            $table->date('tanggal_data_awal')->nullable();
            $table->date('tanggal_data_akhir')->nullable();
            $table->unsignedInteger('baris_dimuat')->default(0);
            $table->unsignedInteger('baris_dilewati')->default(0);
            $table->json('rincian')->nullable();
            $table->timestamp('mulai_pada');
            $table->timestamp('selesai_pada')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_muat');
    }
};
