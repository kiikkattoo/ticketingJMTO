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

// Tangkap Parameter Filter GET
$f_area   = $_GET['area'] ?? '';
$f_lokasi = $_GET['lokasi'] ?? '';
$f_periode = $_GET['periode'] ?? '';

// Opsi Dropdown Area & Lokasi
$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");
$opt_lokasi = mysqli_query($conn, "SELECT DISTINCT nama_lokasi FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_lokasi ASC");

// Bangun Klausa WHERE
$where_area = ["a.kode_mitra = '$id_mitra_login'"];
$where_trx  = ["a.kode_mitra = '$id_mitra_login'"];

if (!empty($f_area)) {
    $safe_area = mysqli_real_escape_string($conn, $f_area);
    $where_area[] = "a.nama_area = '$safe_area'";
    $where_trx[]  = "a.nama_area = '$safe_area'";
}
if (!empty($f_lokasi)) {
    $safe_lokasi = mysqli_real_escape_string($conn, $f_lokasi);
    $where_area[] = "a.nama_lokasi = '$safe_lokasi'";
    $where_trx[]  = "a.nama_lokasi = '$safe_lokasi'";
}
if ($f_periode === 'Hari Ini') {
    $where_trx[] = "DATE(t.waktu_transaksi) = CURDATE()";
} elseif ($f_periode === 'Minggu Ini') {
    $where_trx[] = "YEARWEEK(t.waktu_transaksi, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($f_periode === 'Bulan Ini') {
    $where_trx[] = "MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE())";
}

$sql_where_area = "WHERE " . implode(" AND ", $where_area);
$sql_where_trx  = "WHERE " . implode(" AND ", $where_trx);

// Query KPI
$q_trx = mysqli_query($conn, "SELECT 
    COUNT(t.id_transaksi) AS total_trx,
    COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS total_pendapatan
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $sql_where_trx");
$d_trx = ($q_trx) ? mysqli_fetch_assoc($q_trx) : [];
$total_transaksi  = (int)($d_trx['total_trx'] ?? 0);
$total_pendapatan = (float)($d_trx['total_pendapatan'] ?? 0);

$q_area = mysqli_query($conn, "SELECT COUNT(DISTINCT a.nama_gate) AS jml_gate, COUNT(DISTINCT a.nama_area) AS jml_area FROM area_lokasi a $sql_where_area");
$d_area = ($q_area) ? mysqli_fetch_assoc($q_area) : [];
$gate_aktif = (int)($d_area['jml_gate'] ?? 0);
$jml_area   = (int)($d_area['jml_area'] ?? 0);

$q_perangkat = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN p.jenis_perangkat = 'Turnstile' AND p.status_perangkat = 'Aktif' THEN 1 END) AS jml_turnstile,
    COUNT(CASE WHEN p.jenis_perangkat = 'CCTV' AND p.status_perangkat = 'Aktif' THEN 1 END) AS jml_cctv
    FROM perangkat p
    INNER JOIN area_lokasi a ON p.id_area = a.id_area
    $sql_where_area");
$d_perangkat = ($q_perangkat) ? mysqli_fetch_assoc($q_perangkat) : [];
$turnstile_aktif = (int)($d_perangkat['jml_turnstile'] ?? 0);
$cctv_aktif      = (int)($d_perangkat['jml_cctv'] ?? 0);

// Data Grafik Harian (per jam, 00 - 23)
$hourly_traffic = array_fill(0, 24, 0);
$hourly_pendapatan = array_fill(0, 24, 0);
$q_harian = mysqli_query($conn, "SELECT HOUR(t.waktu_transaksi) AS jam_trx, COUNT(t.id_transaksi) AS jml_pengunjung, COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS omzet FROM transaksi t INNER JOIN area_lokasi a ON t.id_area = a.id_area $sql_where_trx GROUP BY HOUR(t.waktu_transaksi)");
if ($q_harian) {
    while ($rh = mysqli_fetch_assoc($q_harian)) {
        $h = (int)$rh['jam_trx'];
        $hourly_traffic[$h] = (int)$rh['jml_pengunjung'];
        $hourly_pendapatan[$h] = (float)$rh['omzet'];
    }
}
$harian_labels = []; $harian_traffic = []; $harian_pendapatan = [];
for ($i = 0; $i <= 23; $i++) {
    $harian_labels[]     = str_pad($i, 2, '0', STR_PAD_LEFT);
    $harian_traffic[]    = $hourly_traffic[$i];
    $harian_pendapatan[] = $hourly_pendapatan[$i];
}

