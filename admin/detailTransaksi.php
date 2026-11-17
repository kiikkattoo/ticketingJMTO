<?php
session_start();
require_once dirname(__FILE__) . '/../koneksi.php';

// =========================================================
// HELPER DETEKSI NAMA KOLOM OTOMATIS AGAR ANTI-ERROR SQL
// =========================================================
function getExistingColumn($conn, $table, $candidates, $fallback = '')
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    if (!$res) return $fallback;
    $cols = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $cols[] = $row['Field'];
    }
    foreach ($candidates as $c) {
        if (in_array($c, $cols)) return $c;
    }
    return $fallback;
}

$col_trx_area     = getExistingColumn($conn, 'transaksi', ['kode_area', 'id_area', 'area_id'], 'kode_area');
$col_trx_status   = getExistingColumn($conn, 'transaksi', ['status_transaksi', 'status', 'status_pembayaran'], 'status_transaksi');
$col_trx_nominal  = getExistingColumn($conn, 'transaksi', ['nominal', 'harga', 'tarif', 'total_bayar', 'jumlah_bayar'], 'nominal');
$col_trx_penerbit = getExistingColumn($conn, 'transaksi', ['penerbit', 'metode_pembayaran', 'channel', 'payment_method'], 'penerbit');
$col_trx_waktu    = getExistingColumn($conn, 'transaksi', ['waktu_transaksi', 'tanggal_transaksi', 'created_at', 'tanggal', 'waktu'], 'waktu_transaksi');

$col_area_pk    = getExistingColumn($conn, 'area_lokasi', ['kode_area', 'id_area', 'id'], 'kode_area');
$col_area_nama  = getExistingColumn($conn, 'area_lokasi', ['nama_area', 'nama_lokasi', 'area'], 'nama_area');
$col_area_mitra = getExistingColumn($conn, 'area_lokasi', ['kode_mitra', 'id_mitra'], 'kode_mitra');

$col_mitra_pk   = getExistingColumn($conn, 'mitra', ['kode_mitra', 'id_mitra', 'id'], 'kode_mitra');
$col_mitra_nama = getExistingColumn($conn, 'mitra', ['nama_mitra', 'nama'], 'nama_mitra');
$col_mitra_kat  = getExistingColumn($conn, 'mitra', ['kategori_mitra', 'kategori', 'jenis_mitra'], 'kategori_mitra');

// =========================================================
// 1. AMBIL PARAMETER FILTER DARI URL (GET)
// =========================================================
$kategori_mitra = isset($_GET['kategori_mitra']) ? mysqli_real_escape_string($conn, $_GET['kategori_mitra']) : '';
$kode_mitra     = isset($_GET['mitra'])          ? mysqli_real_escape_string($conn, $_GET['mitra']) : '';
$kode_area      = isset($_GET['area'])           ? mysqli_real_escape_string($conn, $_GET['area']) : '';
$metode_bayar   = isset($_GET['metode'])         ? mysqli_real_escape_string($conn, $_GET['metode']) : '';
$status_trx     = isset($_GET['status'])         ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$tgl_awal       = isset($_GET['tgl_awal'])       ? mysqli_real_escape_string($conn, $_GET['tgl_awal']) : '';
$tgl_akhir      = isset($_GET['tgl_akhir'])      ? mysqli_real_escape_string($conn, $_GET['tgl_akhir']) : '';

$where = ["1=1"];
if ($kategori_mitra !== '') $where[] = "m.`$col_mitra_kat` = '$kategori_mitra'";
if ($kode_mitra !== '')     $where[] = "a.`$col_area_mitra` = '$kode_mitra'";
if ($kode_area !== '')      $where[] = "t.`$col_trx_area` = '$kode_area'";
if ($metode_bayar !== '')   $where[] = "t.`$col_trx_penerbit` = '$metode_bayar'";
if ($status_trx !== '')     $where[] = "t.`$col_trx_status` = '$status_trx'";

