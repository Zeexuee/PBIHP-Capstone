/**
 * Format angka dan tanggal untuk tampilan berbahasa Indonesia.
 * Semua fungsi mengembalikan tanda pisah bila nilainya kosong.
 */

const KOSONG = '–';

const angkaBulat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const angkaSatuDesimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const angkaDuaDesimal = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const tanggalPanjang = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const tanggalPendek = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
const bulanTahun = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' });

const kosong = (nilai) => nilai === null || nilai === undefined || Number.isNaN(nilai);

export function rupiah(nilai) {
    return kosong(nilai) ? KOSONG : `Rp${angkaBulat.format(nilai)}`;
}

export function rupiahBertanda(nilai) {
    if (kosong(nilai)) {
        return KOSONG;
    }

    const tanda = nilai > 0 ? '+' : nilai < 0 ? '−' : '';

    return `${tanda}Rp${angkaBulat.format(Math.abs(nilai))}`;
}

export function hargaPerSatuan(nilai, satuan) {
    return kosong(nilai) ? KOSONG : `${rupiah(nilai)}/${satuan}`;
}

export function persen(nilai) {
    return kosong(nilai) ? KOSONG : `${angkaDuaDesimal.format(nilai)}%`;
}

export function persenBertanda(nilai) {
    if (kosong(nilai)) {
        return KOSONG;
    }

    const tanda = nilai > 0 ? '+' : nilai < 0 ? '−' : '';

    return `${tanda}${angkaDuaDesimal.format(Math.abs(nilai))}%`;
}

/**
 * Untuk label sumbu yang sempit: "+10%" alih-alih "+10,00%". Satu desimal
 * hanya muncul bila garis bantu jatuh pada nilai pecahan.
 */
export function persenBertandaRingkas(nilai) {
    if (kosong(nilai)) {
        return KOSONG;
    }

    const tanda = nilai > 0 ? '+' : nilai < 0 ? '−' : '';

    return `${tanda}${angkaSatuDesimal.format(Math.abs(nilai))}%`;
}

export function desimal(nilai) {
    return kosong(nilai) ? KOSONG : angkaDuaDesimal.format(nilai);
}

/**
 * Tanggal disimpan tanpa jam, sehingga dibaca sebagai tengah malam UTC agar
 * tidak bergeser satu hari karena zona waktu peramban.
 */
export function keWaktu(tanggalIso) {
    return Date.parse(`${tanggalIso}T00:00:00Z`);
}

export function tanggal(tanggalIso) {
    return tanggalIso ? tanggalPanjang.format(keWaktu(tanggalIso)) : KOSONG;
}

export function tanggalSingkat(tanggalIso) {
    return tanggalIso ? tanggalPendek.format(keWaktu(tanggalIso)) : KOSONG;
}

export function periode(tanggalIso, agregasi) {
    if (!tanggalIso) {
        return KOSONG;
    }

    if (agregasi === 'bulanan') {
        return bulanTahun.format(keWaktu(tanggalIso));
    }

    if (agregasi === 'mingguan') {
        return `Minggu ${tanggalSingkat(tanggalIso)}`;
    }

    return tanggal(tanggalIso);
}

export function tambahHari(tanggalIso, jumlahHari) {
    const waktu = new Date(keWaktu(tanggalIso));
    waktu.setUTCDate(waktu.getUTCDate() + jumlahHari);

    return waktu.toISOString().slice(0, 10);
}
