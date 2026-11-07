import { ambil, dibatalkan } from './api.js';
import { buat, cari, kosongkan, sel, status, tulis } from './dom.js';
import * as format from './format.js';
import { gambar, WARNA } from './grafik.js';

const SATUAN_PERIODE = { harian: 'hari kerja', mingguan: 'minggu', bulanan: 'bulan' };

const TEKS_PANEN = 'Musim panen';

/** Tinggi satu baris label (px), banyak baris paling banyak, dan jarak label dari tepi atas area plot. */
const TINGGI_BARIS_LABEL = 15;
const MAKS_BARIS_LABEL = 3;
const OFFSET_LABEL = 12;
/** Perkiraan lebar huruf pada font 11px, jarak antar label, lebar sumbu y di kiri, dan ruang kosong di kanan area plot. */
const LEBAR_HURUF = 6;
const JARAK_LABEL = 8;
const LEBAR_SUMBU_Y = 64;
const JARAK_KANAN = 10;

let grafik = null;
let dataTerakhir = null;

function kotakProvinsi() {
    return [...document.querySelectorAll('input[name="tren-provinsi"]')];
}

/**
 * Provinsi di luar pulau terpilih disembunyikan dan dilepas, lalu pilihan
 * dikunci ketika batas maksimum tercapai.
 */
export function sesuaikanPemilih(konfigurasi, pulau) {
    for (const kelompok of document.querySelectorAll('[data-kelompok-pulau]')) {
        kelompok.hidden = pulau !== '' && kelompok.dataset.kelompokPulau !== pulau;
    }

    for (const kotak of kotakProvinsi()) {
        if (pulau !== '' && kotak.dataset.pulau !== pulau) {
            kotak.checked = false;
        }
    }

    kunciBatas(konfigurasi);
}

export function kunciBatas(konfigurasi) {
    const terpilih = kotakProvinsi().filter((kotak) => kotak.checked).length;

    for (const kotak of kotakProvinsi()) {
        kotak.disabled = !kotak.checked && terpilih >= konfigurasi.maksProvinsi;
    }

    tulis('tren-jumlah-provinsi', String(terpilih));
}

function rentangTanggal(konfigurasi) {
    const pilihan = cari('tren-rentang').value;
    const akhir = konfigurasi.tanggalAkhir;

    if (!akhir) {
        return {};
    }

    if (pilihan === 'semua') {
        return { mulai: konfigurasi.tanggalAwal, selesai: akhir };
    }

    return { mulai: format.tambahHari(akhir, 1 - Number(pilihan)), selesai: akhir };
}

/**
 * Label anotasi diletakkan di atas area plot, bukan di dalamnya, sehingga
 * tidak pernah menimpa garis harga berapa pun datanya. Label yang berdekatan
 * dipindah ke baris di atasnya. Label yang tidak muat di baris mana pun tidak
 * digambar di grafik; namanya tetap tertulis pada keterangan di bawah grafik.
 *
 * Lebar label hanya diperkirakan dari jumlah huruf, karena posisi sebenarnya
 * baru diketahui setelah grafik digambar.
 *
 * @returns {Map<string, {baris: number, diKanan: boolean}>} letak tiap label, kunci tanpa letak berarti tidak digambar
 */
function letakkanLabel(data) {
    const letak = new Map();

    if (data.tanggal.length < 2) {
        return letak;
    }

    const awal = format.keWaktu(data.tanggal[0]);
    const akhir = format.keWaktu(data.tanggal[data.tanggal.length - 1]);
    const lebarGrafik = cari('tren-grafik').clientWidth;
    const lebarPlot = lebarGrafik - LEBAR_SUMBU_Y - JARAK_KANAN;

    const butir = [
        ...data.musim_panen.map((satu, i) => ({ kunci: `panen-${i}`, teks: TEKS_PANEN, waktu: format.keWaktu(satu.mulai) })),
        ...data.penanda.map((satu, i) => ({ kunci: `penanda-${i}`, teks: satu.nama, waktu: format.keWaktu(satu.tanggal) })),
    ].sort((a, b) => a.waktu - b.waktu);

    const ujungBaris = Array(MAKS_BARIS_LABEL).fill(-Infinity);

    for (const satu of butir) {
        const lebar = satu.teks.length * LEBAR_HURUF + JARAK_LABEL;
        const x = LEBAR_SUMBU_Y + ((satu.waktu - awal) / Math.max(akhir - awal, 1)) * lebarPlot;
        const diKanan = x + lebar > lebarGrafik;
        const kiri = diKanan ? x - lebar : x;
        const baris = ujungBaris.findIndex((ujung) => kiri >= ujung + JARAK_LABEL);

        if (baris !== -1) {
            ujungBaris[baris] = kiri + lebar;
            letak.set(satu.kunci, { baris, diKanan });
        }
    }

    return letak;
}

function labelDiAtasPlot(teks, warna, { baris, diKanan }) {
    return {
        text: teks,
        position: 'top',
        orientation: 'horizontal',
        textAnchor: diKanan ? 'end' : 'start',
        offsetX: diKanan ? -4 : 4,
        offsetY: -(baris * TINGGI_BARIS_LABEL + OFFSET_LABEL),
        borderColor: 'transparent',
        style: { background: 'transparent', color: warna, fontSize: '11px' },
    };
}