if ($tgl_awal !== '' && $tgl_akhir !== '') {
    $where[] = "DATE(t.`$col_trx_waktu`) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
} elseif ($tgl_awal !== '') {
    $where[] = "DATE(t.`$col_trx_waktu`) >= '$tgl_awal'";
} elseif ($tgl_akhir !== '') {
    $where[] = "DATE(t.`$col_trx_waktu`) <= '$tgl_akhir'";
}

$where_sql = implode(" AND ", $where);

// =========================================================
// 2. QUERY RINGKASAN 6 KARTU KPI & STATUS
// =========================================================
$sql_kpi = "SELECT
                COUNT(*) AS total_trx,
                SUM(CASE WHEN LOWER(t.`$col_trx_status`) IN ('berhasil', 'sukses', 'success', 'paid') THEN 1 ELSE 0 END) AS total_berhasil,
                SUM(CASE WHEN LOWER(t.`$col_trx_status`) IN ('pending', 'menunggu pembayaran', 'waiting') THEN 1 ELSE 0 END) AS total_pending,
                SUM(CASE WHEN LOWER(t.`$col_trx_status`) IN ('gagal', 'failed', 'batal', 'expired') THEN 1 ELSE 0 END) AS total_gagal,
                SUM(CASE WHEN LOWER(t.`$col_trx_status`) = 'refund' THEN 1 ELSE 0 END) AS total_refund,
                COALESCE(SUM(CASE WHEN LOWER(t.`$col_trx_status`) IN ('berhasil', 'sukses', 'success', 'paid') THEN t.`$col_trx_nominal` ELSE 0 END), 0) AS total_nominal
            FROM transaksi t
            LEFT JOIN area_lokasi a ON t.`$col_trx_area` = a.`$col_area_pk`
            LEFT JOIN mitra m ON a.`$col_area_mitra` = m.`$col_mitra_pk`
            WHERE $where_sql";

$res_kpi = mysqli_query($conn, $sql_kpi);
$kpi = ($res_kpi) ? mysqli_fetch_assoc($res_kpi) : [];

$total_trx      = (int)($kpi['total_trx'] ?? 0);
$total_berhasil = (int)($kpi['total_berhasil'] ?? 0);
$total_pending  = (int)($kpi['total_pending'] ?? 0);
$total_gagal    = (int)($kpi['total_gagal'] ?? 0);
$total_refund   = (int)($kpi['total_refund'] ?? 0);
$total_nominal  = (float)($kpi['total_nominal'] ?? 0);

// Hitung persentase masing-masing status
$pct_berhasil = $total_trx > 0 ? round(($total_berhasil / $total_trx) * 100, 1) : 0;
$pct_pending  = $total_trx > 0 ? round(($total_pending / $total_trx) * 100, 1) : 0;
$pct_gagal    = $total_trx > 0 ? round(($total_gagal / $total_trx) * 100, 1) : 0;
$pct_refund   = $total_trx > 0 ? round(($total_refund / $total_trx) * 100, 1) : 0;

// =========================================================
// 3. QUERY DISTRIBUSI PENERBIT / CHANNEL (UNTUK BAR CHART)
// =========================================================
$channel_labels  = ['Bank BCA', 'APPSKU', 'GOERS', 'Petugas'];
$channel_volume  = [0, 0, 0, 0];
$channel_nominal = [0, 0, 0, 0];

$sql_channel = "SELECT
                    t.`$col_trx_penerbit` AS penerbit,
                    COUNT(*) AS jml_tiket,
                    COALESCE(SUM(CASE WHEN LOWER(t.`$col_trx_status`) IN ('berhasil', 'sukses', 'success', 'paid') THEN t.`$col_trx_nominal` ELSE 0 END), 0) AS jml_nominal
                FROM transaksi t
                LEFT JOIN area_lokasi a ON t.`$col_trx_area` = a.`$col_area_pk`
                LEFT JOIN mitra m ON a.`$col_area_mitra` = m.`$col_mitra_pk`
                WHERE $where_sql
                GROUP BY t.`$col_trx_penerbit`";