$nama_bulan_id = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulanan_labels = []; $bulanan_traffic = []; $bulanan_pendapatan = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $ym = date('Y-m', $ts);
    $m_num = (int)date('n', $ts);
    $bulanan_labels[] = $nama_bulan_id[$m_num];
    $q_bln = mysqli_query($conn, "SELECT COUNT(t.id_transaksi) AS jml, COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS omzet FROM transaksi t INNER JOIN area_lokasi a ON t.id_area = a.id_area WHERE a.kode_mitra = '$id_mitra_login' AND DATE_FORMAT(t.waktu_transaksi, '%Y-%m') = '$ym'");
    $rb = ($q_bln) ? mysqli_fetch_assoc($q_bln) : [];
    $bulanan_traffic[] = (int)($rb['jml'] ?? 0);
    $bulanan_pendapatan[] = (float)($rb['omzet'] ?? 0);
}

$show_rev = $tema_mitra['show_revenue'] ?? 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Mitra - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== LAYOUT UMUM ===== */
.main-content { max-width: 100%; width: 100%; }

/* ===== KARTU FILTER ===== */
.filter-card {
    background: #ffffff;
    border: 1px solid #e8edf3;
    border-radius: 16px;
    padding: 22px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
}
.filter-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; width: 100%; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 12px; font-weight: 600; color: #475569; letter-spacing: 0.2px; }
.filter-group select {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    background: #f8fafc;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.filter-group select:hover { background: #ffffff; }
.filter-group select:focus {
    border-color: var(--mitra-primer);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.06);
}
.filter-footer-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.filter-footer-row .btn-reset,
.filter-footer-row .btn-filter {
    height: 40px;
    padding: 0 24px;
    border-radius: 10px;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: transform .15s, box-shadow .2s, background .2s;
}
.filter-footer-row .btn-reset { background: #ffffff; border: 1px solid #dbe2ea; color: #475569; }
.filter-footer-row .btn-reset:hover { background: #f1f5f9; }
.filter-footer-row .btn-filter { background: var(--mitra-primer); border: none; color: #ffffff; }
.filter-footer-row .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,0.18); }

/* ===== KARTU STATISTIK =====
   Grid 6 kolom: 3 kartu di baris atas (masing-masing 2 kolom),
   2 kartu di baris bawah (masing-masing 3 kolom) sehingga melebar penuh & rata. */
.stats-grid {
    display: grid !important;
    grid-template-columns: repeat(6, 1fr) !important;
    gap: 20px !important;
    margin-bottom: 24px;
}
.stats-grid .stat-card { grid-column: span 2; }
.stats-grid .stat-card:nth-child(n+4) { grid-column: span 3; }
.stats-grid .stat-card:nth-child(4):last-child { grid-column: span 6; }

.stats-grid .stat-card {
    position: relative;
    overflow: hidden;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 18px !important;
    min-height: 120px !important;
    padding: 22px 26px !important;
    background: linear-gradient(135deg, var(--mitra-primer) 0%, var(--mitra-primer) 55%, rgba(255,255,255,0.12) 160%) !important;
    background-color: var(--mitra-primer) !important;
    border-radius: 18px !important;
    color: #ffffff !important;
    box-shadow: 0 8px 20px rgba(0,0,0,0.14) !important;
    box-sizing: border-box;
    transition: transform .2s ease, box-shadow .2s ease;
}
.stats-grid .stat-card::after {
    content: "";
    position: absolute;
    right: -30px; top: -30px;
    width: 110px; height: 110px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    pointer-events: none;
}
.stats-grid .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(0,0,0,0.2) !important; }

