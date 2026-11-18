<?php

declare(strict_types=1);

/**
 * File: functions.php
 * Deskripsi: Kumpulan fungsi logika bisnis, validasi form, pengolahan berkas JSON, dan utilitas tampilan.
 */

/**
 * Memformat angka bulat menjadi format mata uang Rupiah.
 */
function formatRupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Menentukan label status ketersediaan berdasarkan jumlah stok.
 */
function statusStok(int $stok): string
{
    return match (true) {
        $stok === 0 => 'Habis',
        $stok <= 10 => 'Menipis',
        default     => 'Tersedia',
    };
}

/**
 * Menentukan class CSS modifier untuk elemen badge berdasarkan status stok.
 */
function kelasBadge(string $status): string
{
    return match ($status) {
        'Tersedia' => 'badge--aman',
        'Menipis'  => 'badge--menipis',
        'Habis'    => 'badge--habis',
        default    => '',
    };
}

/**
 * Menghitung total nilai aset seluruh stok produk (harga dikali stok).
 *
 * @param array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}> $produk
 */
function hitungNilaiStok(array $produk): int
{
    $total = 0;
    foreach ($produk as $item) {
        $total += ($item['harga'] * $item['stok']);
    }
    return $total;
}

/**
 * Menyaring daftar produk berdasarkan kategori terpilih.
 *
 * @param array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}> $produk
 * @return array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}>
 */
function filterKategori(array $produk, string $kategori): array
{
    if ($kategori === 'Semua') {
        return $produk;
    }

    return array_values(
        array_filter(
            $produk,
            fn(array $item): bool => strcasecmp($item['kategori'], $kategori) === 0
        )
    );
}

/**
 * Membersihkan dan menyaring teks dari potensi serangan XSS (Cross-Site Scripting).
 */
function e(string $teks): string
{
    return htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Mencari produk berdasarkan nama produk secara case-insensitive.
 *
 * @param array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}> $produk
 * @return array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}>
 */
function cariProduk(array $produk, string $kataKunci): array
{
    $kataKunci = trim($kataKunci);
    if ($kataKunci === '') {
        return $produk;
    }

    return array_values(
        array_filter(
            $produk,
            fn(array $item): bool => stripos($item['nama'], $kataKunci) !== false
        )
    );
}

/**
 * Mencari satu produk spesifik berdasarkan kode produk.
 *
 * @param array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}> $produk
 * @return array{kode: string, nama: string, kategori: string, harga: int, stok: int}|null
 */
function cariProdukByKode(array $produk, string $kode): ?array
{
    foreach ($produk as $item) {
        if ($item['kode'] === $kode) {
            return $item;
        }
    }
    return null;
}

/**
 * Memvalidasi data input pemesanan sesuai aturan bisnis toko.
 *
 * @param array<string, mixed> $input
 * @param array<int, array{kode: string, nama: string, kategori: string, harga: int, stok: int}> $daftarProduk
 * @return array<string, string> Kumpulan pesan kesalahan (kosong jika valid).
 */
