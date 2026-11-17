<?php
session_start();
require '../koneksi.php';
require_once '../includes/load_tema_mitra.php';

// Proteksi Halaman & Role Mitra
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}
if (strtolower($_SESSION["nama_role"] ?? '') !== 'mitra') {
    header("Location: ../admin/dashboard.php");
    exit();
}

$id_mitra_login = mysqli_real_escape_string($conn, $_SESSION['id_mitra'] ?? '');
$nama_mitra_login = $_SESSION['nama_user'] ?? 'Mitra';

// Tangkap Parameter Filter sesuai Desain Asli
$f_area   = $_GET['area'] ?? '';
$f_metode = $_GET['metode_pembayaran'] ?? '';
$f_status = $_GET['status_transaksi'] ?? '';
$f_periode = $_GET['periode'] ?? '';
$search   = $_GET['search'] ?? '';
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) $page = 1;
$offset   = ($page - 1) * $limit;

// Opsi Dropdown
$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");
$opt_metode = mysqli_query($conn, "SELECT DISTINCT metode_pembayaran FROM transaksi t INNER JOIN area_lokasi a ON t.id_area = a.id_area WHERE a.kode_mitra = '$id_mitra_login' AND metode_pembayaran != '' ORDER BY metode_pembayaran ASC");

// Bangun Klausa WHERE
$where_trx = ["a.kode_mitra = '$id_mitra_login'"];

if (!empty($f_area)) {
    $safe_area = mysqli_real_escape_string($conn, $f_area);
    $where_trx[] = "a.nama_area = '$safe_area'";
}
if (!empty($f_metode)) {
    $safe_metode = mysqli_real_escape_string($conn, $f_metode);
    $where_trx[] = "t.metode_pembayaran = '$safe_metode'";
}
if (!empty($f_status)) {
    $safe_status = mysqli_real_escape_string($conn, $f_status);
    $where_trx[] = "t.status_transaksi = '$safe_status'";
}
if ($f_periode === 'Hari Ini') {
    $where_trx[] = "DATE(t.waktu_transaksi) = CURDATE()";
} elseif ($f_periode === 'Minggu Ini') {
    $where_trx[] = "YEARWEEK(t.waktu_transaksi, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($f_periode === 'Bulan Ini') {
    $where_trx[] = "MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE())";
}
if (!empty($search)) {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $where_trx[] = "(t.kode_transaksi LIKE '%$safe_search%' OR t.penerbit LIKE '%$safe_search%' OR t.jenis_tiket LIKE '%$safe_search%')";
}

$sql_where = "WHERE " . implode(" AND ", $where_trx);

// Hitung Total Data untuk Paging
$q_count = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi t INNER JOIN area_lokasi a ON t.id_area = a.id_area $sql_where");
$d_count = mysqli_fetch_assoc($q_count);
$total_data = (int)($d_count['total'] ?? 0);
$total_pages = ceil($total_data / $limit);
if ($total_pages < 1) $total_pages = 1;

// Ambil Data Transaksi dengan Limit & Offset
$query_trx = "SELECT t.*, a.nama_area, a.nama_lokasi FROM transaksi t INNER JOIN area_lokasi a ON t.id_area = a.id_area $sql_where ORDER BY t.waktu_transaksi DESC LIMIT $limit OFFSET $offset";
$result_trx = mysqli_query($conn, $query_trx);

