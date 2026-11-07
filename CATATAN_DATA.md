# Catatan Keputusan dan Asumsi Data

Dokumen ini mencatat keputusan tim tentang data, asumsi yang belum terkonfirmasi, dan kontrak format yang wajib diikuti pipeline ETL Python saat mengisi basis data. Perbarui dokumen ini setiap kali ada keputusan baru.

## 1. Musim panen (asumsi, belum terkonfirmasi)

Kolom `kalender.musim_panen` saat ini bernilai `true` untuk bulan **Maret dan April** setiap tahun.

Ini **asumsi kerja**, bukan fakta yang sudah diverifikasi. Data BPS yang ditemukan hanya menunjukkan bahwa potensi luas panen padi Februari sampai April 2026 diperkirakan 3,92 juta hektare, atau sekitar 1,3 juta hektare per bulan. Angka itu jauh lebih tinggi daripada Mei 2025 (0,98 juta hektare) dan Juni 2025 (0,79 juta hektare). Panen kedua juga terlihat pada Agustus 2025 (1,11 juta hektare) dan September 2025 (1,13 juta hektare).

Kolom ini dipakai sebagai pita musim panen pada grafik tren. Pada putaran pemodelan pertama kolom ini juga menjadi fitur model, tetapi tidak lagi sejak putaran kedua (bagian 8). Sebelum kolom ini dipakai lagi sebagai fitur model, konfirmasi bulan panen raya dengan data luas panen padi bulanan BPS. Kalau hasilnya berbeda, ubah konstanta `BULAN_MUSIM_PANEN` di `database/seeders/KalenderSeeder.php`, lalu jalankan ulang `php artisan db:seed --class=KalenderSeeder`.

## 2. Satuan harga

| Kelompok | Varian | Satuan baku |
|---|---|---|
| Beras | Beras Medium, Beras Premium | kg |
| Minyak goreng | Minyak Goreng Kemasan, Minyak Goreng Curah | kg |

- Seluruh harga disimpan dalam rupiah per kilogram, satuan yang diterbitkan PIHPS untuk beras maupun minyak goreng. Harga tidak dikonversi ke satuan lain.
- Keputusan ini menggantikan keputusan lama yang memakai liter untuk minyak goreng. Liter dipilih ketika sumber utamanya masih Panel Harga Bapanas. Setelah PIHPS menjadi satu-satunya sumber (bagian 7), memakai liter berarti mengonversi seluruh data minyak goreng dengan faktor massa jenis yang tidak resmi.
- ETL tetap menolak harga yang satuannya berbeda dari `komoditas.satuan` dan melaporkannya sebagai baris yang dilewati, misalnya bila PIHPS kelak mengubah satuan sebuah komoditas.
- Angka dari publikasi lain yang memakai satuan liter, misalnya kutipan berita di laporan, tidak dibandingkan langsung dengan data platform.

## 3. Kalender hari besar

Kalender mencakup 1 Januari 2024 sampai 31 Desember 2026. Hari besar yang dicatat:

| Hari besar | 2024 | 2025 | 2026 |
|---|---|---|---|
| Tahun Baru Imlek | 10 Feb | 29 Jan | 17 Feb |
| Awal Ramadan | 12 Mar | 1 Mar | 19 Feb |
| Idul Fitri (1 Syawal) | 10 Apr | 31 Mar | 21 Mar |
| Idul Adha | 17 Jun | 6 Jun | 27 Mei |
| Natal | 25 Des | 25 Des | 25 Des |

Sumber dan aturan pencatatan:

- Idul Fitri, Idul Adha, Imlek, dan Natal mengikuti hari libur nasional dalam SKB 3 Menteri, diperiksa silang dengan daftar OfficeHolidays 2024 dan 2025 serta daftar hari libur Indonesia 2026.
- Awal Ramadan mengikuti penetapan pemerintah melalui sidang isbat Kementerian Agama, bukan penetapan organisasi kemasyarakatan. Contohnya, pada 2026 pemerintah menetapkan 19 Februari, sedangkan Muhammadiyah 18 Februari.
- Untuk Idul Fitri hanya 1 Syawal yang dicatat, walaupun hari kedua juga libur nasional.

