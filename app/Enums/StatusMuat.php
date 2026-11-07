<?php

namespace App\Enums;

/**
 * Status satu proses pemuatan data ke tabel fakta.
 */
enum StatusMuat: string
{
    case Berjalan = 'berjalan';
    case Berhasil = 'berhasil';
    case Gagal = 'gagal';
}
