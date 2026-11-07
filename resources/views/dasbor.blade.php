@php
    use Illuminate\Support\Carbon;

    $formatTanggal = fn (?string $tanggal): string => $tanggal === null
        ? 'belum ada data'
        : Carbon::parse($tanggal)->locale('id')->translatedFormat('j F Y');

    $provinsiTrenAwal = ['31', '35', '65'];

    $kartu = [
        ['id' => 'nasional', 'judul' => 'Harga rata-rata nasional'],
        ['id' => 'perubahan', 'judul' => 'Perubahan 30 hari'],
        ['id' => 'termurah', 'judul' => 'Provinsi termurah'],
        ['id' => 'termahal', 'judul' => 'Provinsi termahal'],
        ['id' => 'rentang', 'judul' => 'Rentang termurah–termahal'],
    ];
@endphp

<x-layouts.app judul="Dasbor Harga Beras dan Minyak Goreng">
    <header class="bg-merek-700 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-4 px-4 py-5 sm:px-6">
            <div>
                <p class="text-sm font-medium text-merek-100">Platform Business Intelligence Harga Pangan</p>
                <h1 class="mt-1 text-xl font-semibold sm:text-2xl">Harga Beras dan Minyak Goreng</h1>
                <p class="mt-1 text-sm text-merek-100">Pulau Jawa, Kalimantan, dan Sumatra · 21 provinsi</p>
            </div>
            <p class="text-sm text-merek-100">
                Data terakhir: <span class="font-semibold text-white">{{ $formatTanggal($tanggalAkhir) }}</span>
            </p>
        </div>
    </header>

    @if ($dataTiruan)
        <div role="alert" class="border-b border-amber-300 bg-amber-50">
            <p class="mx-auto max-w-7xl px-4 py-3 text-sm text-amber-900 sm:px-6">
                <strong class="font-semibold">Data tiruan.</strong>
                Seluruh angka di halaman ini dibuat oleh program untuk keperluan pengembangan, bukan data harga sebenarnya.
                Jangan mengutip angka atau tangkapan layar halaman ini sebagai hasil penelitian.
            </p>
        </div>
    @endif

    <div class="border-b border-slate-200 bg-white/95 backdrop-blur md:sticky md:top-0 md:z-30">
        <div class="mx-auto flex max-w-7xl flex-wrap items-end gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
            <div class="flex flex-col gap-1">
                <label for="filter-komoditas" class="text-xs font-medium text-slate-600">Komoditas</label>
                <select id="filter-komoditas" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    @foreach ($komoditasPerKelompok as $kelompok => $daftar)
                        <optgroup label="{{ $kelompok }}">
                            @foreach ($daftar as $satuKomoditas)
                                <option value="{{ $satuKomoditas->kode }}" @selected($satuKomoditas->is($komoditasAwal))>
                                    {{ $satuKomoditas->nama }} (per {{ $satuKomoditas->satuan->label() }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-tingkat" class="text-xs font-medium text-slate-600">Tingkat harga</label>
                <select id="filter-tingkat" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="konsumen" selected>Konsumen (pasar tradisional)</option>
                    <option value="produsen">Produsen (hanya beras)</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-pulau" class="text-xs font-medium text-slate-600">Cakupan wilayah</label>
                <select id="filter-pulau" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua pulau</option>
                    @foreach ($pulau as $satuPulau)
                        <option value="{{ $satuPulau->value }}">{{ $satuPulau->label() }}</option>
                    @endforeach
                </select>
            </div>

            <nav aria-label="Bagian dasbor" class="ml-auto">
                <ul class="flex flex-wrap gap-1 text-sm">
                    @foreach (['ringkasan' => 'Ringkasan', 'tren' => 'Tren', 'perbandingan' => 'Perbandingan', 'prediksi' => 'Prediksi', 'tentang-data' => 'Tentang data'] as $jangkar => $nama)
                        <li>
                            <a href="#{{ $jangkar }}" class="block rounded-md px-3 py-2 font-medium text-slate-600 hover:bg-merek-50 hover:text-merek-700">{{ $nama }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </div>

    <main id="isi-utama" class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6">
        <x-panel id="ringkasan" judul="Ringkasan" keterangan="Keadaan harga pada tanggal data terakhir, dibandingkan dengan rata-rata nasional PIHPS Bank Indonesia.">
            <p id="ringkasan-status" role="status" aria-live="polite" class="mb-4 text-sm text-slate-600 empty:hidden"></p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($kartu as $satuKartu)
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $satuKartu['judul'] }}</p>
                        <p id="kpi-{{ $satuKartu['id'] }}" class="mt-2 text-xl font-semibold text-slate-900">–</p>
                        <p id="kpi-{{ $satuKartu['id'] }}-sub" class="mt-1 text-sm text-slate-600">&nbsp;</p>
                    </div>
                @endforeach
            </div>
        </x-panel>

        <x-panel id="tren" judul="Tren harga" keterangan="Garis putus-putus abu-abu adalah rata-rata nasional. Garis yang terputus menandakan hari kerja tanpa data, bukan harga nol.">
            <x-slot:aksi>
                <div class="flex flex-col gap-1">
                    <label for="tren-rentang" class="text-xs font-medium text-slate-600">Periode</label>
                    <select id="tren-rentang" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="90">90 hari terakhir</option>
                        <option value="180" selected>180 hari terakhir</option>
                        <option value="365">1 tahun terakhir</option>
                        <option value="semua">Seluruh periode</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="tren-agregasi" class="text-xs font-medium text-slate-600">Tampilkan per</label>
                    <select id="tren-agregasi" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="harian" selected>Hari</option>
                        <option value="mingguan">Minggu</option>
                        <option value="bulanan">Bulan</option>
                    </select>
                </div>
            </x-slot:aksi>

            <details id="tren-pemilih" class="mb-4 rounded-md border border-slate-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-slate-700">
                    Provinsi yang dibandingkan: <span id="tren-jumlah-provinsi">0</span> dari maksimum {{ $maksProvinsi }}
                </summary>
                <fieldset class="grid grid-cols-1 gap-4 border-t border-slate-200 px-4 py-3 sm:grid-cols-3">
                    <legend class="sr-only">Pilih paling banyak {{ $maksProvinsi }} provinsi</legend>
                    @foreach ($provinsiPerPulau as $namaPulau => $daftar)
                        <div data-kelompok-pulau="{{ $daftar->first()->pulau->value }}">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $namaPulau }}</p>
                            <ul class="flex flex-col">
                                @foreach ($daftar as $satuProvinsi)
                                    <li>
                                        <label class="flex items-center gap-2 py-1 text-sm text-slate-700">
                                            <input type="checkbox" name="tren-provinsi" value="{{ $satuProvinsi->kode }}"
                                                data-pulau="{{ $satuProvinsi->pulau->value }}"
                                                class="size-4 rounded border-slate-300 text-merek-600"
                                                @checked(in_array($satuProvinsi->kode, $provinsiTrenAwal, true))>
                                            {{ $satuProvinsi->nama }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </fieldset>
            </details>

            <p id="tren-status" role="status" aria-live="polite" class="mb-2 text-sm text-slate-600 empty:hidden"></p>
            <div id="tren-grafik" class="min-h-80" role="img" aria-label="Grafik tren harga. Rincian angka tersedia pada tabel di bawah grafik."></div>
            <p id="tren-penanda" class="mt-3 text-sm text-slate-600 empty:hidden"></p>
            <ul id="tren-celah" class="mt-3 flex flex-col gap-1 text-sm text-slate-600 empty:hidden"></ul>

            <details id="tren-rincian" class="mt-4 rounded-md border border-slate-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-slate-700">Lihat angka dalam tabel</summary>
                <div class="max-h-96 overflow-auto border-t border-slate-200">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Harga per periode untuk rata-rata nasional dan provinsi terpilih</caption>
                        <thead id="tren-tabel-kepala" class="sticky top-0 bg-slate-100 text-xs uppercase text-slate-600"></thead>
                        <tbody id="tren-tabel-isi" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </details>
        </x-panel>

        <x-panel id="perbandingan" judul="Perbandingan antarwilayah" keterangan="Batang menunjukkan selisih harga setiap provinsi terhadap rata-rata nasional, diurutkan dari yang termurah. Hijau berarti di bawah rata-rata nasional, jingga berarti di atasnya.">
            <x-slot:aksi>
                <div class="flex flex-col gap-1">
                    <label for="perbandingan-tanggal" class="text-xs font-medium text-slate-600">Tanggal</label>
                    <input type="date" id="perbandingan-tanggal" value="{{ $tanggalAkhir }}" min="{{ $tanggalAwal }}" max="{{ $tanggalAkhir }}"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
            </x-slot:aksi>

            <p id="perbandingan-status" role="status" aria-live="polite" class="mb-2 text-sm text-slate-600 empty:hidden"></p>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div id="perbandingan-grafik" class="min-h-96 lg:col-span-3" role="img" aria-label="Grafik batang selisih harga setiap provinsi terhadap rata-rata nasional. Rincian angka tersedia pada tabel."></div>

                <div class="overflow-x-auto lg:col-span-2">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Harga dan selisih terhadap rata-rata nasional per provinsi</caption>
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th scope="col" class="px-3 py-2">#</th>
                                <th scope="col" class="px-3 py-2">Provinsi</th>
                                <th scope="col" class="px-3 py-2 text-right">Harga</th>
                                <th scope="col" class="px-3 py-2 text-right">Selisih</th>
                                <th scope="col" class="px-3 py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody id="perbandingan-tabel" class="divide-y divide-slate-100"></tbody>
                    </table>
                    <p id="perbandingan-tanpa-data" class="mt-3 text-sm text-slate-600 empty:hidden"></p>
                </div>
            </div>
        </x-panel>

        <x-panel id="prediksi" judul="Prediksi harga" keterangan="Prediksi harga konsumen di pasar tradisional. Area berwarna adalah periode prakiraan.">
            <x-slot:aksi>
                <div class="flex flex-col gap-1">
                    <label for="prediksi-provinsi" class="text-xs font-medium text-slate-600">Provinsi</label>
                    <select id="prediksi-provinsi" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        @foreach ($provinsiPerPulau as $namaPulau => $daftar)
                            <optgroup label="{{ $namaPulau }}">
                                @foreach ($daftar as $satuProvinsi)
                                    <option value="{{ $satuProvinsi->kode }}" @selected($satuProvinsi->is($provinsiAwal))>{{ $satuProvinsi->nama }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </x-slot:aksi>

            <p id="prediksi-status" role="status" aria-live="polite" class="mb-2 text-sm text-slate-600 empty:hidden"></p>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div id="prediksi-grafik" class="min-h-80 lg:col-span-2" role="img" aria-label="Grafik harga historis dan prediksi."></div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-md border border-merek-100 bg-merek-50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-merek-700">Model yang dipakai</p>
                        <p id="prediksi-model" class="mt-1 text-lg font-semibold text-slate-900">–</p>
                        <p id="prediksi-ringkas" class="mt-2 text-sm text-slate-700"></p>
                    </div>

                    <p class="text-sm text-slate-600">
                        Prakiraan bukan kepastian. Gunakan sebagai bahan pertimbangan, bukan sebagai satu-satunya dasar keputusan.
                    </p>
                </div>
            </div>

            <details class="mt-6 rounded-md border border-slate-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-slate-700">Metrik evaluasi model</summary>
                <div class="overflow-x-auto border-t border-slate-200">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <caption class="px-3 py-2 text-left text-xs text-slate-600">
                            Kriteria penerimaan: MAPE di bawah {{ number_format($kriteria['mape_maks'], 0, ',', '.') }}%, R² minimal {{ number_format($kriteria['r2_min'], 2, ',', '.') }}, dan galat lebih kecil daripada garis dasar Naive. Metrik dihitung pada periode uji dari seluruh provinsi sekaligus, untuk prakiraan 1 sampai 20 hari kerja ke depan.
                        </caption>
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th scope="col" class="px-2 py-2 sm:px-3">Model</th>
                                <th scope="col" class="px-2 py-2 text-right sm:px-3">MAPE</th>
                                <th scope="col" class="px-2 py-2 text-right sm:px-3">RMSE</th>
                                <th scope="col" class="px-2 py-2 text-right sm:px-3">MAE</th>
                                <th scope="col" class="px-2 py-2 text-right sm:px-3">R²</th>
                                <th scope="col" class="hidden px-3 py-2 sm:table-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody id="prediksi-metrik" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </details>
        </x-panel>

        <x-panel id="tentang-data" judul="Tentang data">
            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-slate-900">Sumber</dt>
                    <dd class="mt-1 text-slate-600">Pusat Informasi Harga Pangan Strategis Nasional (PIHPS) Bank Indonesia. Harga konsumen diambil dari pasar tradisional, sedangkan harga produsen hanya tersedia untuk beras.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-900">Periode</dt>
                    <dd class="mt-1 text-slate-600">{{ $formatTanggal($tanggalAwal) }} sampai {{ $formatTanggal($tanggalAkhir) }}. PIHPS mencatat harga setiap hari kerja.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-900">Satuan dan varian</dt>
                    <dd class="mt-1 text-slate-600">Seluruh harga dalam rupiah per kilogram sebagaimana diterbitkan PIHPS, termasuk minyak goreng, tanpa konversi. Beras medium dan premium memakai kualitas Medium I dan Super I, minyak goreng kemasan memakai Kemasan Bermerk 1.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-900">Rata-rata nasional</dt>
                    <dd class="mt-1 text-slate-600">Angka nasional yang diterbitkan PIHPS untuk jenis harga yang sama, bukan rata-rata dari 21 provinsi di dasbor ini.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-900">Data kosong</dt>
                    <dd class="mt-1 text-slate-600">Sabtu dan Minggu tidak ditampilkan karena PIHPS tidak mencatat harga pada hari itu. Hari kerja tanpa data dibiarkan kosong dan tampil sebagai garis yang terputus, tidak diisi dengan perkiraan.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-900">Di luar lingkup</dt>
                    <dd class="mt-1 text-slate-600">Dasbor ini tidak menjelaskan sebab perbedaan harga, tidak mengelompokkan wilayah, dan tidak menyajikan faktor yang memengaruhi prediksi.</dd>
                </div>
            </dl>
        </x-panel>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-slate-500 sm:px-6">
            Capstone Project · Platform Business Intelligence Harga Beras dan Minyak Goreng
        </p>
    </footer>

    <script type="application/json" id="konfigurasi-dasbor">@json($konfigurasi)</script>
</x-layouts.app>