.stats-grid .stat-card .card-icon {
    flex: 0 0 64px !important;
    width: 64px; height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.16);
    border-radius: 16px;
    color: #ffffff !important;
}
.stats-grid .stat-card .card-icon i { font-size: 28px !important; }
.stats-grid .stat-card .card-info {
    display: flex !important;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start !important;
    gap: 8px;
    flex: 1;
    min-width: 0;
    position: relative;
    z-index: 1;
}
.stats-grid .stat-card .card-label {
    font-size: 12px !important;
    font-weight: 500 !important;
    color: rgba(255,255,255,0.85) !important;
    margin: 0 !important;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.stats-grid .stat-card .card-value {
    font-size: clamp(20px, 1.8vw, 28px) !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    margin: 0 !important;
    line-height: 1.15;
    white-space: nowrap;
}

/* Kartu Perangkat Aktif: label di atas, badge Turnstile & CCTV berjajar rapi di bawahnya */
.stats-grid .stat-card .device-sub-badges {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 10px !important;
    margin: 0 !important;
    padding: 0 !important;
    width: auto !important;
    align-self: flex-start !important;
}
.stats-grid .stat-card .device-sub-badges span {
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
    margin: 0 !important;
    padding: 5px 14px 5px 6px;
    background: rgba(255,255,255,0.16) !important;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 600;
    color: #ffffff !important;
    white-space: nowrap;
}
.dev-ic {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: #ffffff;
    color: var(--mitra-primer);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-style: normal;
    flex-shrink: 0;
}
.dev-ic i { color: var(--mitra-primer); }
.dev-ic svg { color: var(--mitra-primer); display: block; }

/* ===== KARTU GRAFIK ===== */
.charts-grid { align-items: stretch; }
.chart-card {
    background: #ffffff;
    border: 1px solid #e8edf3;
    border-radius: 18px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    min-width: 0;
}
.chart-title { font-size: 16px; font-weight: 600; color: #1e293b; margin: 0; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 10px; flex-wrap: wrap; }
.chart-controls-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.chart-card .toggle-buttons { display: inline-flex; align-items: center; gap: 4px; padding: 4px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px; }
.chart-card .toggle-buttons button { border: none; background: transparent; padding: 7px 14px; border-radius: 7px; font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 500; color: #475569; cursor: pointer; transition: 0.2s; }
.chart-card .toggle-buttons button:hover:not(.active) { background: #e2e8f0; }
.chart-card .toggle-buttons button.active { background: var(--mitra-primer); color: #ffffff; font-weight: 600; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
.chart-canvas-wrapper { position: relative; width: 100%; }

/* ===== RESPONSIF ===== */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .stats-grid .stat-card,
    .stats-grid .stat-card:nth-child(n+4) { grid-column: span 1; }
    .stats-grid .stat-card:last-child:nth-child(odd) { grid-column: span 2; }
}
@media (max-width: 992px) {
    .filter-grid-3 { grid-template-columns: 1fr !important; }
    .stats-grid { grid-template-columns: 1fr !important; }
    .stats-grid .stat-card,
    .stats-grid .stat-card:nth-child(n+4),
    .stats-grid .stat-card:last-child:nth-child(odd) { grid-column: span 1; }
    .charts-grid { grid-template-columns: 1fr !important; }
}
</style>
</head>
<body>
<div class="dashboard-wrapper">
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="<?= htmlspecialchars($tema_mitra['logo_portal']); ?>" alt="Logo Venue" class="logo-img" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item active"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
            <div class="submenu">
                <a href="analisisTransaksi.php">Analisis Transaksi</a>
                <a href="daftarTransaksi.php">Daftar Transaksi</a>
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
        <p class="welcome-text">Selamat Datang, <?= htmlspecialchars($nama_mitra_login); ?>!</p>
        <h1 class="page-header-title">Dashboard</h1>
    </header>

    <section class="filter-card">
        <form method="GET" action="dashboard.php">
            <div class="filter-grid-3">
                <div class="filter-group">
                    <label>Area</label>
                    <select name="area">
                        <option value="">Pilih Area</option>
                        <?php if ($opt_area): while ($a = mysqli_fetch_assoc($opt_area)): ?>
                            <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= ($f_area === $a['nama_area']) ? 'selected' : ''; ?>><?= htmlspecialchars($a['nama_area']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Lokasi</label>
                    <select name="lokasi">
                        <option value="">Pilih lokasi</option>
                        <?php if ($opt_lokasi): while ($l = mysqli_fetch_assoc($opt_lokasi)): ?>
                            <option value="<?= htmlspecialchars($l['nama_lokasi']); ?>" <?= ($f_lokasi === $l['nama_lokasi']) ? 'selected' : ''; ?>><?= htmlspecialchars($l['nama_lokasi']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
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
                <a href="dashboard.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                <button type="submit" class="btn-filter">Filter</button>
            </div>
        </form>
    </section>

    <!-- Kartu KPI Sesuai Desain Asli (Grid 3 Kolom: 3 di atas, 2 di bawah) -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="card-icon"><i class="fa-solid fa-ticket"></i></div>
            <div class="card-info">
                <span class="card-label">Total Transaksi</span>
                <h2 class="card-value"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fa-solid fa-torii-gate"></i></div>
            <div class="card-info">
                <span class="card-label">Gate Aktif</span>
                <h2 class="card-value"><?= $gate_aktif; ?></h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fa-solid fa-map-pin"></i></div>
            <div class="card-info">
                <span class="card-label">Area</span>
                <h2 class="card-value"><?= $jml_area; ?></h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fa-solid fa-server"></i></div>
            <div class="card-info">
                <span class="card-label">Perangkat Aktif</span>
                <div class="device-sub-badges">
                    <span><i class="dev-ic"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M4.5 7.2 8.6 4.8 11 6.2V21H4.5z"/><g stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"><line x1="12.6" y1="9.2" x2="21" y2="7.8"/><line x1="12.6" y1="10.6" x2="21" y2="11.6"/><line x1="12.6" y1="12.2" x2="18.6" y2="16.6"/><line x1="12.6" y1="13" x2="15.6" y2="19.4"/></g></svg></i> <?= $turnstile_aktif; ?> Turnstile</span>
                    <span><i class="dev-ic"><i class="fa-solid fa-video"></i></i> <?= $cctv_aktif; ?> CCTV</span>
                </div>
            </div>
        </div>
        <?php if ($show_rev === 1): ?>
        <div class="stat-card">
            <div class="card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="card-info">
                <span class="card-label">Pendapatan</span>
                <h2 class="card-value">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Grafik -->
    <section class="charts-grid" style="grid-template-columns: <?= ($show_rev === 1) ? '1fr 1fr' : '1fr'; ?>; display: grid; gap: 24px;">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Traffic</h3>
                <div class="chart-controls-right">
                    <div class="toggle-buttons">
                        <button type="button" id="btnTrafficHarian" class="active" onclick="switchTraffic('harian')">Harian</button>
                        <button type="button" id="btnTrafficBulanan" onclick="switchTraffic('bulanan')">Bulanan</button>
                    </div>
                </div>
            </div>
            <div class="chart-canvas-wrapper" style="height: 290px;"><canvas id="trafficChart"></canvas></div>
        </div>

        <?php if ($show_rev === 1): ?>
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Pendapatan</h3>
                <div class="chart-controls-right">
                    <div class="toggle-buttons">
                        <button type="button" id="btnOmzetHarian" class="active" onclick="switchOmzet('harian')">Harian</button>
                        <button type="button" id="btnOmzetBulanan" onclick="switchOmzet('bulanan')">Bulanan</button>
                    </div>
                </div>
            </div>
            <div class="chart-canvas-wrapper" style="height: 290px;"><canvas id="pendapatanChart"></canvas></div>
        </div>
        <?php endif; ?>
    </section>
</main>
</div>
<script>
function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }

// Data harian: sumbu X jam 00 - 23. Data bulanan: 6 bulan terakhir.
const harianLabels = <?= json_encode($harian_labels); ?>;
const harianTraffic = <?= json_encode($harian_traffic); ?>;
const harianOmzet = <?= json_encode($harian_pendapatan); ?>;
const bulananLabels = <?= json_encode($bulanan_labels); ?>;
const bulananTraffic = <?= json_encode($bulanan_traffic); ?>;
const bulananOmzet = <?= json_encode($bulanan_pendapatan); ?>;

const COLOR_BAR = '<?= htmlspecialchars($tema_mitra['warna_aksen']); ?>';
// Keterangan tooltip mode harian: jam "10" ditampilkan sebagai "10:00 - 10:59". Label bulanan tidak berubah.
Chart.defaults.plugins.tooltip.callbacks.title = function (items) {
    const lbl = items.length ? String(items[0].label) : '';
    return /^\d{2}$/.test(lbl) ? lbl + ':00 - ' + lbl + ':59' : lbl;
};
const chartScales = {
    x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, font: { size: 11 } } },
    y: { beginAtZero: true, ticks: { precision: 0 } }
};

// Warna lebih tua (sekitar 25% lebih gelap) untuk batang tertinggi. Jika warna bukan hex, dipakai warna aslinya.
function darkenColor(hex, factor) {
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    const r = Math.round(((n >> 16) & 255) * factor);
    const g = Math.round(((n >> 8) & 255) * factor);
    const b = Math.round((n & 255) * factor);
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}
const COLOR_BAR_DARK = darkenColor(COLOR_BAR, 0.75);

const trafficChart = new Chart(document.getElementById('trafficChart').getContext('2d'), {
    type: 'bar',
    data: { labels: harianLabels, datasets: [{ label: 'Pengunjung', data: harianTraffic, backgroundColor: function (ctx) {
        const data = ctx.dataset.data;
        const max = Math.max.apply(null, data);
        return (max > 0 && data[ctx.dataIndex] === max) ? COLOR_BAR_DARK : COLOR_BAR;
    }, borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, scales: chartScales }
});
<?php if ($show_rev === 1): ?>
const pendapatanChart = new Chart(document.getElementById('pendapatanChart').getContext('2d'), {
    type: 'line',
    data: { labels: harianLabels, datasets: [{ label: 'Pendapatan', data: harianOmzet, borderColor: COLOR_BAR, backgroundColor: 'rgba(234,179,8,0.2)', fill: true, tension: 0.3 }] },
    options: { responsive: true, maintainAspectRatio: false, scales: chartScales }
});
<?php endif; ?>

function switchTraffic(mode) {
    document.getElementById('btnTrafficHarian').classList.toggle('active', mode === 'harian');
    document.getElementById('btnTrafficBulanan').classList.toggle('active', mode === 'bulanan');
    trafficChart.data.labels = (mode === 'harian') ? harianLabels : bulananLabels;
    trafficChart.data.datasets[0].data = (mode === 'harian') ? harianTraffic : bulananTraffic;
    trafficChart.update();
}
<?php if ($show_rev === 1): ?>
function switchOmzet(mode) {
    document.getElementById('btnOmzetHarian').classList.toggle('active', mode === 'harian');
    document.getElementById('btnOmzetBulanan').classList.toggle('active', mode === 'bulanan');
    pendapatanChart.data.labels = (mode === 'harian') ? harianLabels : bulananLabels;
    pendapatanChart.data.datasets[0].data = (mode === 'harian') ? harianOmzet : bulananOmzet;
    pendapatanChart.update();
}
<?php endif; ?>
</script>
</body>
</html>