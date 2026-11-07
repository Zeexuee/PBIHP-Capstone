<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil prediksi yang ditulis oleh layanan Python. Dasbor membaca dari tabel
     * ini, sehingga prediksi terakhir tetap tampil walaupun layanan Python mati.
     * Satu baris per wilayah, komoditas, model, dan tanggal sasaran. Menjalankan
     * ulang pemodelan menghapus seluruh baris satu komoditas lalu menyisipkan
     * baris baru dalam satu transaksi, sehingga tanggal sasaran lama tidak
     * tertinggal.
     */
    public function up(): void
    {
        Schema::create('prediksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provinsi_id');
            $table->foreignId('komoditas_id');
            $table->string('model', 20);
            $table->date('tanggal_sasaran');
            $table->decimal('harga_prediksi', 12, 2);
            $table->timestamp('dihasilkan_pada');
            $table->timestamps();

            $table->unique(['provinsi_id', 'komoditas_id', 'model', 'tanggal_sasaran'], 'prediksi_unik');
            $table->index(['komoditas_id', 'tanggal_sasaran'], 'prediksi_komoditas_tanggal');

            $table->foreign('provinsi_id')->references('id')->on('provinsi')->restrictOnDelete();
            $table->foreign('komoditas_id')->references('id')->on('komoditas')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediksi');
    }
};
