<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indikator deskriptif yang dihitung pada tahap praproses dan disimpan,
     * agar dasbor tidak menghitung ulang selisih setiap kali halaman dibuka.
     * Indikator dihitung dari harga PIHPS pada jenis pasar utama setiap tingkat
     * harga, yaitu pasar tradisional untuk konsumen dan pasar umum untuk produsen.
     */
    public function up(): void
    {
        Schema::create('indikator_harga', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('provinsi_id');
            $table->foreignId('komoditas_id');
            $table->string('tingkat_harga', 20);
            $table->decimal('harga', 12, 2);
            $table->decimal('rata_rata_nasional', 12, 2);
            $table->decimal('selisih', 12, 2);
            $table->decimal('selisih_persen', 7, 2);
            $table->timestamps();

            $table->unique(['tanggal', 'provinsi_id', 'komoditas_id', 'tingkat_harga'], 'indikator_harga_unik');
            $table->index(['komoditas_id', 'tingkat_harga', 'tanggal'], 'indikator_harga_komoditas_tanggal');
            $table->index(['provinsi_id', 'komoditas_id', 'tanggal'], 'indikator_harga_wilayah_tanggal');

            $table->foreign('provinsi_id')->references('id')->on('provinsi')->restrictOnDelete();
            $table->foreign('komoditas_id')->references('id')->on('komoditas')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indikator_harga');
    }
};