**Tahun 2027 belum dicatat.** Dua sumber yang ditemukan saling bertentangan:

| Hari besar 2027 | Sumber A (artikel yang mengutip SKB) | Sumber B (OfficeHolidays) |
|---|---|---|
| Tahun Baru Imlek | 6 Feb | 6 Feb |
| Idul Fitri | 10 Mar | 9 Mar |
| Idul Adha | 17 Mei | 16 Mei |
| Natal | 25 Des | 25 Des |

Kalender diperpanjang ke 2027 setelah SKB 3 Menteri tentang libur nasional 2027 dibaca langsung dari dokumen resminya. Ini perlu dikerjakan sebelum grafik tren menampilkan tanggal sesudah 31 Desember 2026, agar penanda hari besar dan pita musim panen tetap tampil. Pemodelan tidak lagi membaca kalender sejak putaran kedua (bagian 8).

## 4. Rata-rata nasional

Acuan rata-rata nasional adalah **angka nasional yang diterbitkan PIHPS Bank Indonesia**, bukan rata-rata dari 21 provinsi cakupan penelitian. Angka ini disimpan di tabel `harga_nasional`.

`harga_nasional` dan `indikator_harga` tidak memiliki kolom jenis pasar. Keduanya selalu mengacu pada **jenis pasar utama** setiap tingkat harga (`TingkatHarga::jenisPasarUtama()` di Laravel):

| `tingkat_harga` | Jenis pasar utama | Angka nasional PIHPS yang dipakai |
|---|---|---|
| `konsumen` | `tradisional` | Pasar Tradisional (jenis harga 1) |
| `produsen` | `umum` | Produsen (jenis harga 4) |

Harga konsumen dipakai dari pasar tradisional karena hanya jenis harga itu yang tersedia untuk ke-21 provinsi, dengan kelengkapan rata-rata 99,5 persen. Angka nasional pasar modern tidak disimpan. Harga provinsi pasar modern boleh dimuat ke `harga_harian` sebagai pelengkap, tetapi tidak ditampilkan di dasbor.

Indikator di tabel `indikator_harga` dihitung dari baris `harga_harian` bersumber `pihps` pada jenis pasar utama, dengan tanggal, komoditas, dan tingkat harga yang sama:

- `rata_rata_nasional` = harga di `harga_nasional`
- `selisih` = harga provinsi − rata-rata nasional
- `selisih_persen` = selisih ÷ rata-rata nasional × 100, dibulatkan dua desimal

Indikator hanya dibuat untuk tanggal yang memiliki harga provinsi dan harga nasional sekaligus.

## 5. Kontrak data untuk ETL

Skema basis data dimiliki migrasi Laravel. ETL hanya mengisi dan memperbarui baris, tidak membuat atau mengubah tabel.

- **Jangan memakai `pandas.to_sql(if_exists='replace')`.** Perintah itu menghapus dan membuat ulang tabel, sehingga unique key, index, dan foreign key hilang. Pakai `if_exists='append'` untuk tabel kosong, atau upsert dengan `INSERT ... ON DUPLICATE KEY UPDATE`.
- **Format tanggal selalu `YYYY-MM-DD`**, tanpa jam.
- **Data hanya untuk hari kerja.** PIHPS tidak mencatat harga pada Sabtu dan Minggu, sehingga tidak ada baris akhir pekan. Dasbor tidak menampilkan akhir pekan pada deret harian, sehingga akhir pekan tidak tampil sebagai celah. Tanggal prediksi juga hanya hari kerja.
- **Tanggal tanpa data tidak diberi baris.** Jangan mengisi nol atau menyalin harga hari sebelumnya.
- **Harga dalam rupiah dengan dua desimal**, sudah dalam satuan baku komoditasnya.
- **Wilayah dicocokkan lewat `provinsi.kode`**, yaitu kode provinsi BPS dua digit, bukan lewat nama.
- **Komoditas dicocokkan lewat `komoditas.kode`**: `beras-medium`, `beras-premium`, `minyak-goreng-kemasan`, `minyak-goreng-curah`.
- **Prediksi selalu untuk harga konsumen di pasar tradisional.** Tabel `prediksi` tidak memiliki kolom tingkat harga maupun jenis pasar, sehingga layanan Python hanya menulis prediksi untuk deret itu.
- **Harga produsen hanya untuk beras.** Minyak goreng hanya dicatat di tingkat konsumen.

