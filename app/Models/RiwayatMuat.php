<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Enums\StatusMuat;
use Database\Factories\RiwayatMuatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan pemuatan data. Baris ditulis oleh DataTiruanSeeder dan pipeline ETL.
 */
#[Table(name: 'riwayat_muat', timestamps: false)]
#[Fillable(['sumber', 'status', 'tanggal_data_awal', 'tanggal_data_akhir', 'baris_dimuat', 'baris_dilewati', 'rincian', 'mulai_pada', 'selesai_pada'])]
class RiwayatMuat extends Model
{
    /** @use HasFactory<RiwayatMuatFactory> */
    use HasFactory;

    /**
     * Penanda sumber untuk data buatan DataTiruanSeeder. ETL menolak memuat
     * data asli selama baris dengan sumber ini masih ada.
     */
    public const string SUMBER_TIRUAN = 'tiruan';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusMuat::class,
            'tanggal_data_awal' => Tanggal::class,
            'tanggal_data_akhir' => Tanggal::class,
            'baris_dimuat' => 'integer',
            'baris_dilewati' => 'integer',
            'rincian' => 'array',
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }
}
