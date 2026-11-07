<?php

namespace App\Enums;

/**
 * Tingkat rantai pasok tempat harga dicatat.
 */
enum TingkatHarga: string
{
    case Konsumen = 'konsumen';
    case Produsen = 'produsen';

    /**
     * Jenis pasar yang menjadi data utama untuk tingkat harga ini.
     *
     * Harga konsumen diambil dari pasar tradisional karena hanya jenis harga
     * itu yang tersedia lengkap untuk ke-21 provinsi di PIHPS. PIHPS mencatat
     * harga produsen tanpa membedakan jenis pasar. Angka nasional, indikator
     * selisih, dan prediksi selalu mengacu pada jenis pasar ini.
     */
    public function jenisPasarUtama(): JenisPasar
    {
        return match ($this) {
            self::Konsumen => JenisPasar::Tradisional,
            self::Produsen => JenisPasar::Umum,
        };
    }
}