Nilai kolom kategori yang diterima:

| Kolom | Nilai |
|---|---|
| `tingkat_harga` | `konsumen`, `produsen` |
| `jenis_pasar` | `umum`, `tradisional`, `modern` |
| `sumber` | `pihps` |
| `model` | `random_forest`, `xgboost`, `naive` |

Kunci upsert setiap tabel:

| Tabel | Kunci unik |
|---|---|
| `harga_harian` | tanggal, provinsi_id, komoditas_id, tingkat_harga, jenis_pasar, sumber |
| `harga_nasional` | komoditas_id, tingkat_harga, tanggal |
| `indikator_harga` | tanggal, provinsi_id, komoditas_id, tingkat_harga |
| `prediksi` | provinsi_id, komoditas_id, model, tanggal_sasaran (tidak di-upsert, lihat bagian 8; juga cadangan layanan API model, lihat bagian 9) |
| `metrik_model` | komoditas_id, model |

## 6. Data tiruan

`DataTiruanSeeder` mengisi tabel fakta dengan data buatan agar dasbor dapat dikembangkan sebelum ETL selesai.

- **Data tiruan tidak boleh dilaporkan sebagai hasil penelitian.** Angka harga, prediksi, dan metrik di dalamnya dibuat oleh program, bukan diukur.
- Jalankan dengan `php artisan db:seed --class=DataTiruanSeeder`. Seeder menolak berjalan di lingkungan production dan menolak berjalan kalau tabel fakta atau `riwayat_muat` sudah berisi data.
- Sebelum mengisi data, seeder menulis baris `riwayat_muat` bersumber `tiruan`. Selama baris itu ada, ETL menolak memuat data asli (kode keluar 2), sehingga data asli dan tiruan tidak pernah tercampur dalam satu basis data.
- Sebelum memuat data asli, kosongkan basis data dengan `php artisan migrate:fresh --seed`. Perintah ini menghapus seluruh isi basis data lokal, termasuk penanda tiruan, lalu mengisi ulang data acuan.
- Data tiruan bersifat deterministik, sehingga setiap anggota tim mendapat angka yang sama.
- Bentuknya meniru PIHPS: hanya hari kerja, seluruh harga per kilogram dengan tingkat harga setara rata-rata PIHPS Januari 2024, sumber `pihps`, harga konsumen di pasar tradisional, dan harga produsen di pasar umum untuk beras saja.
- Basis data yang dibuat sebelum 26 Oktober 2026 berisi data tiruan bersumber `bapanas` dan satuan `liter`, dua nilai yang tidak lagi dikenali aplikasi. Bangun ulang dengan `php artisan migrate:fresh --seed`, lalu `php artisan db:seed --class=DataTiruanSeeder`.
- Celah data sengaja dibuat agar tampilan celah di grafik dapat diuji. Rinciannya ada di PHPDoc `database/seeders/DataTiruanSeeder.php`.

## 7. Sumber data dan status pengambilan

Pipeline pengambilan data ada di folder `analitik/` (Python), di samping folder `capstone/`. Alurnya dua tahap: **unduh** menyimpan respons sumber apa adanya ke arsip JSON, lalu **muat** membaca arsip dan menulis ke basis data. Pemuatan ulang tidak perlu mengunduh ulang, dan respons asli selalu bisa diperiksa.

### 7.1 Panel Harga Pangan Bapanas (tidak dipakai)