$res_channel = mysqli_query($conn, $sql_channel);
if ($res_channel) {
    while ($row = mysqli_fetch_assoc($res_channel)) {
        $penerbit = trim((string)$row['penerbit']);
        $idx = false;
        foreach ($channel_labels as $i => $lbl) {
            if (strcasecmp($lbl, $penerbit) === 0) {
                $idx = $i;
                break;
            }
        }
        if ($idx !== false) {
            $channel_volume[$idx]  = (int)$row['jml_tiket'];
            $channel_nominal[$idx] = (float)$row['jml_nominal'];
        } elseif ($penerbit !== '') {
            $channel_labels[]  = $penerbit;
            $channel_volume[]  = (int)$row['jml_tiket'];
            $channel_nominal[] = (float)$row['jml_nominal'];
        }
    }
}

// =========================================================
// 4. DATA DROPDOWN FILTER
// =========================================================
$q_mitra = mysqli_query($conn, "SELECT `$col_mitra_pk` AS kode_mitra, `$col_mitra_nama` AS nama_mitra FROM mitra ORDER BY `$col_mitra_nama` ASC");
$q_area  = mysqli_query($conn, "SELECT `$col_area_pk` AS kode_area, `$col_area_nama` AS nama_area FROM area_lokasi ORDER BY `$col_area_nama` ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Transaksi - Ticketing JMTO</title>
<link rel="stylesheet" href="../style.css">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* =========================================================
   PERBAIKAN SCROLL & FULL-BLEED LAYOUT AGAR BISA DI-SCROLL
========================================================= */
html, body {
    height: auto !important;
    min-height: 100vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    background-color: #F4F6F9 !important;
    margin: 0;
    padding: 0;
}
.container {
    display: flex !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 100vh !important;
    overflow: visible !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #F4F6F9 !important;
    box-shadow: none !important;
}
.sidebar {
    position: fixed !important;
    top: 0;
    left: 0;
    height: 100vh !important;
    overflow-y: auto !important;
    z-index: 100;
}
.main-content {
    flex: 1 !important;
    margin-left: 265px !important;
    width: calc(100% - 265px) !important;
    max-width: none !important;
    height: auto !important;
    min-height: 100vh !important;
    overflow: visible !important;
    padding: 28px 36px 50px 36px !important;
    background-color: #F4F6F9 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
}

/* Header & Breadcrumb */
.breadcrumb-text {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 4px;
    font-weight: 500;
}
.page-title {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 20px 0;
}

/* Filter Card */
.filter-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 22px 24px;
    border: 1px solid #e2e8f0;
    margin-bottom: 22px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px 20px;
}
.filter-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.filter-group select {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13.5px;
    color: #1e293b;
    background-color: #ffffff;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.filter-group select:focus {
    border-color: #004B87;
}
.filter-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.btn-reset {
    padding: 8px 18px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}
.btn-reset:hover {
    background: #f8fafc;
    color: #0f172a;
}
.btn-filter {
    padding: 8px 22px;
    border-radius: 8px;
    border: none;
    background: #004B87;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
}
.btn-filter:hover {
    background: #003866;
}