$show_rev = $tema_mitra['show_revenue'] ?? 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Transaksi - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.main-content { max-width: 100%; width: 100%; }
.filter-grid-4 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; width: 100%; margin-bottom: 16px; }
.filter-footer-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.btn-download-csv { display: inline-flex; align-items: center; gap: 8px; background: #15803d; color: #ffffff; padding: 8px 16px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; transition: background 0.2s; }
.btn-download-csv:hover { background: #166534; }
@media (max-width: 992px) { .filter-grid-4 { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="dashboard-wrapper">
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="<?= htmlspecialchars($tema_mitra['logo_portal']); ?>" alt="Logo Venue" class="logo-img" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
        <div class="menu-group active">
            <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <a href="analisisTransaksi.php">Analisis Transaksi</a>
                <a href="daftarTransaksi.php" class="active-sub">Daftar Transaksi</a>
                <a href="rekapTransaksi.php">Rekap Transaksi</a>
            </div>
        </div>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-desktop icon"></i> Monitoring <i class="fa-solid fa-chevron-down arrow"></i></div>
            <div class="submenu"><a href="cctv.php">CCTV</a></div>
        </div>
        <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-header">
        <p class="welcome-text">Laporan</p>
        <h1 class="page-header-title">Daftar Transaksi</h1>
    </header>

    <!-- Filter Card sesuai Desain Asli -->
    <section class="filter-card">
        <form method="GET" action="daftarTransaksi.php">
            <div class="filter-grid-4">
                <div class="filter-group">
                    <label>Area</label>
                    <select name="area">
                        <option value="">Pilih area</option>
                        <?php if ($opt_area): while ($a = mysqli_fetch_assoc($opt_area)): ?>
                            <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= ($f_area === $a['nama_area']) ? 'selected' : ''; ?>><?= htmlspecialchars($a['nama_area']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Metode Pembayaran</label>
                    <select name="metode_pembayaran">
                        <option value="">Pilih metode pembayaran</option>
                        <?php if ($opt_metode): while ($m = mysqli_fetch_assoc($opt_metode)): ?>
                            <option value="<?= htmlspecialchars($m['metode_pembayaran']); ?>" <?= ($f_metode === $m['metode_pembayaran']) ? 'selected' : ''; ?>><?= htmlspecialchars($m['metode_pembayaran']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status Transaksi</label>
                    <select name="status_transaksi">
                        <option value="">Pilih status transaksi</option>
                        <option value="Berhasil" <?= ($f_status === 'Berhasil') ? 'selected' : ''; ?>>Berhasil</option>
                        <option value="Pending" <?= ($f_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Gagal" <?= ($f_status === 'Gagal') ? 'selected' : ''; ?>>Gagal</option>
                    </select>
                </div>
            </div>
            <div class="filter-grid-4" style="grid-template-columns: 1fr;">
                <div class="filter-group">
                    <label>Periode</label>
                    <select name="periode">
                        <option value="">Pilih periode</option>
                        <option value="Hari Ini" <?= ($f_periode === 'Hari Ini') ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="Minggu Ini" <?= ($f_periode === 'Minggu Ini') ? 'selected' : ''; ?>>Minggu Ini</option>
                        <option value="Bulan Ini" <?= ($f_periode === 'Bulan Ini') ? 'selected' : ''; ?>>Bulan Ini</option>
                    </select>
                </div>
            </div>
            <div class="filter-footer-row">
                <a href="daftarTransaksi.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                <button type="submit" class="btn-filter">Filter</button>
            </div>
        </form>
    </section>

    <!-- Tabel Section dengan Tombol Download CSV & Pagination -->
    <section class="table-card" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:14px; padding:24px; margin-top:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">Daftar Transaksi</h3>
                <form method="GET" action="daftarTransaksi.php" style="margin:0; display:inline-flex; align-items:center; gap:6px;">
                    <select name="limit" onchange="this.form.submit()" style="height:34px; padding:0 8px; border:1px solid #cbd5e1; border-radius:6px; font-family:'Poppins',sans-serif; font-size:11px;">
                        <option value="10" <?= ($limit === 10) ? 'selected' : ''; ?>>10/page</option>
                        <option value="25" <?= ($limit === 25) ? 'selected' : ''; ?>>25/page</option>
                        <option value="50" <?= ($limit === 50) ? 'selected' : ''; ?>>50/page</option>
                    </select>
                    <?php foreach ($_GET as $k => $v): if ($k !== 'limit' && $k !== 'page'): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k); ?>" value="<?= htmlspecialchars($v); ?>">
                    <?php endif; endforeach; ?>
                </form>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <form method="GET" action="daftarTransaksi.php" style="margin:0;">
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search); ?>" style="height:36px; padding:0 12px; border:1px solid #cbd5e1; border-radius:7px; font-family:'Poppins',sans-serif; font-size:12px; width:200px;">
                    <?php foreach ($_GET as $k => $v): if ($k !== 'search' && $k !== 'page'): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k); ?>" value="<?= htmlspecialchars($v); ?>">
                    <?php endif; endforeach; ?>
                </form>
                <a href="#" class="btn-download-csv"><i class="fa-solid fa-file-excel"></i> Download CSV</a>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead>
                    <tr style="background:var(--mitra-primer); color:#ffffff;">
                        <th style="padding:12px 14px; text-align:center;">No</th>
                        <th style="padding:12px 14px; text-align:left;">Kode Transaksi</th>
                        <th style="padding:12px 14px; text-align:left;">Waktu Transaksi</th>
                        <th style="padding:12px 14px; text-align:left;">Penerbit</th>
                        <th style="padding:12px 14px; text-align:left;">Kategori Mitra</th>
                        <th style="padding:12px 14px; text-align:left;">Jenis Tiket</th>
                        <th style="padding:12px 14px; text-align:left;">Metode Pembayaran</th>
                        <?php if ($show_rev === 1): ?>
                        <th style="padding:12px 14px; text-align:right;">Nominal</th>
                        <?php endif; ?>
                        <th style="padding:12px 14px; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_trx && mysqli_num_rows($result_trx) > 0): ?>
                        <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($result_trx)): ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:12px 14px; text-align:center;"><?= $no++; ?></td>
                            <td style="padding:12px 14px; font-weight:600;"><?= htmlspecialchars($row['kode_transaksi']); ?></td>
                            <td style="padding:12px 14px;"><?= htmlspecialchars($row['waktu_transaksi']); ?></td>
                            <td style="padding:12px 14px;"><?= htmlspecialchars($row['penerbit']); ?></td>
                            <td style="padding:12px 14px;"><?= htmlspecialchars($row['nama_area'] ?? 'Wisata & Rekreasi'); ?></td>
                            <td style="padding:12px 14px;"><?= htmlspecialchars($row['jenis_tiket']); ?></td>
                            <td style="padding:12px 14px;"><?= htmlspecialchars($row['metode_pembayaran'] !== '' ? $row['metode_pembayaran'] : '-'); ?></td>
                            <?php if ($show_rev === 1): ?>
                            <td style="padding:12px 14px; text-align:right; font-weight:600;">Rp <?= number_format($row['nominal'], 0, ',', '.'); ?></td>
                            <?php endif; ?>
                            <td style="padding:12px 14px; text-align:center;">
                                <span style="padding:4px 12px; border-radius:99px; font-size:11px; font-weight:600; background:<?= ($row['status_transaksi'] === 'Berhasil') ? '#dcfce7; color:#15803d;' : (($row['status_transaksi'] === 'Pending') ? '#fef9c3; color:#a16207;' : '#fee2e2; color:#991b1b;'); ?>">
                                    <?= htmlspecialchars($row['status_transaksi']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($show_rev === 1) ? '9' : '8'; ?>" style="padding:32px; text-align:center; color:#64748b;">Belum ada data transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div style="display:flex; justify-content:flex-end; align-items:center; margin-top:18px; gap:6px;">
            <?php 
            // Buat query string untuk pagination
            $query_params = $_GET;
            unset($query_params['page']);
            $qs = http_build_query($query_params);
            $qs = $qs ? '&' . $qs : '';
            ?>
            <a href="?page=<?= max(1, $page - 1); ?><?= $qs; ?>" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; color:#334155; text-decoration:none;"><i class="fa-solid fa-chevron-left"></i></a>
            <span style="padding:0 10px; font-size:12px; font-weight:600; color:var(--mitra-primer); background:#f1f5f9; height:32px; display:inline-flex; align-items:center; border-radius:6px; border:1px solid #cbd5e1;"><?= $page; ?></span>
            <a href="?page=<?= min($total_pages, $page + 1); ?><?= $qs; ?>" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; color:#334155; text-decoration:none;"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </section>
</main>
</div>
<script>
function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }
</script>
</body>
</html>