Tim memutuskan pada 26 Oktober 2026 untuk tidak memakai Panel Harga Pangan Bapanas. Sejak pemeriksaan Oktober 2026, seluruh endpoint `api-panelhargav2.badanpangan.go.id/api/front/*` membalas `401 Unauthorized. Invalid or missing API key.`, termasuk permintaan dari situs resmi Bapanas sendiri. Kunci yang tertanam di kode JavaScript situs tidak dipakai, karena memakainya berarti melewati kontrol akses yang sengaja dipasang pemilik data. PIHPS Bank Indonesia kini menjadi satu-satunya sumber.

### 7.2 PIHPS Bank Indonesia

PIHPS terbuka tanpa kunci. Respons berupa tabel lebar, satu kolom per tanggal, dan hanya berisi **hari kerja**. Ada dua bentuk tabel dengan angka yang sama (diperiksa: 1.851 nilai yang tumpang tindih identik):

| Perintah | Endpoint | Isi satu permintaan | Lama |
|---|---|---|---|
| `python -m etl unduh pihps` | `GetGridDataDaerah` | satu wilayah, 4 komoditas, satu tahun | sekitar 35 menit untuk riwayat 2024 sampai sekarang (198 permintaan), cukup sekali |
| `python -m etl perbarui pihps` | `GetGridDataKomoditas` | satu komoditas, seluruh provinsi dan angka nasional | sekitar 1 menit (12 permintaan) |

Setiap permintaan ke PIHPS memakan beberapa detik walaupun rentangnya pendek, jadi yang menentukan lama pengunduhan adalah jumlah permintaan. Pembaruan rutin memakai tabel per komoditas karena 12 permintaan sudah mencakup 21 provinsi. Untuk riwayat panjang, tabel per komoditas tidak lebih cepat (sekitar 50 detik per komoditas per tahun).

Provinsi yang tidak punya data pada suatu rentang tidak muncul sebagai baris di kedua bentuk tabel. Contohnya, harga produsen DKI Jakarta dan pasar modern DI Yogyakarta memang kosong.

PIHPS tidak memakai golongan medium dan premium, melainkan sub-kualitas I dan II. Padanan yang dipakai:

| Komoditas PIHPS | ID | `komoditas.kode` | Satuan PIHPS |
|---|---|---|---|
| Beras Kualitas Medium I | com_3 | `beras-medium` | kg |
| Beras Kualitas Super I | com_5 | `beras-premium` | kg |
| Minyak Goreng Curah | com_17 | `minyak-goreng-curah` | kg |
| Minyak Goreng Kemasan Bermerk 1 | com_18 | `minyak-goreng-kemasan` | kg |

Sub-kualitas I dan II tidak dirata-ratakan, karena hasilnya angka yang tidak diterbitkan sumber.

| Jenis harga PIHPS | ID | `tingkat_harga` | `jenis_pasar` |
|---|---|---|---|
| Pasar Tradisional | 1 | `konsumen` | `tradisional` |
| Pasar Modern | 2 | `konsumen` | `modern` |
| Produsen | 4 | `produsen` | `umum` |

Pedagang Besar (ID 3) tidak dipakai karena bukan tingkat konsumen maupun produsen.

Aturan pemuatan PIHPS ke `harga_harian` (sumber `pihps`) yang berlaku saat ini:

- Beras dan minyak goreng dimuat per kilogram, untuk pasar tradisional, pasar modern, dan produsen.
- Harga yang satuannya berbeda dari `komoditas.satuan` dilewati. Alasan di laporan ETL: `satuan_{sumber}_bukan_{tujuan}`, misalnya `satuan_kg_bukan_liter`.
- Harga produsen hanya untuk beras (bagian 5).
- Angka nasional dimuat ke `harga_nasional` hanya untuk jenis pasar utama (bagian 4). Angka nasional pasar modern dilewati dengan alasan `harga_nasional_selain_jenis_pasar_utama`. Setelah itu `indikator_harga` dihitung ulang dari isi basis data dalam transaksi yang sama.

Sebelum setiap pengunduhan, pemetaan provinsi, komoditas, jenis harga, dan satuan dicocokkan dengan daftar referensi PIHPS. Kalau ada yang berubah di sisi sumber, pengunduhan dihentikan.

