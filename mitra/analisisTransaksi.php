<?php 
session_start();
require '../koneksi.php';
include '../includes/load_tema_mitra.php'; 

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}
if (strtolower($_SESSION["nama_role"] ?? '') !== 'mitra') {
    header("Location: ../admin/detailTransaksi.php");
    exit();
}

$id_mitra_login = mysqli_real_escape_string($conn, $_SESSION['id_mitra'] ?? '');

$f_area    = $_GET['area'] ?? '';
$f_metode  = $_GET['metode'] ?? '';
$f_status  = $_GET['status'] ?? '';
$f_periode = $_GET['periode'] ?? '';

$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");

$where = ["a.kode_mitra = '$id_mitra_login'"];

if (!empty($f_area)) {
    $safe_area = mysqli_real_escape_string($conn, $f_area);
    $where[] = "a.nama_area = '$safe_area'";
}
if (!empty($f_metode)) {
    $safe_metode = mysqli_real_escape_string($conn, $f_metode);
    $where[] = "t.penerbit = '$safe_metode'";
}
if (!empty($f_status)) {
    $safe_status = mysqli_real_escape_string($conn, $f_status);
    $where[] = "t.status_transaksi = '$safe_status'";
}
if ($f_periode === 'Hari Ini') {
    $where[] = "DATE(t.waktu_transaksi) = CURDATE()";
} elseif ($f_periode === 'Minggu Ini') {
    $where[] = "YEARWEEK(t.waktu_transaksi, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($f_periode === 'Bulan Ini') {
    $where[] = "MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE())";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$query_kpi = "SELECT 
    COUNT(t.id_transaksi) AS total_trx,
    COUNT(CASE WHEN t.status_transaksi = 'Berhasil' THEN 1 END) AS jml_berhasil,
    COUNT(CASE WHEN t.status_transaksi = 'Pending' OR t.status_transaksi = 'Menunggu Pembayaran' THEN 1 END) AS jml_pending,
    COUNT(CASE WHEN t.status_transaksi = 'Gagal' THEN 1 END) AS jml_gagal,
    COUNT(CASE WHEN t.status_transaksi = 'Refund' THEN 1 END) AS jml_refund,
    COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS total_nominal
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $where_sql";

$res_kpi = mysqli_query($conn, $query_kpi);
$kpi = ($res_kpi) ? mysqli_fetch_assoc($res_kpi) : [];

$total_trx     = (int)($kpi['total_trx'] ?? 0);
$jml_berhasil  = (int)($kpi['jml_berhasil'] ?? 0);
$jml_pending   = (int)($kpi['jml_pending'] ?? 0);
$jml_gagal     = (int)($kpi['jml_gagal'] ?? 0);
$jml_refund    = (int)($kpi['jml_refund'] ?? 0);
$total_nominal = (float)($kpi['total_nominal'] ?? 0);

$pct_berhasil = ($total_trx > 0) ? round(($jml_berhasil / $total_trx) * 100, 2) : 0;
$pct_pending  = ($total_trx > 0) ? round(($jml_pending / $total_trx) * 100, 2) : 0;
$pct_gagal    = ($total_trx > 0) ? round(($jml_gagal / $total_trx) * 100, 2) : 0;
$pct_refund   = ($total_trx > 0) ? round(($jml_refund / $total_trx) * 100, 2) : 0;

$channels = ['Bank DKI', 'GOERS', 'APPSKU', 'Petugas'];
$channel_counts = [0, 0, 0, 0];

$query_channel = "SELECT 
    t.penerbit, 
    COUNT(t.id_transaksi) AS jml 
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $where_sql
    GROUP BY t.penerbit";

$res_channel = mysqli_query($conn, $query_channel);
if ($res_channel) {
    while ($ch = mysqli_fetch_assoc($res_channel)) {
        $idx = array_search($ch['penerbit'], $channels);
        if ($idx !== false) {
            $channel_counts[$idx] = (int)$ch['jml'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Transaksi Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content { max-width: 100%; width: 100%; }
        .filter-grid-2row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .filter-bottom-bar { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
        .filter-bottom-bar .filter-group { flex: 0 0 calc(33.333% - 11px); min-width: 220px; }
        .filter-actions-right { display: flex; gap: 10px; margin-left: auto; }
        .stats-grid-3col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .donut-legend-custom { display: flex; justify-content: center; flex-wrap: wrap; gap: 16px; margin-top: 14px; font-size: 12px; color: #444; }
        .donut-legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        @media (max-width: 992px) {
            .filter-grid-2row, .stats-grid-3col { grid-template-columns: 1fr; }
            .filter-bottom-bar .filter-group { flex: 1 1 100%; }
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
            <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
            <div class="menu-group active">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-regular fa-file-lines icon"></i> Laporan
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="detailTransaksi.php" class="active-sub">Analisis Transaksi</a>
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
                </div>
            </div>
            <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <p class="welcome-text">Laporan</p>
            <h1 class="page-header-title">Analisis Transaksi</h1>
        </header>

        <section class="filter-card">
            <form method="GET" action="detailTransaksi.php">
                <div class="filter-grid-2row">
                    <div class="filter-group">
                        <label>Area</label>
                        <select name="area">
                            <option value="">Semua Area</option>
                            <?php if ($opt_area): while ($a = mysqli_fetch_assoc($opt_area)): ?>
                                <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= ($f_area === $a['nama_area']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($a['nama_area']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Metode Pembayaran</label>
                        <select name="metode">
                            <option value="">Semua Metode</option>
                            <?php foreach ($channels as $ch_opt): ?>
                                <option value="<?= $ch_opt; ?>" <?= ($f_metode === $ch_opt) ? 'selected' : ''; ?>><?= $ch_opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status Transaksi</label>
                        <select name="status">
                            <option value="">Semua Status</option>
                            <option value="Berhasil" <?= ($f_status === 'Berhasil') ? 'selected' : ''; ?>>Berhasil</option>
                            <option value="Pending" <?= ($f_status === 'Pending') ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                            <option value="Gagal" <?= ($f_status === 'Gagal') ? 'selected' : ''; ?>>Gagal</option>
                            <option value="Refund" <?= ($f_status === 'Refund') ? 'selected' : ''; ?>>Refund</option>
                        </select>
                    </div>
                </div>
                <div class="filter-bottom-bar">
                    <div class="filter-group">
                        <label>Periode</label>
                        <select name="periode">
                            <option value="">Semua Periode</option>
                            <option value="Hari Ini" <?= ($f_periode === 'Hari Ini') ? 'selected' : ''; ?>>Hari Ini</option>
                            <option value="Minggu Ini" <?= ($f_periode === 'Minggu Ini') ? 'selected' : ''; ?>>Minggu Ini</option>
                            <option value="Bulan Ini" <?= ($f_periode === 'Bulan Ini') ? 'selected' : ''; ?>>Bulan Ini</option>
                        </select>
                    </div>
                    <div class="filter-actions-right">
                        <a href="detailTransaksi.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                        <button type="submit" class="btn-filter">Filter</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="stats-grid-3col">
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-ticket"></i></div>
                <div class="card-info">
                    <span class="card-label">Total Transaksi</span>
                    <h2 class="card-value"><?= number_format($total_trx, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-chart-simple"></i> 100% total volume</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-torii-gate"></i></div>
                <div class="card-info">
                    <span class="card-label">Transaksi berhasil</span>
                    <h2 class="card-value"><?= number_format($jml_berhasil, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> <?= $pct_berhasil; ?>% dari total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-regular fa-clock"></i></div>
                <div class="card-info">
                    <span class="card-label">Menunggu pembayaran</span>
                    <h2 class="card-value"><?= number_format($jml_pending, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> <?= $pct_pending; ?>% dari total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-regular fa-circle-xmark"></i></div>
                <div class="card-info">
                    <span class="card-label">Transaksi gagal</span>
                    <h2 class="card-value"><?= number_format($jml_gagal, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> <?= $pct_gagal; ?>% dari total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-rotate-left"></i></div>
                <div class="card-info">
                    <span class="card-label">Transaksi refund</span>
                    <h2 class="card-value"><?= number_format($jml_refund, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> <?= $pct_refund; ?>% dari total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <div class="card-info">
                    <span class="card-label">Total nominal</span>
                    <h2 class="card-value">Rp <?= number_format($total_nominal, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-check-circle"></i> Transaksi berhasil</span>
                </div>
            </div>
        </section>

        <section class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-chart-pie"></i></span>
                        <h3 class="chart-title">Proporsi Status Transaksi</h3>
                    </div>
                </div>
                <div class="chart-canvas-wrapper" style="height: 270px; position: relative;">
                    <canvas id="statusDonutChart"></canvas>
                </div>
                <div class="donut-legend-custom">
                    <div class="donut-legend-item"><span class="legend-dot" style="background:#00A86B;"></span> berhasil (<?= $pct_berhasil; ?>%)</div>
                    <div class="donut-legend-item"><span class="legend-dot" style="background:#F2C94C;"></span> pending (<?= $pct_pending; ?>%)</div>
                    <div class="donut-legend-item"><span class="legend-dot" style="background:#EB5757;"></span> gagal (<?= $pct_gagal; ?>%)</div>
                    <div class="donut-legend-item"><span class="legend-dot" style="background:#1D3557;"></span> refund (<?= $pct_refund; ?>%)</div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <h3 class="chart-title">Transaksi per Metode Pembayaran</h3>
                    </div>
                </div>
                <div class="chart-canvas-wrapper" style="height: 300px;">
                    <canvas id="channelBarChart"></canvas>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
    function toggleDropdown(element) {
        const currentGroup = element.parentElement;
        document.querySelectorAll('.menu-group').forEach(group => {
            if (group !== currentGroup) group.classList.remove('active');
        });
        currentGroup.classList.toggle('active');
    }

    const centerTextPlugin = {
        id: 'centerText',
        afterDraw(chart) {
            const { ctx, chartArea: { left, right, top, bottom } } = chart;
            ctx.save();
            ctx.font = '600 18px Poppins, sans-serif';
            ctx.fillStyle = '#444444';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('<?= number_format($total_trx, 0, ',', '.'); ?>', (left + right) / 2, (top + bottom) / 2);
            ctx.restore();
        }
    };

    new Chart(document.getElementById('statusDonutChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Berhasil', 'Pending', 'Gagal', 'Refund'],
            datasets: [{
                data: [<?= $jml_berhasil; ?>, <?= $jml_pending; ?>, <?= $jml_gagal; ?>, <?= $jml_refund; ?>],
                backgroundColor: ['#00A86B', '#F2C94C', '#EB5757', '#1D3557'],
                borderWidth: 2,
                borderColor: '#ffffff',
                cutout: '68%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = <?= max($total_trx, 1); ?>;
                            const val = ctx.parsed;
                            return ` ${ctx.label}: ${val.toLocaleString('id-ID')} transaksi (${((val / total) * 100).toFixed(2)}%)`;
                        }
                    }
                }
            }
        },
        plugins: [centerTextPlugin]
    });

    new Chart(document.getElementById('channelBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($channels); ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= json_encode($channel_counts); ?>,
                backgroundColor: '#7B74F5',
                hoverBackgroundColor: '#5E56E8',
                borderRadius: 4,
                barThickness: 24
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', align: 'start', labels: { boxWidth: 12 } }
            },
            scales: {
                x: { beginAtZero: true, position: 'top', grid: { color: '#f0f0f0' } },
                y: { grid: { display: false } }
            }
        }
    });
</script>
</body>
</html>