function validasiPesanan(array $input, array $daftarProduk): array
{
    $kesalahan = [];

    // 1. Validasi Nama: Wajib, panjang 3 - 50 karakter
    $nama = trim((string) ($input['nama'] ?? ''));
    if ($nama === '') {
        $kesalahan['nama'] = 'Nama pemesan wajib diisi.';
    } elseif (mb_strlen($nama) < 3 || mb_strlen($nama) > 50) {
        $kesalahan['nama'] = 'Nama pemesan harus memiliki panjang antara 3 hingga 50 karakter.';
    }

    // 2. Validasi WhatsApp: Wajib, format awalan 08 atau 628 diikuti 8-12 digit
    $whatsapp = trim((string) ($input['whatsapp'] ?? $input['wa'] ?? ''));
    $polaWa   = '/^(08|628)[0-9]{8,12}$/';
    if ($whatsapp === '') {
        $kesalahan['whatsapp'] = 'Nomor WhatsApp wajib diisi.';
    } elseif (filter_var($whatsapp, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $polaWa]]) === false) {
        $kesalahan['whatsapp'] = 'Format nomor WhatsApp tidak valid. Gunakan format 08... atau 628... (10-14 digit).';
    }

    // 3. Validasi Produk: Harus ada di daftar produk dan stok > 0
    $kodeProduk = trim((string) ($input['kode_produk'] ?? ''));
    $produkTerpilih = cariProdukByKode($daftarProduk, $kodeProduk);

    if ($kodeProduk === '' || $produkTerpilih === null) {
        $kesalahan['kode_produk'] = 'Silakan pilih produk yang tersedia.';
    } elseif ($produkTerpilih['stok'] <= 0) {
        $kesalahan['kode_produk'] = 'Mohon maaf, stok produk ini sedang habis.';
    }

    // 4. Validasi Jumlah: Rentang integer 1-20 dan tidak melebihi stok yang ada
    $jumlahRaw = $input['jumlah'] ?? null;
    $jumlahValid = filter_var(
        $jumlahRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 20]]
    );

    if ($jumlahValid === false) {
        $kesalahan['jumlah'] = 'Jumlah pesanan harus berupa angka bulat antara 1 sampai 20.';
    } elseif ($produkTerpilih !== null && (int) $jumlahValid > $produkTerpilih['stok']) {
        $kesalahan['jumlah'] = 'Jumlah pesanan melebihi stok yang tersedia (' . $produkTerpilih['stok'] . ' item).';
    }

    // 5. Validasi Catatan: Maksimal 200 karakter
    $catatan = trim((string) ($input['catatan'] ?? ''));
    if (mb_strlen($catatan) > 200) {
        $kesalahan['catatan'] = 'Catatan tambahan tidak boleh melebihi 200 karakter.';
    }

    // 6. Validasi Alamat: wajib, 10-150 karakter
    $alamat = trim((string) ($input['alamat'] ?? ''));
    
    if (mb_strlen($alamat) < 10 || mb_strlen($alamat) > 150) {
        $kesalahan['alamat'] = 'Alamat wajib diisi dengan panjang 10-150 karakter.';
    
    }
    // 6. Validasi Metode Pengambilan: menggunakan daftar putih
    $daftarMetode = ["Ambil di toko", "Kirim kurir"];
    
    $metode = $input["metode_pengambilan"] ?? "";
    
    if (!in_array($metode, $daftarMetode, true)) {
        $kesalahan["metode_pengambilan"] = "Pilih metode pengambilan yang tersedia.";
    }
    return $kesalahan;
}

/**
 * Membaca data pesanan dari berkas JSON.
 *
 * @param string $file Path lengkap berkas JSON.
 * @return array<int, array<string, mixed>> Daftar seluruh pesanan.
 */
function bacaPesanan(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $isiBerkas = file_get_contents($file);
    if ($isiBerkas === false || trim($isiBerkas) === '') {
        return [];
    }

    $data = json_decode($isiBerkas, true);
    return is_array($data) ? $data : [];
}

/**
 * Menyimpan data pesanan baru ke dalam berkas JSON (append).
 *
 * @param string $file Path berkas JSON.
 * @param array<string, mixed> $pesananBaru Data satu pesanan baru.
 */
function simpanPesanan(string $file, array $pesananBaru): void
{
    $direktori = dirname($file);
    if (!is_dir($direktori)) {
        mkdir($direktori, 0755, true);
    }

    $daftarPesanan = bacaPesanan($file);
    $daftarPesanan[] = $pesananBaru;

    $json = json_encode($daftarPesanan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($file, $json, LOCK_EX);
    }
}

/**
 * Membuat kode format pesanan berdasarkan nomor urut (contoh: PSN-0001).
 */
function buatKodePesanan(int $nomorUrut): string
{
    return sprintf('PSN-%04d', $nomorUrut);
}