### 7.3 Arsip mentah

- Lokasi tabel per daerah: `analitik/data/mentah/pihps/jenis-{id}/{wilayah}/{mulai}_{selesai}.json`, satu berkas per jenis harga, wilayah, dan tahun kalender.
- Lokasi tabel per komoditas: `analitik/data/mentah/pihps/jenis-{id}/semua/{id_komoditas}/{mulai}_{selesai}.json`.
- Folder `data/` tidak masuk git.
- Angka 14 hari terakhir sebelum tanggal unduh dianggap belum final, karena sumber masih dapat menambah atau merevisi angka hari-hari terakhir. `perbarui` mengunduh mulai dari hari pertama yang belum final untuk provinsi dan komoditas mana pun di arsip, sehingga celah tetap tertutup walaupun pembaruan lama tidak dijalankan.
- Kalau dua berkas mencakup tanggal yang sama, angka dari unduhan terbaru yang dimuat.

### 7.4 Riwayat pemuatan

Setiap pemuatan menulis satu baris di `riwayat_muat` sebelum mulai (status `berjalan`), lalu memperbaruinya menjadi `berhasil` atau `gagal` beserta jumlah baris dimuat, jumlah dilewati per alasan, dan rentang tanggal data. Baris ditulis lebih dahulu agar tetap tercatat walaupun pemuatan gagal di tengah jalan.

## 8. Pemodelan prediksi

Pemodelan ada di paket `analitik/prediksi/` dan dijalankan dari folder `analitik` setelah data dimuat:

```
.venv\Scripts\python -m prediksi latih --database capstone_pihps
.venv\Scripts\python -m prediksi latih --database capstone_pihps --komoditas beras-medium
```

Satu komoditas memakan sekitar 2 sampai 5 menit, dan keempat komoditas sekitar 13 menit. Kode keluar sama dengan ETL: 0 berhasil, 1 bila ada komoditas yang gagal (komoditas lain tetap diproses), 2 bila ditolak, misalnya basis data berisi data tiruan.

### 8.1 Data dan rancangan

- Data latih adalah harga konsumen bersumber `pihps` di pasar tradisional dan pasar modern. Validasi, uji, dan prediksi hanya untuk pasar tradisional.
- Satu model global untuk setiap komoditas dan algoritma, dilatih dari seluruh provinsi sekaligus. Sasarannya persentase perubahan harga dari hari asal ke 1 sampai 20 hari kerja kemudian, dengan cakrawala sebagai fitur.
- Setiap algoritma (Random Forest dan XGBoost) dipakai sebagai model dua tahap (`ModelDuaTahap` di `prediksi/latih.py`). Tahap klasifikasi memperkirakan peluang harga pada tanggal sasaran berbeda dari harga hari asal. Tahap besaran adalah model regresi yang dilatih hanya pada baris yang harganya berubah. Prediksi bernilai besaran bila peluang berubah mencapai ambang, dan 0 bila tidak. Rancangan ini dipilih karena 46 sampai 66 persen pasangan pada data latih tidak menunjukkan perubahan harga sama sekali.
- Hari kerja berarti Senin sampai Jumat, termasuk hari libur nasional, karena PIHPS juga mencatat harga pada hari libur nasional yang jatuh pada hari kerja. Diperiksa pada data 2024-01-01 sampai 2026-10-23: 735 hari bertanggal nasional sama dengan 735 hari kerja.
- Daftar fitur ada di `KOLOM_FITUR` (`prediksi/fitur.py`): cakrawala, fitur riwayat (*lag*, rata-rata bergerak, volatilitas), fitur nasional, dan identitas deret (provinsi, pulau, pasar modern). Seluruh fitur hanya memakai data pada atau sebelum hari asal.
- Fitur nasional dibaca dari `harga_nasional` tingkat konsumen, yaitu angka nasional pasar tradisional (bagian 4). Tanggal tanpa angka nasional membuat fitur nasional hari itu kosong (NaN), bukan diisi.
- Data dibagi menurut tanggal sasaran: latih sampai 31 Desember 2025, validasi 1 Januari sampai 30 April 2026, uji mulai 1 Mei 2026. Hiperparameter, ambang, dan model terpilih ditentukan dari skor validasi, yaitu perbaikan terkecil di antara MAPE, RMSE, dan MAE terhadap Naive, dalam persen (`skor_terhadap_naive` di `prediksi/evaluasi.py`). Skor positif berarti ketiga galat lebih kecil daripada Naive. Data uji hanya dipakai sekali untuk metrik akhir.
- Pemodelan tidak lagi membaca tabel `kalender`, sehingga panjang kalender tidak membatasi pemodelan.