function anotasi(data) {
    const letak = letakkanLabel(data);

    const penanda = data.penanda.map((satu, i) => ({
        x: format.keWaktu(satu.tanggal),
        borderColor: WARNA.penanda,
        strokeDashArray: 3,
        ...(letak.has(`penanda-${i}`) && { label: labelDiAtasPlot(satu.nama, '#475569', letak.get(`penanda-${i}`)) }),
    }));

    const panen = data.musim_panen.map((satu, i) => ({
        x: format.keWaktu(satu.mulai),
        x2: format.keWaktu(satu.selesai),
        fillColor: WARNA.panen,
        opacity: 0.08,
        borderColor: 'transparent',
        ...(letak.has(`panen-${i}`) && { label: labelDiAtasPlot(TEKS_PANEN, '#15803d', letak.get(`panen-${i}`)) }),
    }));

    const barisTerpakai = letak.size === 0 ? 0 : Math.max(...[...letak.values()].map((satu) => satu.baris)) + 1;

    return { xaxis: [...panen, ...penanda], ruangAtas: barisTerpakai === 0 ? 0 : barisTerpakai * TINGGI_BARIS_LABEL + OFFSET_LABEL };
}

/**
 * Nama penanda dan arti area hijau ditulis juga sebagai teks, supaya tetap
 * terbaca ketika label di grafik tidak muat atau grafik dibaca pembaca layar.
 */
function tampilkanKeterangan(data) {
    const bagian = [];

    if (data.penanda.length > 0) {
        const daftar = data.penanda.map((satu) => `${satu.nama} (${format.tanggalSingkat(satu.tanggal)})`).join(', ');
        bagian.push(`Garis vertikal putus-putus: ${daftar}.`);
    }

    if (data.musim_panen.length > 0) {
        bagian.push('Area hijau: musim panen.');
    }

    tulis('tren-penanda', bagian.join(' '));
}

function gambarGrafik(data) {
    const satuan = data.komoditas.satuan;
    const { xaxis: anotasiX, ruangAtas } = anotasi(data);

    grafik = gambar(cari('tren-grafik'), grafik, {
        chart: { type: 'line', height: 360 },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 3, padding: { top: ruangAtas } },
        series: data.seri.map((seri) => ({
            name: seri.nama,
            data: data.tanggal.map((tanggal, i) => [format.keWaktu(tanggal), seri.harga[i]]),
        })),
        colors: [WARNA.nasional, ...WARNA.provinsi],
        stroke: {
            curve: 'straight',
            width: data.seri.map((_, i) => (i === 0 ? 2 : 2.5)),
            dashArray: data.seri.map((_, i) => (i === 0 ? 6 : 0)),
        },
        markers: { size: data.agregasi === 'harian' ? 0 : 3 },
        xaxis: { type: 'datetime', labels: { datetimeUTC: true } },
        yaxis: { labels: { formatter: (nilai) => format.rupiah(nilai) } },
        tooltip: {
            shared: true,
            x: { formatter: (waktu) => format.periode(new Date(waktu).toISOString().slice(0, 10), data.agregasi) },
            y: { formatter: (nilai) => (nilai === null || nilai === undefined ? 'tanpa data' : format.hargaPerSatuan(nilai, satuan)) },
        },
        annotations: { xaxis: anotasiX },
    });
}

function tampilkanCelah(data) {
    kosongkan('tren-celah');

    const satuanPeriode = SATUAN_PERIODE[data.agregasi];

    for (const seri of data.seri.slice(1)) {
        const kosong = seri.harga.filter((nilai) => nilai === null).length;

        if (kosong > 0) {
            cari('tren-celah').append(buat('li', {
                teks: `${seri.nama}: ${kosong} dari ${seri.harga.length} ${satuanPeriode} tanpa data pada periode ini.`,
            }));
        }
    }
}

function isiTabel() {
    const data = dataTerakhir;

    if (!data || !cari('tren-rincian').open) {
        return;
    }

    kosongkan('tren-tabel-kepala');
    kosongkan('tren-tabel-isi');

    cari('tren-tabel-kepala').append(buat('tr', {}, [
        buat('th', { kelas: 'px-3 py-2', teks: 'Periode', atribut: { scope: 'col' } }),
        ...data.seri.map((seri) => buat('th', { kelas: 'px-3 py-2 text-right', teks: seri.nama, atribut: { scope: 'col' } })),
    ]));

    const baris = data.tanggal.map((tanggal, i) => buat('tr', {}, [
        buat('th', { kelas: 'px-3 py-2 font-normal', teks: format.periode(tanggal, data.agregasi), atribut: { scope: 'row' } }),
        ...data.seri.map((seri) => sel(seri.harga[i] === null ? 'tanpa data' : format.rupiah(seri.harga[i]), 'px-3 py-2 text-right tabular-nums')),
    ]));

    cari('tren-tabel-isi').append(...baris.reverse());
}

export function pasangTabel() {
    cari('tren-rincian').addEventListener('toggle', isiTabel);
}

export async function muatTren(konfigurasi, filter) {
    status('tren-status', 'Memuat tren harga…');

    const provinsi = kotakProvinsi().filter((kotak) => kotak.checked).map((kotak) => kotak.value);

    let data;

    try {
        data = await ambil(konfigurasi.api.tren, {
            komoditas: filter.komoditas,
            tingkat: filter.tingkat,
            provinsi,
            agregasi: cari('tren-agregasi').value,
            ...rentangTanggal(konfigurasi),
        }, 'tren');
    } catch (galat) {
        if (!dibatalkan(galat)) {
            status('tren-status', galat.message, true);
        }

        return;
    }

    dataTerakhir = data;
    gambarGrafik(data);
    tampilkanKeterangan(data);
    tampilkanCelah(data);
    isiTabel();

    status('tren-status', `${format.tanggal(data.mulai)} sampai ${format.tanggal(data.selesai)} · harga per ${data.komoditas.satuan}`);
}
