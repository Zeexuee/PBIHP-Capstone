<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penanda Data Tiruan
    |--------------------------------------------------------------------------
    |
    | Selama basis data masih berisi DataTiruanSeeder, dasbor menampilkan
    | spanduk peringatan agar tangkapan layar tidak dikira hasil penelitian.
    | Ubah menjadi false hanya setelah ETL memuat data asli.
    |
    */

    'data_tiruan' => (bool) env('DASBOR_DATA_TIRUAN', true),

    /*
    |--------------------------------------------------------------------------
    | Kriteria Penerimaan Model (Tabel 3.6)
    |--------------------------------------------------------------------------
    |
    | MAPE harus di bawah ambang, R² tidak kurang dari ambang, dan model harus
    | mengalahkan garis dasar Naive pada MAPE, RMSE, dan MAE.
    |
    */

    'kriteria' => [
        'mape_maks' => 3.0,
        'r2_min' => 0.70,
    ],

    'tren' => [
        'maks_provinsi' => 5,
        'rentang_bawaan_hari' => 180,
        'rentang_maks_hari' => 1100,
    ],

    /*
    | Rentang harga tercatat yang ditampilkan sebelum prakiraan, dalam hari
    | kalender. Hanya hari kerja di dalam rentang ini yang dikirim ke dasbor.
    */

    'prediksi' => [
        'hari_historis' => 60,
    ],

    'batas_permintaan_per_menit' => 120,

];