### 8.2 Riwayat putaran pemodelan

| Putaran | Rancangan | Dasar pemilihan | Arsip |
|---|---|---|---|
| 1 (28 Oktober 2026) | Regresi tunggal, 16 fitur termasuk bulan, jarak ke Idul Fitri, dan musim panen pada tanggal sasaran | MAPE validasi | `analitik/data/model/putaran-1/`: berkas model, log pelatihan, salinan kode |
| 2 (29 Oktober 2026) | Model dua tahap, fitur kalender dibuang, empat fitur nasional ditambah, kisi Random Forest digeser ke `min_samples_leaf` {100, 300, 1000} × `max_features` {0,33; 0,5} | Skor validasi | Berkas model di `analitik/data/model/2026-10-23/`; log pelatihan serta skrip dan log eksplorasi di `analitik/data/model/putaran-2/` |

- Perubahan putaran 2 dipilih dari eksplorasi yang hanya memakai data validasi. Baris uji dibuang sebelum apa pun dihitung (`putaran-2/eksplorasi_validasi.py` dan `.log`). Satu konfigurasi dipakai untuk keempat komoditas, yaitu konfigurasi dengan rata-rata skor validasi tertinggi (+3,26 persen, positif pada keempat komoditas).
- Hasil uji putaran 1 sudah diketahui sebelum putaran 2 dirancang, sehingga data uji tidak sepenuhnya independen terhadap putaran 2. Hasil uji kedua putaran dilaporkan berdampingan.
- Tim memutuskan jalur umpan balik Tahap 5 ke Tahap 3 dibatasi satu putaran (Tahap 5 laporan). Data uji periode 1 Mei sampai 23 Oktober 2026 tidak dipakai lagi untuk merancang putaran berikutnya.

Hasil uji, periode 1 Mei sampai 23 Oktober 2026, MAPE model terpilih dibandingkan dengan MAPE Naive:

| Komoditas | Putaran 1 | Putaran 2 | Lolos Tabel 3.6 pada putaran 2 |
|---|---|---|---|
| Beras medium | XGBoost 0,4350% (Naive 0,4276%) | XGBoost 0,4264% (Naive 0,4276%) | Ya |
| Beras premium | XGBoost 0,4046% (Naive 0,4017%) | Random Forest 0,3958% (Naive 0,4017%) | Ya |
| Minyak goreng curah | XGBoost 0,7891% (Naive 0,7378%) | Random Forest 0,8229% (Naive 0,7378%) | Tidak |
| Minyak goreng kemasan | XGBoost 0,5403% (Naive 0,4865%) | XGBoost 0,6989% (Naive 0,4865%) | Tidak |

### 8.3 Kontrak tabel

