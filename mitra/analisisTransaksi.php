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
        /* ===== LAYOUT UMUM ===== */
        .main-content { max-width: 100%; width: 100%; }

        /* ===== KARTU FILTER =====
           Baris 1: Area | Metode | Status
           Baris 2: Periode (kolom 1) ........ Reset + Filter (rata kanan, sejajar kolom 3) */
        .filter-card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 16px;
            padding: 26px 28px;
            margin-bottom: 26px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .filter-grid-2row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px 22px; }
        .filter-bottom-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px 22px;
            align-items: end;
            margin-top: 18px;
        }
        .filter-bottom-bar .filter-group { grid-column: 1; min-width: 0; flex: none; }
        .filter-actions-right { grid-column: 3; display: flex; justify-content: flex-end; gap: 12px; margin-left: 0; }

        .filter-group { display: flex; flex-direction: column; gap: 7px; }
        .filter-group label { font-size: 13px; font-weight: 500; color: #475569; }
        .filter-group select {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            background: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #334155;
            outline: none;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
        }
        .filter-group select:focus {
            border-color: var(--mitra-primer, #003f7f);
            box-shadow: 0 0 0 3px rgba(0, 63, 127, 0.10);
        }
        .filter-actions-right .btn-reset,
        .filter-actions-right .btn-filter {
            height: 44px;
            min-width: 100px;
            padding: 0 26px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            justify-content: center;
            transition: transform .15s, box-shadow .2s, background .2s;
        }
        .filter-actions-right .btn-reset { background: #ffffff; border: 1.5px solid var(--mitra-primer, #003f7f); color: #0f172a; }
        .filter-actions-right .btn-reset:hover { background: #f1f5f9; }
        .filter-actions-right .btn-filter { background: var(--mitra-primer, #003f7f); border: 1.5px solid var(--mitra-primer, #003f7f); color: #ffffff; }
        .filter-actions-right .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,0.2); }

        /* ===== KARTU STATISTIK (3 x 2) ===== */
        .stats-grid-3col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 28px; }
        .stats-grid-3col .stat-card {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 18px;
            min-height: 124px;
            padding: 20px 26px;
            background: var(--mitra-primer, #003f7f);
            border-radius: 18px;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.14);
            box-sizing: border-box;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .stats-grid-3col .stat-card::after {
            content: "";
            position: absolute;
            right: -34px; top: -34px;
            width: 120px; height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            pointer-events: none;
        }
        .stats-grid-3col .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(0,0,0,0.2); }
        .stats-grid-3col .stat-card .card-icon {
            flex: 0 0 64px;
            width: 64px; height: 64px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.14);
            border-radius: 16px;
            color: #ffffff;
        }
        .stats-grid-3col .stat-card .card-icon i { font-size: 28px; color: #ffffff; }
        .stats-grid-3col .stat-card .card-info { display: flex; flex-direction: column; justify-content: center; gap: 4px; flex: 1; min-width: 0; }
        .stats-grid-3col .stat-card .card-label { font-size: 13px; font-weight: 600; color: #ffffff; margin: 0; }
        .stats-grid-3col .stat-card .card-value {
            font-size: clamp(22px, 2vw, 30px);
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            line-height: 1.15;
            white-space: nowrap;
        }
        .stats-grid-3col .stat-card .card-trend { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.88); }

        /* ===== KARTU GRAFIK ===== */
        .charts-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px; align-items: stretch; }
        .chart-card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 18px;
            padding: 22px 24px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
            min-width: 0;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .chart-title-group { display: flex; align-items: center; gap: 10px; }
        .chart-title-icon {
            width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 10px;
            background: #eef3fa;
            color: var(--mitra-primer, #003f7f);
            font-size: 15px;
        }
        .chart-title { font-size: 16px; font-weight: 600; color: #1e293b; margin: 0; }
        .chart-canvas-wrapper { position: relative; width: 100%; }

        .donut-legend-custom { display: flex; justify-content: center; flex-wrap: wrap; gap: 18px; margin-top: 16px; font-size: 12px; color: #475569; }
        .donut-legend-item { display: flex; align-items: center; gap: 6px; text-transform: capitalize; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }

        /* ===== RESPONSIF ===== */
        @media (max-width: 1200px) {
            .stats-grid-3col { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 992px) {
            .filter-grid-2row, .filter-bottom-bar { grid-template-columns: 1fr; }
            .filter-bottom-bar .filter-group, .filter-actions-right { grid-column: 1; }
            .filter-actions-right { justify-content: stretch; }
            .filter-actions-right .btn-reset, .filter-actions-right .btn-filter { flex: 1; }
            .stats-grid-3col { grid-template-columns: 1fr; }
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