/* ===== Date Range Picker (Periode) ===== */
.rp-wrap { position: relative; }
.rp-input {
    width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px;
    font-family: inherit; font-size: 13.5px; background: #fff; cursor: pointer;
    color: #1e293b; box-sizing: border-box; outline: none;
}
.rp-input:focus { border-color: #004B87; }
.rp-input::placeholder { color: #94a3b8; }
.rp-popup {
    display: none; position: absolute; top: calc(100% + 8px); left: 0; z-index: 999;
    background: #fafafa; border-radius: 16px; padding: 22px 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); width: 340px;
}
.rp-popup.open { display: block; }
.rp-month { margin-bottom: 18px; }
.rp-month:last-child { margin-bottom: 0; }
.rp-month-head {
    display: flex; justify-content: space-between; align-items: center;
    font-weight: 600; font-size: 14px; color: #111; margin-bottom: 12px;
}
.rp-nav {
    background: none; border: none; cursor: pointer; font-size: 14px;
    color: #475569; padding: 2px 8px; border-radius: 6px;
}
.rp-nav:hover { background: #e2e8f0; }
.rp-nav.hidden { visibility: hidden; }
.rp-grid { display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 4px; }
.rp-dow { font-size: 10px; color: #b0b7c3; text-align: center; padding-bottom: 6px; }
.rp-day {
    height: 34px; display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: #465563; cursor: pointer; position: relative;
}
.rp-day.empty { cursor: default; }
.rp-day.in-range { background: #dbeafe; }
.rp-day.start { background: linear-gradient(to right, transparent 50%, #dbeafe 50%); }
.rp-day.end { background: linear-gradient(to left, transparent 50%, #dbeafe 50%); }
.rp-day.start.end { background: transparent; }
.rp-day span {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.rp-day:not(.empty):hover span { background: #e2e8f0; }
.rp-day.start span, .rp-day.end span { background: #0b6bdc !important; color: #fff; font-weight: 700; }

/* 6 Kartu KPI Biru JMTO */
.kpi-grid-6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}
.kpi-blue-card {
    background: #004B87;
    border-radius: 14px;
    padding: 22px 24px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 3px 10px rgba(0, 75, 135, 0.14);
}
.kpi-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ffffff;
    flex-shrink: 0;
}
.kpi-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.kpi-label {
    font-size: 13px;
    font-weight: 500;
    color: #e2e8f0;
    margin-bottom: 4px;
}
.kpi-value {
    font-size: 25px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 5px;
    white-space: nowrap;
}
.kpi-sub {
    font-size: 12px;
    color: #cbd5e1;
    white-space: nowrap;
}
.kpi-sub.dot-accent {
    color: #F2C94C;
    font-weight: 700;
}

/* Grid 2 Grafik Analitik (Rasio 40% : 60%) */
.charts-grid-40-60 {
    display: grid;
    grid-template-columns: 4fr 6fr;
    gap: 20px;
    margin-bottom: 24px;
}
.chart-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
    display: flex;
    flex-direction: column;
}
.chart-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    gap: 12px;
}
.chart-card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.chart-card-subtitle {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.chart-switch-group {
    display: inline-flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.switch-btn {
    border: none;
    background: transparent;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}
.switch-btn.active {
    background: #004B87;
    color: #ffffff;
}
.doughnut-wrapper {
    position: relative;
    height: 230px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 4px 0 18px 0;
}
.doughnut-center-label {
    position: absolute;
    text-align: center;
    pointer-events: none;
}
.doughnut-center-label .total-num {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
}
.doughnut-center-label .total-txt {
    font-size: 11.5px;
    color: #64748b;
}
.custom-legend-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px 18px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.legend-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12.5px;
}
.legend-left {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #475569;
    font-weight: 500;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.legend-pct {
    font-weight: 700;
    color: #0f172a;
}
.bar-wrapper {
    position: relative;
    height: 300px;
    width: 100%;
}

@media (max-width: 1024px) {
    .filter-grid,
    .kpi-grid-6 {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-grid-40-60 {
        grid-template-columns: 1fr;
    }
    .main-content {
        max-width: 1120px;
    }
}
</style>
</head>
<body>
<div class="dashboard-wrapper">
<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="fa-solid fa-border-all icon"></i> Dashboard
        </a>
        <div class="menu-group active">
            <div class="menu-header" onclick="toggleDropdown(this)">
                <i class="fa-regular fa-file-lines icon"></i> Laporan
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="submenu">
                <a href="detailTransaksi.php" class="sub-active">Detail Transaksi</a>
                <a href="daftarTransaksi.php">Daftar Transaksi</a>
                <a href="rekapTransaksi.php">Rekap Transaksi</a>
            </div>
        </div>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleDropdown(this)">
                <i class="fa-solid fa-desktop icon"></i> Monitoring
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="submenu">
                <a href="cctv.php">CCTV</a>
                <a href="monitoringArea.php">Perangkat Area</a>
            </div>
        </div>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleDropdown(this)">
                <i class="fa-solid fa-briefcase icon"></i> Manajemen
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="submenu">
                <a href="daftarUser.php">User</a>
                <a href="daftarRole.php">Role</a>
                <a href="daftarMitra.php">Mitra</a>
                <a href="daftarPerangkat.php">Perangkat</a>
                <a href="daftarVenue.php">Venue</a>
            </div>
        </div>
        <a href="../logout.php" class="menu-item logout">
            <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
        </a>
    </nav>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="breadcrumb-text">Laporan</div>
    <h1 class="page-title">Detail Transaksi</h1>

    <!-- BARIS 1: FILTER CARD -->
    <form method="GET" action="detailTransaksi.php" class="filter-card">
        <div class="filter-grid">
            <div class="filter-group">
                <label>Kategori Mitra</label>
                <select name="kategori_mitra">
                    <option value="">Semua Kategori Mitra</option>
                    <option value="Pariwisata" <?= $kategori_mitra === 'Pariwisata' ? 'selected' : '' ?>>Pariwisata</option>
                    <option value="Kawasan" <?= $kategori_mitra === 'Kawasan' ? 'selected' : '' ?>>Kawasan</option>
                    <option value="Event" <?= $kategori_mitra === 'Event' ? 'selected' : '' ?>>Event</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Mitra</label>
                <select name="mitra">
                    <option value="">Semua Mitra</option>
                    <?php if ($q_mitra): while ($m = mysqli_fetch_assoc($q_mitra)): ?>
                        <option value="<?= htmlspecialchars($m['kode_mitra']) ?>" <?= $kode_mitra === $m['kode_mitra'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nama_mitra']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Area</label>
                <select name="area">
                    <option value="">Semua Area</option>
                    <?php if ($q_area): while ($a = mysqli_fetch_assoc($q_area)): ?>
                        <option value="<?= htmlspecialchars($a['kode_area']) ?>" <?= $kode_area === $a['kode_area'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nama_area']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Metode Pembayaran</label>
                <select name="metode">
                    <option value="">Semua Metode Pembayaran</option>
                    <option value="Bank BCA" <?= $metode_bayar === 'Bank BCA' ? 'selected' : '' ?>>Bank BCA</option>
                    <option value="GOERS" <?= $metode_bayar === 'GOERS' ? 'selected' : '' ?>>GOERS</option>
                    <option value="APPSKU" <?= $metode_bayar === 'APPSKU' ? 'selected' : '' ?>>APPSKU</option>
                    <option value="Petugas" <?= $metode_bayar === 'Petugas' ? 'selected' : '' ?>>Petugas</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status Transaksi</label>
                <select name="status">
                    <option value="">Semua Status</option>
                    <option value="Berhasil" <?= $status_trx === 'Berhasil' ? 'selected' : '' ?>>Berhasil</option>
                    <option value="Menunggu Pembayaran" <?= $status_trx === 'Menunggu Pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                    <option value="Gagal" <?= $status_trx === 'Gagal' ? 'selected' : '' ?>>Gagal</option>
                    <option value="Refund" <?= $status_trx === 'Refund' ? 'selected' : '' ?>>Refund</option>
                </select>
            </div>
            <!-- Filter Periode -->
            <div class="filter-group">
                <label>Periode</label>
                <div class="rp-wrap" id="rpWrap">
                    <input type="text" id="rpDisplay" class="rp-input" readonly placeholder="Pilih periode" autocomplete="off">
                    <input type="hidden" name="tgl_awal" id="rpStart" value="<?= htmlspecialchars($tgl_awal) ?>">
                    <input type="hidden" name="tgl_akhir" id="rpEnd" value="<?= htmlspecialchars($tgl_akhir) ?>">
                    <div class="rp-popup" id="rpPopup">
                        <div class="rp-month" id="rpMonth1"></div>
                        <div class="rp-month" id="rpMonth2"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="filter-actions">
            <a href="detailTransaksi.php" class="btn-reset">Reset</a>
            <button type="submit" class="btn-filter">Filter</button>
        </div>
    </form>

    <!-- BARIS 2: 6 KARTU KPI BIRU -->
    <div class="kpi-grid-6">
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-ticket"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Total Transaksi</span>
                <span class="kpi-value"><?= number_format($total_trx, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent">100%</span> dari filter aktif</span>
            </div>
        </div>
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-circle-check"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Transaksi Berhasil</span>
                <span class="kpi-value"><?= number_format($total_berhasil, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent"><?= $pct_berhasil ?>%</span> tingkat sukses</span>
            </div>
        </div>
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-clock"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Menunggu Pembayaran</span>
                <span class="kpi-value"><?= number_format($total_pending, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent"><?= $pct_pending ?>%</span> transaksi pending</span>
            </div>
        </div>
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Transaksi Gagal</span>
                <span class="kpi-value"><?= number_format($total_gagal, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent"><?= $pct_gagal ?>%</span> transaksi gagal</span>
            </div>
        </div>
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-rotate-left"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Transaksi Refund</span>
                <span class="kpi-value"><?= number_format($total_refund, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent"><?= $pct_refund ?>%</span> dikembalikan</span>
            </div>
        </div>
        <div class="kpi-blue-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-wallet"></i></div>
            <div class="kpi-info">
                <span class="kpi-label">Total Nominal</span>
                <span class="kpi-value">Rp <?= number_format($total_nominal, 0, ',', '.') ?></span>
                <span class="kpi-sub"><span class="dot-accent">Terverifikasi</span> transaksi berhasil</span>
            </div>
        </div>
    </div>

    <!-- BARIS 3: 2 KARTU GRAFIK ANALITIK (40% : 60%) -->
    <div class="charts-grid-40-60">
        <!-- GRAFIK KIRI: DOUGHNUT CHART PROPORSI STATUS -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <h3 class="chart-card-title">Status Transaksi</h3>
                    <div class="chart-card-subtitle">Komposisi keberhasilan transaksi gate</div>
                </div>
            </div>
            <div class="doughnut-wrapper">
                <canvas id="statusDoughnutChart"></canvas>
                <div class="doughnut-center-label">
                    <span class="total-num"><?= number_format($total_trx, 0, ',', '.') ?></span>
                    <span class="total-txt">Total Transaksi</span>
                </div>
            </div>
            <div class="custom-legend-grid">
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-dot" style="background: #10B981;"></span>Berhasil</div>
                    <span class="legend-pct"><?= $pct_berhasil ?>%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-dot" style="background: #F2C94C;"></span>Pending</div>
                    <span class="legend-pct"><?= $pct_pending ?>%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-dot" style="background: #EF4444;"></span>Gagal</div>
                    <span class="legend-pct"><?= $pct_gagal ?>%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-dot" style="background: #004B87;"></span>Refund</div>
                    <span class="legend-pct"><?= $pct_refund ?>%</span>
                </div>
            </div>
        </div>

        <!-- GRAFIK KANAN: HORIZONTAL BAR CHART DISTRIBUSI PENERBIT -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <h3 class="chart-card-title">Distribusi Penerbit</h3>
                    <div class="chart-card-subtitle" id="channelSubtitle">Perbandingan volume tiket per penerbit</div>
                </div>
                <div class="chart-switch-group">
                    <button type="button" class="switch-btn active" id="btnVolume" onclick="switchChannelMode('volume')">Volume Tiket</button>
                    <button type="button" class="switch-btn" id="btnNominal" onclick="switchChannelMode('nominal')">Nominal Rp</button>
                </div>
            </div>
            <div class="bar-wrapper">
                <canvas id="channelBarChart"></canvas>
            </div>
        </div>
    </div>
</main>
</div>

<script>
// 1. TOGGLE DROPDOWN SIDEBAR
function toggleDropdown(headerElement) {
    const parentGroup = headerElement.parentElement;
    parentGroup.classList.toggle('active');
}

// 2. DOUGHNUT CHART (PROPORSI STATUS)
const statusData = [<?= $total_berhasil ?>, <?= $total_pending ?>, <?= $total_gagal ?>, <?= $total_refund ?>];
const hasStatusData = statusData.some(val => val > 0);
const ctxDoughnut = document.getElementById('statusDoughnutChart').getContext('2d');
new Chart(ctxDoughnut, {
    type: 'doughnut',
    data: {
        labels: ['Berhasil', 'Menunggu Pembayaran', 'Gagal', 'Refund'],
        datasets: [{
            data: hasStatusData ? statusData : [1],
            backgroundColor: hasStatusData ? ['#10B981', '#F2C94C', '#EF4444', '#004B87'] : ['#E2E8F0'],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: {
                enabled: hasStatusData,
                backgroundColor: '#1e293b',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const val = context.parsed || 0;
                        const total = <?= max($total_trx, 1) ?>;
                        const pct = ((val / total) * 100).toFixed(1);
                        return ` ${context.label}: ${val.toLocaleString('id-ID')} (${pct}%)`;
                    }
                }
            }
        }
    }
});

// 3. HORIZONTAL BAR CHART (DISTRIBUSI PENERBIT)
const channelLabels  = <?= json_encode($channel_labels) ?>;
const channelVolume  = <?= json_encode($channel_volume) ?>;
const channelNominal = <?= json_encode($channel_nominal) ?>;
let currentMode = 'volume';

const ctxBar = document.getElementById('channelBarChart').getContext('2d');
const channelChart = new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: channelLabels,
        datasets: [{
            label: 'Volume Tiket',
            data: channelVolume,
            backgroundColor: '#004B87',
            hoverBackgroundColor: '#003866',
            borderRadius: 6,
            barThickness: 28
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const val = context.parsed.x || 0;
                        if (currentMode === 'nominal') {
                            return ' Nominal: Rp ' + val.toLocaleString('id-ID');
                        }
                        return ' Volume: ' + val.toLocaleString('id-ID') + ' Tiket';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: { color: '#e2e8f0', borderDash: [4, 4] },
                ticks: {
                    color: '#64748b',
                    font: { size: 12 },
                    callback: function(value) {
                        if (currentMode === 'nominal') {
                            if (value >= 1000000) return 'Rp ' + (value / 1000000) + 'jt';
                            if (value >= 1000) return 'Rp ' + (value / 1000) + 'rb';
                            return 'Rp ' + value;
                        }
                        return value.toLocaleString('id-ID');
                    }
                }
            },
            y: {
                grid: { display: false },
                ticks: {
                    color: '#1e293b',
                    font: { size: 13, weight: '600' }
                }
            }
        }
    }
});

// Switch Mode Grafik (Volume Tiket <-> Nominal Rp)
function switchChannelMode(mode) {
    currentMode = mode;
    const btnVolume  = document.getElementById('btnVolume');
    const btnNominal = document.getElementById('btnNominal');
    const subtitle   = document.getElementById('channelSubtitle');

    if (mode === 'volume') {
        btnVolume.classList.add('active');
        btnNominal.classList.remove('active');
        subtitle.textContent = 'Perbandingan volume tiket per penerbit';
        channelChart.data.datasets[0].label = 'Volume Tiket';
        channelChart.data.datasets[0].data = channelVolume;
        channelChart.data.datasets[0].backgroundColor = '#004B87';
        channelChart.data.datasets[0].hoverBackgroundColor = '#003866';
    } else {
        btnNominal.classList.add('active');
        btnVolume.classList.remove('active');
        subtitle.textContent = 'Perbandingan nominal pendapatan terverifikasi per penerbit';
        channelChart.data.datasets[0].label = 'Nominal Rp';
        channelChart.data.datasets[0].data = channelNominal;
        channelChart.data.datasets[0].backgroundColor = '#F2C94C';
        channelChart.data.datasets[0].hoverBackgroundColor = '#e0b636';
    }
    channelChart.update();
}

// 4. DATE RANGE PICKER (PERIODE)
(function () {
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const DOW = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

    const wrap = document.getElementById('rpWrap');
    const popup = document.getElementById('rpPopup');
    const display = document.getElementById('rpDisplay');
    const hidStart = document.getElementById('rpStart');
    const hidEnd = document.getElementById('rpEnd');

    function parseYMD(s) {
        if (!s) return null;
        const p = s.split('-');
        return new Date(+p[0], +p[1] - 1, +p[2]);
    }
    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function toDMY(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    let start = parseYMD(hidStart.value);
    let end = parseYMD(hidEnd.value);
    const base = start || new Date();
    let viewYear = base.getFullYear();
    let viewMonth = base.getMonth();

    function updateDisplay() {
        if (start && end) display.value = toDMY(start) + ' - ' + toDMY(end);
        else if (start) display.value = toDMY(start) + ' - ...';
        else if (end) display.value = '... - ' + toDMY(end);
        else display.value = '';
    }

    function buildMonth(container, year, month, showPrev, showNext) {
        const first = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        let html = '<div class="rp-month-head">';
        html += '<button type="button" class="rp-nav ' + (showPrev ? '' : 'hidden') + '" data-nav="-1">&#10094;</button>';
        html += '<span>' + MONTHS[month] + ' ' + year + '</span>';
        html += '<button type="button" class="rp-nav ' + (showNext ? '' : 'hidden') + '" data-nav="1">&#10095;</button>';
        html += '</div><div class="rp-grid">';

        DOW.forEach(d => html += '<div class="rp-dow">' + d + '</div>');
        for (let i = 0; i < first.getDay(); i++) html += '<div class="rp-day empty"></div>';

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month, d);
            let cls = 'rp-day';
            const t = date.getTime();
            const sT = start ? start.getTime() : null;
            const eT = end ? end.getTime() : null;

            if (sT !== null && t === sT) cls += ' start';
            if (eT !== null && t === eT) cls += ' end';
            if (sT !== null && eT !== null && t > sT && t < eT) cls += ' in-range';
            if (sT !== null && eT === null && t === sT) cls += ' end';

            html += '<div class="' + cls + '" data-date="' + toYMD(date) + '"><span>' + d + '</span></div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function render() {
        const nextMonth = new Date(viewYear, viewMonth + 1, 1);
        buildMonth(document.getElementById('rpMonth1'), viewYear, viewMonth, true, false);
        buildMonth(document.getElementById('rpMonth2'), nextMonth.getFullYear(), nextMonth.getMonth(), false, true);
    }

    function pickDate(date) {
        if (!start || (start && end)) {
            start = date;
            end = null;
        } else {
            if (date.getTime() < start.getTime()) {
                end = start;
                start = date;
            } else {
                end = date;
            }
            popup.classList.remove('open');
        }
        hidStart.value = start ? toYMD(start) : '';
        hidEnd.value = end ? toYMD(end) : '';
        updateDisplay();
        render();
    }

    display.addEventListener('click', function () {
        popup.classList.toggle('open');
    });

    popup.addEventListener('click', function (e) {
        const nav = e.target.closest('[data-nav]');
        if (nav) {
            const step = parseInt(nav.getAttribute('data-nav'));
            const d = new Date(viewYear, viewMonth + step, 1);
            viewYear = d.getFullYear();
            viewMonth = d.getMonth();
            render();
            return;
        }
        const cell = e.target.closest('.rp-day[data-date]');
        if (cell) pickDate(parseYMD(cell.getAttribute('data-date')));
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) popup.classList.remove('open');
    });

    updateDisplay();
    render();
})();
</script>
</body>
</html>