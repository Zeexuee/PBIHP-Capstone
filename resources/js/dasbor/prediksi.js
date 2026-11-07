import { ambil, dibatalkan } from './api.js';
import { buat, cari, kosongkan, sel, status, tulis } from './dom.js';
import * as format from './format.js';
import { gambar, WARNA } from './grafik.js';

let grafik = null;

/**
 * Rentang sumbu harga paling sempit, relatif terhadap harga di tengah grafik.
 * Tanpa batas ini, harga yang datar membuat sumbu hanya selebar beberapa
 * rupiah, sehingga garis datar terlihat seperti naik turun.
 */
const RENTANG_MINIMUM_RELATIF = 0.02;

const LANGKAH_RUPIAH = [50, 100, 200, 250, 500, 1000, 2000, 2500, 5000, 10000];

const JUMLAH_GARIS_BANTU_MAKS = 6;

/**
 * Batas sumbu harga yang dibulatkan ke kelipatan rupiah yang rapi, dengan
 * rentang minimal RENTANG_MINIMUM_RELATIF dari harga tengah.
 */
function sumbuHarga(seri) {
    const nilai = seri.flatMap((satu) => satu.data.map(([, harga]) => harga)).filter((harga) => harga !== null && harga !== undefined);

    if (nilai.length === 0) {
        return {};
    }

    let bawah = Math.min(...nilai);
    let atas = Math.max(...nilai);
    const minimum = ((bawah + atas) / 2) * RENTANG_MINIMUM_RELATIF;

    if (atas - bawah < minimum) {
        const tengah = (bawah + atas) / 2;
        bawah = tengah - minimum / 2;
        atas = tengah + minimum / 2;
    }

    // Sedikit ruang agar garis tidak menempel pada tepi atas atau bawah grafik.
    const ruang = (atas - bawah) * 0.1;
    bawah -= ruang;
    atas += ruang;

    const langkah = LANGKAH_RUPIAH.find((satu) => Math.ceil(atas / satu) - Math.floor(bawah / satu) <= JUMLAH_GARIS_BANTU_MAKS) ?? LANGKAH_RUPIAH.at(-1);
    const min = Math.floor(bawah / langkah) * langkah;
    const max = Math.ceil(atas / langkah) * langkah;

    return { min, max, tickAmount: Math.max(1, Math.round((max - min) / langkah)) };
}

/**
 * Titik historis terakhir disambungkan ke awal garis prakiraan agar terlihat
 * kelanjutannya, tanpa mengubah angka prakiraan itu sendiri.
 */
function sambungkan(titik, historis) {
    const indeks = historis.harga.findLastIndex((nilai) => nilai !== null);
    const awal = indeks === -1 ? [] : [[format.keWaktu(historis.tanggal[indeks]), historis.harga[indeks]]];

    return [...awal, ...titik.map((satu) => [format.keWaktu(satu.tanggal), satu.harga])];
}

function gambarGrafik(data) {
    const satuan = data.komoditas.satuan;

    const seri = [
        { name: 'Harga tercatat', data: data.historis.tanggal.map((tanggal, i) => [format.keWaktu(tanggal), data.historis.harga[i]]) },
        { name: `Prakiraan ${data.model.label}`, data: sambungkan(data.prediksi, data.historis) },
    ];
    const warna = [WARNA.merek, data.memakai_garis_dasar ? WARNA.nasional : WARNA.mahal];
    const garis = [0, 6];

    if (data.garis_dasar.length > 0) {
        seri.push({ name: 'Garis dasar Naive', data: sambungkan(data.garis_dasar, data.historis) });
        warna.push(WARNA.nasional);
        garis.push(2);
    }

    const awal = data.prediksi[0]?.tanggal;
    const akhir = data.prediksi.at(-1)?.tanggal;

    grafik = gambar(cari('prediksi-grafik'), grafik, {
        chart: { type: 'line', height: 340 },
        series: seri,
        colors: warna,
        stroke: { curve: 'straight', width: seri.map(() => 2.5), dashArray: garis },
        xaxis: { type: 'datetime', labels: { datetimeUTC: true } },
        yaxis: { ...sumbuHarga(seri), labels: { formatter: (nilai) => format.rupiah(nilai) } },
        tooltip: {
            shared: true,
            x: { formatter: (waktu) => format.tanggal(new Date(waktu).toISOString().slice(0, 10)) },
            y: { formatter: (nilai) => (nilai === null || nilai === undefined ? 'tanpa data' : format.hargaPerSatuan(nilai, satuan)) },
        },
        annotations: {
            xaxis: awal ? [{
                x: format.keWaktu(awal),
                x2: format.keWaktu(akhir),
                fillColor: WARNA.merek,
                opacity: 0.07,
                borderColor: 'transparent',
                label: {
                    text: 'Prakiraan',
                    position: 'top',
                    orientation: 'horizontal',
                    borderColor: 'transparent',
                    style: { background: 'transparent', color: WARNA.merek, fontSize: '11px' },
                },
            }] : [],
        },
    });
}

