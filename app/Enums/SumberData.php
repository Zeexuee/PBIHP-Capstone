<?php

namespace App\Enums;

/**
 * Sumber data harga. PIHPS Bank Indonesia adalah satu-satunya sumber; kolom
 * sumber tetap disimpan agar asal setiap baris harga selalu tercatat.
 */
enum SumberData: string
{
    case Pihps = 'pihps';
}
