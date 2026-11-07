<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layanan REST API Model
    |--------------------------------------------------------------------------
    |
    | Layanan Python di folder analitik (python -m layanan) yang menghitung
    | prakiraan dari berkas model. Bila URL kosong, layanan mati, atau
    | jawabannya tidak dapat dipakai, panel Prediksi membaca tabel prediksi.
    | Batas waktu sambungan dibuat pendek agar dasbor tidak menunggu lama
    | ketika layanan tidak berjalan.
    |
    */

    'layanan_model' => [
        'url' => env('LAYANAN_MODEL_URL'),
        'batas_waktu_sambung' => (float) env('LAYANAN_MODEL_BATAS_WAKTU_SAMBUNG', 1),
        'batas_waktu' => (float) env('LAYANAN_MODEL_BATAS_WAKTU', 5),
        // Setelah gagal tersambung, layanan tidak dicoba lagi selama sekian
        // detik, agar setiap permintaan tidak ikut menunggu batas waktu.
        'jeda_setelah_gagal' => (int) env('LAYANAN_MODEL_JEDA_SETELAH_GAGAL', 30),
    ],

];