function keteranganStatus(metrik, kriteria) {
    if (metrik.model === 'naive') {
        return 'Garis dasar';
    }

    if (metrik.lolos) {
        return 'Lolos kriteria';
    }

    const alasan = [];

    if (!metrik.lolos_mape) {
        alasan.push(`MAPE tidak di bawah ${format.desimal(kriteria.mape_maks)}%`);
    }

    if (!metrik.lolos_r2) {
        alasan.push(`R² di bawah ${format.desimal(kriteria.r2_min)}`);
    }

    if (metrik.mengalahkan_naive === false) {
        alasan.push('tidak mengalahkan garis dasar');
    }

    return `Tidak lolos: ${alasan.join(', ')}`;
}

function isiMetrik(data) {
    kosongkan('prediksi-metrik');

    const baris = data.metrik.map((metrik) => {
        const lencana = metrik.model === 'naive'
            ? 'bg-slate-100 text-slate-700'
            : metrik.lolos ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800';

        const status = keteranganStatus(metrik, data.kriteria);
        const kelasLencana = `rounded px-2 py-0.5 text-xs font-medium ${lencana}`;

        // Di layar sempit kolom Status tidak muat, jadi statusnya ditulis di bawah nama model dan kolomnya disembunyikan.
        // Hanya satu dari keduanya yang tampil pada satu ukuran layar.
        return buat('tr', {}, [
            buat('th', { kelas: 'px-2 py-2 font-medium text-slate-900 sm:px-3', atribut: { scope: 'row' } }, [
                metrik.label,
                ...(metrik.terpilih ? [buat('span', { kelas: 'block text-xs font-normal text-slate-500', teks: 'model terpilih' })] : []),
                buat('span', { kelas: `mt-1 inline-block sm:hidden ${kelasLencana}`, teks: status }),
            ]),
            sel(format.persen(metrik.mape), 'px-2 py-2 text-right tabular-nums sm:px-3'),
            sel(format.rupiah(metrik.rmse), 'px-2 py-2 text-right tabular-nums sm:px-3'),
            sel(format.rupiah(metrik.mae), 'px-2 py-2 text-right tabular-nums sm:px-3'),
            sel(format.desimal(metrik.r2), 'px-2 py-2 text-right tabular-nums sm:px-3'),
            buat('td', { kelas: 'hidden px-3 py-2 sm:table-cell' }, [
                buat('span', { kelas: `inline-block ${kelasLencana}`, teks: status }),
            ]),
        ]);
    });

    cari('prediksi-metrik').append(...baris);
}

function isiRingkas(data) {
    const metrikTampil = data.metrik.find((metrik) => metrik.model === data.model.kode);
    const terakhir = data.prediksi.at(-1);
    const kalimat = [];

    if (data.memakai_garis_dasar) {
        kalimat.push('Belum ada model yang memenuhi kriteria penerimaan, sehingga yang ditampilkan adalah garis dasar: harga terakhir yang tercatat diulang untuk hari-hari berikutnya.');
    }

    if (metrikTampil) {
        kalimat.push(`Pada periode uji ${format.tanggal(metrikTampil.periode_uji_mulai)} sampai ${format.tanggal(metrikTampil.periode_uji_selesai)}, prakiraan ini rata-rata meleset sekitar ${format.persen(metrikTampil.mape)} dari harga sebenarnya. Angka ini dihitung dari seluruh provinsi sekaligus, bukan khusus ${data.provinsi.nama}.`);
    }

    if (terakhir) {
        kalimat.push(`Prakiraan untuk ${format.tanggal(terakhir.tanggal)}: ${format.hargaPerSatuan(terakhir.harga, data.komoditas.satuan)}.`);
    }

    tulis('prediksi-model', data.model.label);
    tulis('prediksi-ringkas', kalimat.join(' '));
}

export async function muatPrediksi(konfigurasi, filter) {
    status('prediksi-status', 'Memuat prediksi…');

    let data;

    try {
        data = await ambil(konfigurasi.api.prediksi, {
            komoditas: filter.komoditas,
            provinsi: cari('prediksi-provinsi').value,
        }, 'prediksi');
    } catch (galat) {
        if (!dibatalkan(galat)) {
            status('prediksi-status', galat.message, true);
        }

        return;
    }

    isiMetrik(data);
    isiRingkas(data);

    if (data.prediksi.length === 0) {
        grafik?.destroy();
        grafik = null;
        status('prediksi-status', 'Belum ada prediksi untuk provinsi dan komoditas ini.');

        return;
    }

    gambarGrafik(data);
    status('prediksi-status', `${data.provinsi.nama} · harga konsumen per ${data.komoditas.satuan}`);
}