- **`metrik_model`** berisi tiga baris per komoditas (`random_forest`, `xgboost`, `naive`), di-upsert pada kunci uniknya. Metrik dihitung pada harga rupiah per kilogram di pasar tradisional, periode uji, gabungan 21 provinsi dan cakrawala 1 sampai 20 hari kerja (Tabel 3.6 laporan). `r2` adalah R² harga. Nilai dibulatkan empat desimal. `terpilih` bernilai 1 hanya untuk algoritma dengan skor validasi tertinggi, walaupun algoritma itu tidak lolos kriteria. Dasbor menilai ulang kriteria dari metrik ini dan menampilkan garis dasar Naive bila model terpilih tidak lolos.
- **`prediksi`** berisi prediksi ketiga model untuk 20 hari kerja sesudah tanggal data terakhir. Hanya provinsi yang memiliki harga pasar tradisional pada tanggal data terakhir yang diberi prediksi, agar seluruh provinsi berangkat dari hari asal yang sama. Prediksi Naive sama dengan harga terakhir.
- Tabel `prediksi` **tidak di-upsert**. Setiap pelatihan menghapus seluruh baris satu komoditas lalu menyisipkan baris baru, dalam satu transaksi bersama upsert `metrik_model`. Dengan upsert, tanggal sasaran pelatihan sebelumnya yang tidak lagi diprediksi akan tertinggal. Karena satu transaksi, dasbor tidak pernah membaca prediksi baru dengan metrik lama atau sebaliknya. Komoditas lain tidak tersentuh.
- `dihasilkan_pada` sama untuk seluruh baris satu komoditas dalam satu pelatihan.
- Pemodelan menolak berjalan bila basis data berisi penanda data tiruan (bagian 6), sama dengan ETL.

### 8.4 Berkas model

Model akhir, yang dilatih ulang pada seluruh data, disimpan di `analitik/data/model/{tanggal data terakhir}/{komoditas}/`:

| Berkas | Isi |
|---|---|
| `random_forest_klasifikasi.joblib` | `RandomForestClassifier` scikit-learn untuk tahap klasifikasi, disimpan dengan joblib |
| `random_forest_besaran.joblib` | `RandomForestRegressor` scikit-learn untuk tahap besaran, disimpan dengan joblib |
| `xgboost_klasifikasi.json` | `XGBClassifier` untuk tahap klasifikasi, disimpan dengan `save_model` |
| `xgboost_besaran.json` | `XGBRegressor` untuk tahap besaran, disimpan dengan `save_model` |
| `laporan.json` | putaran pemodelan, metode, ambang setiap algoritma, parameter terpilih, skor dan metrik validasi setiap kandidat, metrik validasi Naive, metrik uji keseluruhan dan per cakrawala, persentase pasangan uji yang berubah, penilaian Tabel 3.6, jumlah baris, daftar fitur, dan versi pustaka |

Keempat model menerima matriks fitur dengan urutan kolom `KOLOM_FITUR`. Cara memakainya: peluang berubah = kolom kedua `predict_proba` model klasifikasi; perubahan = `predict` model besaran bila peluang berubah mencapai `ambang` di `laporan.json`, dan 0 bila tidak; harga prediksi = harga hari asal × (1 + perubahan / 100). Berkas joblib hanya dapat dibaca dengan versi scikit-learn yang sama (lihat `versi` di `laporan.json`), dan hanya boleh dibuka dari sumber tepercaya karena joblib memakai pickle. Folder `data/` tidak masuk git.

## 9. Layanan REST API model

Layanan ada di paket `analitik/layanan/` (FastAPI 0.162.0 dan Uvicorn 0.54.0) dan dijalankan dari folder `analitik`:

```
.venv\Scripts\python -m layanan --database capstone_pihps
.venv\Scripts\python -m layanan --database capstone_pihps --port 8100
```

Bawaannya mendengarkan `127.0.0.1:8100`. Saat dinyalakan, layanan memuat seluruh model dari `analitik/data/model/`. Dokumentasi interaktif ada di `http://127.0.0.1:8100/docs`, tetapi halaman itu memuat aset Swagger UI dari internet. Spesifikasi OpenAPI di `http://127.0.0.1:8100/openapi.json` tetap tersedia tanpa internet dan dapat diimpor langsung ke Postman.

### 9.1 Endpoint

