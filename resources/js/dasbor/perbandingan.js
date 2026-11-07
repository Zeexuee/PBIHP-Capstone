import { ambil, dibatalkan } from './api.js';
import { buat, cari, kosongkan, sel, status, tulis } from './dom.js';
import * as format from './format.js';
import { gambar, WARNA } from './grafik.js';

let grafik = null;

/** Lebar jendela (px) di bawahnya grafik memakai label yang lebih ringkas. */
const BATAS_PONSEL = 480;

/**
 * Batang menunjukkan selisih persen terhadap rata-rata nasional, bukan harga
 * mutlak. Batang harga yang dimulai dari nol membuat selisih beberapa persen
 * tidak terlihat, sedangkan memotong sumbu batang akan menyesatkan.
 */
function gambarGrafik(data) {
    const satuan = data.komoditas.satuan;
    const provinsi = data.provinsi;

    grafik = gambar(cari('perbandingan-grafik'), grafik, {
        chart: { type: 'bar', height: Math.max(320, provinsi.length * 28 + 80) },
        series: [{ name: 'Selisih dari rata-rata nasional', data: provinsi.map((satu) => satu.selisih_persen) }],
        colors: provinsi.map((satu) => (satu.selisih < 0 ? WARNA.murah : WARNA.mahal)),
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '70%', borderRadius: 2 } },
        legend: { show: false },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 3, padding: { top: 20 } },
        xaxis: {
            categories: provinsi.map((satu) => satu.nama),
            labels: { formatter: (nilai) => format.persenBertandaRingkas(Number(nilai)) },
        },
        yaxis: { labels: { maxWidth: 200 } },
        responsive: [{
            breakpoint: BATAS_PONSEL,
            options: {
                xaxis: { tickAmount: 2 },
                yaxis: { labels: { maxWidth: 175, style: { fontSize: '11px' } } },
            },
        }],
        tooltip: {
            y: {
                formatter: (nilai, { dataPointIndex }) => `${format.persenBertanda(nilai)} · ${format.hargaPerSatuan(provinsi[dataPointIndex].harga, satuan)}`,
            },
        },
        annotations: {
            xaxis: [{
                x: 0,
                borderColor: '#334155',
                label: {
                    text: 'Rata-rata nasional',
                    orientation: 'horizontal',
                    offsetY: -18,
                    borderColor: 'transparent',
                    style: { background: 'transparent', color: '#334155', fontSize: '11px' },
                },
            }],
        },
    });
}

function isiTabel(data) {
    kosongkan('perbandingan-tabel');

    const baris = data.provinsi.map((satu) => {
        const warnaSelisih = satu.selisih < 0 ? 'text-murah' : 'text-mahal';

        return buat('tr', {}, [
            sel(String(satu.peringkat), 'px-3 py-2 text-slate-500 tabular-nums'),
            buat('th', { kelas: 'px-3 py-2 font-medium text-slate-900', atribut: { scope: 'row' } }, [
                satu.nama,
                buat('span', { kelas: 'block text-xs font-normal text-slate-500', teks: satu.pulau_label }),
            ]),
            sel(format.rupiah(satu.harga), 'px-3 py-2 text-right tabular-nums'),
            sel(format.rupiahBertanda(satu.selisih), `px-3 py-2 text-right tabular-nums ${warnaSelisih}`),
            sel(format.persenBertanda(satu.selisih_persen), `px-3 py-2 text-right tabular-nums ${warnaSelisih}`),
        ]);
    });

    cari('perbandingan-tabel').append(...baris);

    tulis('perbandingan-tanpa-data', data.provinsi_tanpa_data.length === 0
        ? ''
        : `Tanpa data pada tanggal ini: ${data.provinsi_tanpa_data.map((satu) => satu.nama).join(', ')}.`);
}

export async function muatPerbandingan(konfigurasi, filter) {
    status('perbandingan-status', 'Memuat perbandingan…');

    let data;

    try {
        data = await ambil(konfigurasi.api.perbandingan, {
            ...filter,
            tanggal: cari('perbandingan-tanggal').value,
        }, 'perbandingan');
    } catch (galat) {
        if (!dibatalkan(galat)) {
            status('perbandingan-status', galat.message, true);
        }

        return;
    }

    isiTabel(data);

    if (data.provinsi.length === 0) {
        grafik?.destroy();
        grafik = null;
        status('perbandingan-status', 'Tidak ada data provinsi pada tanggal ini. PIHPS hanya mencatat harga pada hari kerja, coba pilih tanggal lain.');

        return;
    }

    gambarGrafik(data);
    status('perbandingan-status', `${format.tanggal(data.tanggal)} · rata-rata nasional ${format.hargaPerSatuan(data.rata_rata_nasional, data.komoditas.satuan)}`);
}
