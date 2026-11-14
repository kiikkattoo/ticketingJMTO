<?php
require 'koneksi.php';

// Ambil daftar Area / Lokasi / Gate dari database
$res_area = mysqli_query($conn, "SELECT a.*, m.nama_mitra FROM area_lokasi a LEFT JOIN mitra m ON a.kode_mitra = m.kode_mitra ORDER BY a.nama_area ASC, a.nama_gate ASC");
$area_list = [];
while ($row = mysqli_fetch_assoc($res_area)) {
    $area_list[] = $row;
}

$notif = "";

// Proses Simulasi Tap / Scan QR Masuk ke Database
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $jumlah_loop = isset($_POST['bulk']) ? 10 : 1;
    $id_area_pilihan = intval($_POST['id_area']);
    $penerbit_pilihan = mysqli_real_escape_string($conn, $_POST['penerbit']);
    $kategori_pilihan = mysqli_real_escape_string($conn, $_POST['tiket_kategori']);
    $jenis_pilihan    = mysqli_real_escape_string($conn, $_POST['jenis_tiket']);
    $tipe_pilihan     = mysqli_real_escape_string($conn, $_POST['tipe_tiket']);
    $nominal_pilihan  = intval($_POST['nominal']);

    $penerbit_pool = ['Bank DKI', 'GOERS', 'APPSKU', 'Petugas'];
    $foto_sample   = "https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60";

    $sukses = 0;
    for ($i = 0; $i < $jumlah_loop; $i++) {
        // Jika bulk 10 transaksi, acak area & penerbit agar grafik beragam
        if ($jumlah_loop > 1 && count($area_list) > 0) {
            $rand_area = $area_list[array_rand($area_list)];
            $id_area   = (int)$rand_area['id_area'];
            $kode_area = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $rand_area['nama_area']), 0, 4));
            $penerbit  = $penerbit_pool[array_rand($penerbit_pool)];
        } else {
            $id_area   = $id_area_pilihan;
            $kode_area = "JMTO";
            foreach ($area_list as $al) {
                if ((int)$al['id_area'] === $id_area) {
                    $kode_area = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $al['nama_area']), 0, 4));
                    break;
                }
            }
            $penerbit = $penerbit_pilihan;
        }

        $tgl_kode     = date('Ymd');
        $rand_digit   = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $id_transaksi = "TRX-" . $tgl_kode . "-" . rand(100, 999) . $i;
        $kode_qr      = "MPOS-" . $tgl_kode . "-" . $kode_area . "-" . $rand_digit;
        $waktu_skrg   = date('Y-m-d H:i:s');

        $sql = "INSERT INTO transaksi (
                    id_transaksi, id_area, waktu_transaksi, penerbit, tiket_kategori, 
                    jenis_tiket, tipe_tiket, kode_qr, foto_cctv, nominal, status_transaksi
                ) VALUES (
                    '$id_transaksi', $id_area, '$waktu_skrg', '$penerbit', '$kategori_pilihan',
                    '$jenis_pilihan', '$tipe_pilihan', '$kode_qr', '$foto_sample', $nominal_pilihan, 'Berhasil'
                )";

        if (mysqli_query($conn, $sql)) {
            $sukses++;
        }
    }

    $notif = "Berhasil mensimulasikan <b>$sukses transaksi QR</b> ke database! Kode QR terakhir: <code>$kode_qr</code>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulator Gate & QR Scanner - JMTO Ticketing</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 30px;
            display: flex;
            justify-content: center;
        }
        .sim-container {
            background: #ffffff;
            max-width: 620px;
            width: 100%;
            border-radius: 14px;
            padding: 28px 32px;
            box-shadow: 0 6px 24px rgba(0, 75, 135, 0.08);
        }
        .sim-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 22px;
        }
        .sim-header h2 {
            margin: 0;
            font-size: 18px;
            color: #004b87;
        }
        .badge-live {
            background: #e6f7ed;
            color: #1e7e34;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            box-sizing: border-box;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 22px;
        }
        .btn-tap {
            flex: 2;
            background: #004b87;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-tap:hover { background: #003663; }
        .btn-bulk {
            flex: 1;
            background: #f2c94c;
            color: #1e293b;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-bulk:hover { background: #e0b63a; }
        .alert-success {
            background: #e6f7ed;
            color: #1e7e34;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #b7eb8f;
        }
        .quick-links {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        .quick-links a {
            color: #004b87;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="sim-container">
    <div class="sim-header">
        <div>
            <h2><i class="fa-solid fa-qrcode"></i> Simulator Tap / Scan Tiket Gate</h2>
            <p style="margin:4px 0 0; font-size:12px; color:#64748b;">Simulasi pengiriman data transaksi string QR dari gate ke database pusat</p>
        </div>
        <span class="badge-live"><i class="fa-solid fa-circle"></i> DB Connected</span>
    </div>

    <?php if ($notif): ?>
        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= $notif; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Pilih Lokasi & Gate Perangkat</label>
            <select name="id_area" required>
                <?php foreach ($area_list as $a): ?>
                    <option value="<?= $a['id_area']; ?>">
                        <?= htmlspecialchars($a['nama_area'] . ' — ' . $a['nama_lokasi'] . ' (' . $a['nama_gate'] . ') [' . ($a['nama_mitra'] ?? 'JMTO') . ']'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Penerbit / Channel Pembayaran</label>
                <select name="penerbit" required>
                    <option value="APPSKU">APPSKU</option>
                    <option value="Bank DKI">Bank DKI</option>
                    <option value="GOERS">GOERS</option>
                    <option value="Petugas">Petugas</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tiket Kategori</label>
                <select name="tiket_kategori" required>
                    <option value="Reguler">Reguler</option>
                    <option value="VIP">VIP</option>
                    <option value="Rombongan">Rombongan</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Jenis Tiket</label>
                <select name="jenis_tiket" required>
                    <option value="GUEST">GUEST</option>
                    <option value="Tiket Masuk Utama">Tiket Masuk Utama</option>
                    <option value="Tiket Wahana">Tiket Wahana</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tipe Tiket</label>
                <select name="tipe_tiket" required>
                    <option value="Kereta Gantung">Kereta Gantung</option>
                    <option value="Perorangan">Perorangan</option>
                    <option value="Kendaraan Roda 4">Kendaraan Roda 4</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Nominal Tarif Tiket (Rp)</label>
            <input type="number" name="nominal" value="50000" step="5000" required>
        </div>

        <div class="btn-group">
            <button type="submit" name="single" class="btn-tap">
                <i class="fa-solid fa-qrcode"></i> Simulasi 1x Scan QR
            </button>
            <button type="submit" name="bulk" class="btn-bulk" title="Generate 10 transaksi acak sekaligus">
                <i class="fa-solid fa-bolt"></i> +10 Acak
            </button>
        </div>
    </form>

    <div class="quick-links">
        <a href="admin/dashboard.php" target="_blank"><i class="fa-solid fa-chart-line"></i> Cek Dashboard JMTO &rarr;</a>
        <a href="admin/daftarTransaksi.php" target="_blank"><i class="fa-solid fa-list"></i> Cek Daftar Transaksi &rarr;</a>
    </div>
</div>

</body>
</html>