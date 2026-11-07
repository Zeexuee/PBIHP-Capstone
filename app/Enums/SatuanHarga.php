<?php

namespace App\Enums;

/**
 * Satuan baku harga sebuah komoditas.
 *
 * Seluruh harga memakai satuan yang diterbitkan PIHPS Bank Indonesia, yaitu
 * rupiah per kilogram, termasuk minyak goreng. Harga tidak pernah dikonversi
 * ke satuan lain, sehingga semua komoditas dapat dibandingkan apa adanya.
 */
enum SatuanHarga: string
{
    case Kilogram = 'kg';

    /**
     * Label satuan untuk ditempelkan pada angka harga, misalnya "/kg".
     */
    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'kg',
        };
    }
}