| Endpoint | Parameter | Isi |
|---|---|---|
| `GET /kesehatan` | tidak ada | Status layanan, nama basis data, dan status model setiap komoditas (`null` bila belum ada model) |
| `GET /v1/prakiraan` | `komoditas`, `provinsi` (kode BPS) | Hari asal, harga asal, tanggal model, putaran, model terpilih, waktu perhitungan, dan prakiraan 20 hari kerja untuk `naive`, `random_forest`, dan `xgboost` |
| `GET /v1/metrik` | `komoditas` | Periode uji, model terpilih, ambang, metrik uji, penilaian Tabel 3.6, dan metrik per cakrawala dari `laporan.json` |

Galat memakai bentuk yang sama dengan API Laravel, yaitu `{"message": ..., "errors": {...}}`, dengan pesan berbahasa Indonesia:

| Kode | Keadaan |
|---|---|
| 422 | Parameter kosong, komoditas tidak dikenal, atau provinsi tidak dikenal |
| 404 | Belum ada model untuk komoditas itu, atau provinsi tidak memiliki harga pasar tradisional pada tanggal data terakhir |
| 503 | Basis data tidak dapat dihubungi, basis data berisi data tiruan, atau berkas model tidak cocok dengan kode saat ini |

### 9.2 Aturan perhitungan

- Prakiraan dihitung dengan fungsi yang sama dengan pelatihan (`prediksi.latih.prakiraan`), dari harga dan angka nasional di basis data serta berkas model terbaru. Pada data 23 Oktober 2026, seluruh 83 kombinasi komoditas dan provinsi yang memiliki data menghasilkan angka yang sama persis dengan tabel `prediksi`.
- Folder model terbaru adalah subfolder bertanggal (TTTT-BB-HH) terbaru yang berisi komoditas itu. Folder arsip seperti `putaran-1` tidak pernah dipakai.
- Model ditolak bila daftar fiturnya, nama berkasnya, atau versi scikit-learn dan XGBoost berbeda dari saat pelatihan.
- Model dan prakiraan disimpan sementara di memori. Prakiraan dihitung ulang bila jumlah atau tanggal terakhir harga, angka nasional, atau pemuatan ETL terakhir yang berhasil berubah. Perubahan harga langsung di basis data di luar ETL baru terbaca setelah layanan dijalankan ulang.
- Layanan hanya membaca data dan tidak memakai autentikasi. Karena itu layanan hanya boleh mendengarkan alamat lokal. Bila `--host` diisi alamat lain, layanan menampilkan peringatan.

### 9.3 Pemakaian di Laravel

`PrediksiController` meminta prakiraan ke `GET /v1/prakiraan` lebih dahulu. Pengaturannya ada di `config/services.php` bagian `layanan_model`:

| Variabel `.env` | Bawaan | Arti |
|---|---|---|
| `LAYANAN_MODEL_URL` | kosong | Alamat layanan, misalnya `http://127.0.0.1:8100`. Kosong berarti layanan tidak dipanggil |
| `LAYANAN_MODEL_BATAS_WAKTU_SAMBUNG` | 1 | Batas waktu tersambung, dalam detik |
| `LAYANAN_MODEL_BATAS_WAKTU` | 5 | Batas waktu jawaban, dalam detik |
| `LAYANAN_MODEL_JEDA_SETELAH_GAGAL` | 30 | Setelah gagal tersambung, layanan tidak dicoba lagi selama sekian detik |

Jawaban layanan hanya dipakai bila keempat syarat berikut terpenuhi:
1. Layanan membalas dengan kode 2xx.
2. Bentuk jawabannya sesuai kontrak di atas.
3. Ketiga model ada di jawaban.
4. Tanggal dan harga asalnya sama dengan harga pasar tradisional terakhir di basis data Laravel.

Syarat terakhir mencegah dasbor berdata tiruan menampilkan prakiraan dari layanan yang membaca data asli. Bila salah satu syarat gagal, prakiraan dibaca dari tabel `prediksi` dan alasannya dicatat di log Laravel. Respons API `/api/v1/prediksi` menyebut sumbernya di kolom `sumber_prakiraan`, yaitu `layanan_model` atau `basis_data`. Pemilihan model yang ditampilkan tetap dilakukan Laravel dari `metrik_model`, sehingga layanan dan cadangan selalu menampilkan model yang sama.
