<?php
declare(strict_types=1);

require __DIR__ . "/data1.php";
require __DIR__ . "/function.php";

// 1. Nilai awal form
$input = [
    "nama"        => "",
    "whatsapp"    => "",
    "alamat"      => "",
    "metode_pengambilan" => "Ambil di toko",
    "kode_produk" => (string) filter_input(INPUT_GET, "produk"),
    "jumlah"      => "1",
    "catatan"     => "",
];
$kesalahan = [];

// 2. Proses hanya jika form dikirim dengan POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 2a. Tangkap dan rapikan (trim) semua isian
    $input = [
        "nama"        => trim((string) filter_input(INPUT_POST, "nama")),
        "whatsapp"    => trim((string) filter_input(INPUT_POST, "whatsapp")),
        "alamat"      => trim((string) filter_input(INPUT_POST, "alamat")),
        "metode_pengambilan" => trim((string) filter_input(INPUT_POST, "metode_pengambilan")),
        "kode_produk" => (string) filter_input(INPUT_POST, "kode_produk"),
        "jumlah"      => trim((string) filter_input(INPUT_POST, "jumlah")),
        "catatan"     => trim((string) filter_input(INPUT_POST, "catatan")),
    ];

    // 2b. Validasi
    $kesalahan = validasiPesanan($input, $daftarProduk);

    // 2c. Lolos validasi: simpan, lalu REDIRECT (pola Post/Redirect/Get)
    if (count($kesalahan) === 0) {
        $produk = cariProdukByKode($daftarProduk, $input["kode_produk"]);
        $jumlah = (int) $input["jumlah"];
        $kode = buatKodePesanan(count(bacaPesanan(FILE_PESANAN)) + 1);

        simpanPesanan(FILE_PESANAN, [
            "kode"     => $kode,
            "waktu"    => date("Y-m-d H:i"),
            "nama"     => $input["nama"],
            "whatsapp" => $input["whatsapp"],
            "alamat"   => $input["alamat"],
            "metode_pengambilan" => $input["metode_pengambilan"],
            "produk"   => $produk["nama"],
            "harga"    => $produk["harga"],
            "jumlah"   => $jumlah,
            "total"    => $produk["harga"] * $jumlah,
            "catatan"  => $input["catatan"],
        ]);

        header("Location: pesan.php?status=berhasil&kode=" . urlencode($kode), true, 303);
        exit;
    }
}

// 3. Setelah redirect: baca status dari URL (GET)
$status = filter_input(INPUT_GET, "status");
$kodeBaru = filter_input(INPUT_GET, "kode", FILTER_VALIDATE_REGEXP, [
    "options" => ["regexp" => "/^PSN-[0-9]{4}$/"],
]);
$berhasil = ($status === "berhasil" && is_string($kodeBaru));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan · <?= e($namaToko) ?></title>
    <link rel="stylesheet" href="setil.css?v=9">
</head>
<body>
    <header class="kepala">
        <h1><?= e($namaToko) ?></h1>
        <nav class="menu">
            <a href="index.php">Katalog</a>
            <a href="pesan.php" class="aktif">Pesan</a>
            <a href="daftar-pesanan.php">Daftar Pesanan</a>
        </nav>
    </header>

    <main class="wadah sempit">
        <h2>Form Pemesanan</h2>

        <?php if ($berhasil): ?>
            <p class="alert alert--sukses">
                Terima kasih! Pesanan <strong><?= e($kodeBaru) ?></strong> sudah kami terima.
                Admin akan menghubungi Anda lewat WhatsApp.
            </p>
        <?php endif; ?>

        <?php if (count($kesalahan) > 0): ?>
            <p class="alert alert--gagal">Periksa kembali isian yang ditandai merah.</p>
        <?php endif; ?>

        <form action="pesan.php" method="post" class="form" novalidate>
            <div class="isian">
                <label for="nama">Nama lengkap</label>
                <input type="text" id="nama" name="nama" value="<?= e($input["nama"]) ?>">
                <?php if (isset($kesalahan["nama"])): ?>
                    <small class="galat"><?= e($kesalahan["nama"]) ?></small>
                <?php endif; ?>
            </div>

            <div class="isian">
                <label for="whatsapp">Nomor WhatsApp</label>
                <input type="tel" id="whatsapp" name="whatsapp" placeholder="08xxxxxxxxxx"
                       value="<?= e($input["whatsapp"]) ?>">
                <?php if (isset($kesalahan["whatsapp"])): ?>
                    <small class="galat"><?= e($kesalahan["whatsapp"]) ?></small>
                <?php endif; ?>
            </div>

            <div class="isian">
                <label for="alamat">Alamat Pengiriman</label>
                <textarea id="alamat" name="alamat" rows="3"><?= e($input["alamat"]) ?></textarea>
                 
                <?php if (isset($kesalahan["alamat"])): ?>
                    <small class="galat"><?= e($kesalahan["alamat"]) ?></small>
                    
                <?php endif; ?>
            </div>

            <div class="isian">
                <label>Metode Pengambilan</label>
                
                <label>
                    <input type="radio" name="metode_pengambilan"
                            value="Ambil di toko"
                            <?= $input["metode_pengambilan"] === "Ambil di toko" ? "checked" : "" ?>>
                    Ambil di toko
                </label>
                
                <label>
                    <input type="radio" name="metode_pengambilan"
                            value="Kirim kurir"
                            <?= $input["metode_pengambilan"] === "Kirim kurir" ? "checked" : "" ?>>
                    Kirim kurir
                </label>
                
                <?php if (isset($kesalahan["metode_pengambilan"])): ?>
                    <small class="galat"><?= e($kesalahan["metode_pengambilan"]) ?></small>
                <?php endif; ?>
            </div>

            <div class="isian">
                <label for="kode_produk">Produk</label>
                <select id="kode_produk" name="kode_produk">
                    <option value="">-- pilih produk --</option>
                    <?php foreach ($daftarProduk as $produk): ?>
                        <option value="<?= e($produk["kode"]) ?>"
                            <?= $produk["kode"] === $input["kode_produk"] ? "selected" : "" ?>
                            <?= $produk["stok"] === 0 ? "disabled" : "" ?>>
                            <?= e($produk["nama"]) ?> · <?= formatRupiah($produk["harga"]) ?>
                            <?= $produk["stok"] === 0 ? "(habis)" : "" ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($kesalahan["kode_produk"])): ?>
                    <small class="galat"><?= e($kesalahan["kode_produk"]) ?></small>
                <?php endif; ?>
            </div>

            <div class="isian">
                <label for="jumlah">Jumlah</label>
                <input type="number" id="jumlah" name="jumlah" min="1" max="20"
                       value="<?= e($input["jumlah"]) ?>">
                <?php if (isset($kesalahan["jumlah"])): ?>
                    <small class="galat"><?= e($kesalahan["jumlah"]) ?></small>
                <?php endif; ?>
            </div>

            <div class="isian">
                <label for="catatan">Catatan (opsional)</label>
                <textarea id="catatan" name="catatan" rows="3"><?= e($input["catatan"]) ?></textarea>
                <?php if (isset($kesalahan["catatan"])): ?>
                    <small class="galat"><?= e($kesalahan["catatan"]) ?></small>
                <?php endif; ?>
            </div>

            <button type="submit" class="tombol">Kirim Pesanan</button>
        </form>
    </main>

    <footer class="kaki">
        <p>&copy; <?= date("Y") ?> Pemrograman Web · Informatika TSU</p>
    </footer>
</body>
</html>