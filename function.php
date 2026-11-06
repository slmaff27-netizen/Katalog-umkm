<?php
declare(strict_types=1);

// functions.php - logika bisnis (tanpa HTML)

function formatRupiah(int $angka): string
{
    return "Rp" . number_format($angka, 0, ',', '.');
}

// Menentukan status stok
function statusStok(int $stok): string
{
    return match (true) {
        $stok === 0 => "Habis",
        $stok <= 10 => "Menipis",
        default => "Tersedia",
    };
}

// Menentukan kelas CSS badge
function kelasBadge(string $status): string
{
    return match ($status) {
        "Habis" => "badge badge--habis",
        "Menipis" => "badge badge--menipis",
        default => "badge badge--aman",
    };
}

// Menghitung nilai seluruh stok
function hitungNilaiStok(array $produk): int
{
    $total = 0;

    foreach ($produk as $item) {
        $total += $item["harga"] * $item["stok"];
    }

    return $total;
}

// Filter berdasarkan kategori
function filterKategori(array $produk, string $kategori): array
{
    if ($kategori === "Semua") {
        return $produk;
    }

    return array_filter(
        $produk,
        fn(array $p): bool => $p["kategori"] === $kategori
    );
}

// Mengamankan teks sebelum dicetak ke HTML
function e(string $teks): string
{
    return htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');
}

// Menentukan label promo
function labelPromo(int $harga): string
{
    return $harga >= 100_000 ? "Gratis Ongkir" : "";
}