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

// Data Grafik
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
$tgl_label = date('d/m');
$shift_ranges = ['pagi' => [0, 12], 'siang' => [13, 18], 'malam' => [19, 23]];
$shift_data = [];
foreach ($shift_ranges as $nama_shift => $range) {
    $lbl = []; $trf = []; $pen = [];
    for ($i = $range[0]; $i <= $range[1]; $i++) {
        $lbl[] = [$tgl_label, str_pad($i, 2, '0', STR_PAD_LEFT) . ':00'];
        $trf[] = $hourly_traffic[$i];
        $pen[] = $hourly_pendapatan[$i];
    }
    $shift_data[$nama_shift] = ['labels' => $lbl, 'traffic' => $trf, 'pendapatan' => $pen];
}
$jam_now = (int)date('G');
$default_shift = ($jam_now <= 12) ? 'pagi' : (($jam_now <= 18) ? 'siang' : 'malam');

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
.main-content { max-width: 100%; width: 100%; }
.filter-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; width: 100%; }
.filter-footer-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 10px; flex-wrap: wrap; }
.chart-controls-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.chart-card .toggle-buttons, .chart-card .shift-toggle-buttons { display: inline-flex; align-items: center; gap: 4px; padding: 4px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; }
.chart-card .toggle-buttons button, .chart-card .shift-toggle-buttons button { border: none; background: transparent; padding: 7px 16px; border-radius: 7px; font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 500; color: #475569; cursor: pointer; transition: 0.2s; }
.chart-card .toggle-buttons button.active, .chart-card .shift-toggle-buttons button.active { background: var(--mitra-primer); color: #ffffff; font-weight: 600; }

/* PERBAIKAN GRID KARTU STATISTIK SESUAI DESAIN ASLI (3 DI ATAS, 2 DI BAWAH) */
.stats-grid { 
    display: grid !important; 
    grid-template-columns: repeat(3, 1fr) !important; 
    gap: 22px !important; 
    margin-bottom: 24px; 
}
.stats-grid .stat-card { 
    display: flex !important; 
    flex-direction: row !important; 
    align-items: center !important; 
    gap: 14px !important; 
    min-height: 130px !important; 
    padding: 20px 24px !important; 
    background: var(--mitra-primer) !important; 
    border-radius: 16px !important; 
    color: #ffffff !important; 
    box-shadow: 0 6px 14px rgba(0,0,0,0.12) !important; 
    box-sizing: border-box; 
}
.stats-grid .stat-card .card-icon { 
    flex: 0 0 32% !important; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #ffffff !important; 
}
.stats-grid .stat-card .card-icon i { font-size: 42px !important; }
.stats-grid .stat-card .card-info { display: flex !important; flex-direction: column; justify-content: center; gap: 4px; flex: 1; }
.stats-grid .stat-card .card-label { font-size: 13px !important; font-weight: 600 !important; color: #ffffff !important; margin: 0 !important; }
.stats-grid .stat-card .card-value { font-size: clamp(18px, 1.4vw, 24px) !important; font-weight: 700 !important; color: #ffffff !important; margin: 0 !important; white-space: nowrap; }
.device-sub-badges span { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: #e3ebf5; }
.dev-ic { width: 20px; height: 20px; border-radius: 50%; background: #ffffff; color: var(--mitra-primer); display: inline-flex; align-items: center; justify-content: center; font-size: 10px; }
@media (max-width: 992px) { .filter-grid-3, .stats-grid { grid-template-columns: 1fr !important; } }
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
                    <span><i class="dev-ic"><i class="fa-solid fa-bars"></i></i> <?= $turnstile_aktif; ?> Turnstile</span>
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
                    <div class="shift-toggle-buttons" id="trafficShiftGroup">
                        <button type="button" class="<?= ($default_shift === 'pagi') ? 'active' : ''; ?>" onclick="switchTrafficShift('pagi', this)">Pagi</button>
                        <button type="button" class="<?= ($default_shift === 'siang') ? 'active' : ''; ?>" onclick="switchTrafficShift('siang', this)">Siang</button>
                        <button type="button" class="<?= ($default_shift === 'malam') ? 'active' : ''; ?>" onclick="switchTrafficShift('malam', this)">Malam</button>
                    </div>
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
                    <div class="shift-toggle-buttons" id="omzetShiftGroup">
                        <button type="button" class="<?= ($default_shift === 'pagi') ? 'active' : ''; ?>" onclick="switchOmzetShift('pagi', this)">Pagi</button>
                        <button type="button" class="<?= ($default_shift === 'siang') ? 'active' : ''; ?>" onclick="switchOmzetShift('siang', this)">Siang</button>
                        <button type="button" class="<?= ($default_shift === 'malam') ? 'active' : ''; ?>" onclick="switchOmzetShift('malam', this)">Malam</button>
                    </div>
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
const shiftDatasets = <?= json_encode($shift_data); ?>;
let trafficMode = 'harian', omzetMode = 'harian';
let trafficShift = '<?= $default_shift; ?>', omzetShift = '<?= $default_shift; ?>';
const bulananLabels = <?= json_encode($bulanan_labels); ?>;
const bulananTraffic = <?= json_encode($bulanan_traffic); ?>;
const bulananOmzet = <?= json_encode($bulanan_pendapatan); ?>;

const COLOR_BAR = '<?= htmlspecialchars($tema_mitra['warna_aksen']); ?>';
const trafficChart = new Chart(document.getElementById('trafficChart').getContext('2d'), {
    type: 'bar',
    data: { labels: shiftDatasets[trafficShift].labels, datasets: [{ label: 'Pengunjung', data: shiftDatasets[trafficShift].traffic, backgroundColor: COLOR_BAR, borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false }
});
<?php if ($show_rev === 1): ?>
const pendapatanChart = new Chart(document.getElementById('pendapatanChart').getContext('2d'), {
    type: 'line',
    data: { labels: shiftDatasets[omzetShift].labels, datasets: [{ label: 'Pendapatan', data: shiftDatasets[omzetShift].pendapatan, borderColor: COLOR_BAR, backgroundColor: 'rgba(234,179,8,0.2)', fill: true }] },
    options: { responsive: true, maintainAspectRatio: false }
});
<?php endif; ?>

function switchTraffic(mode) {
    trafficMode = mode;
    document.getElementById('btnTrafficHarian').classList.toggle('active', mode === 'harian');
    document.getElementById('btnTrafficBulanan').classList.toggle('active', mode === 'bulanan');
    trafficChart.data.labels = (mode === 'harian') ? shiftDatasets[trafficShift].labels : bulananLabels;
    trafficChart.data.datasets[0].data = (mode === 'harian') ? shiftDatasets[trafficShift].traffic : bulananTraffic;
    document.getElementById('trafficShiftGroup').style.display = (mode === 'harian') ? 'inline-flex' : 'none';
    trafficChart.update();
}
function switchTrafficShift(shift, btn) {
    trafficShift = shift;
    btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    trafficChart.data.labels = shiftDatasets[trafficShift].labels;
    trafficChart.data.datasets[0].data = shiftDatasets[trafficShift].traffic;
    trafficChart.update();
}
</script>
</body>
</html>