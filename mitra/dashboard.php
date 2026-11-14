<?php
session_start();
require '../koneksi.php';

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
$f_area    = $_GET['area'] ?? '';
$f_lokasi  = $_GET['lokasi'] ?? '';
$f_periode = $_GET['periode'] ?? '';

// 1. Opsi Dropdown Area & Lokasi (Terkunci ke kode_mitra)
$opt_area   = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");
$opt_lokasi = mysqli_query($conn, "SELECT DISTINCT nama_lokasi FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_lokasi ASC");

// 2. Bangun Klausa WHERE Area & Transaksi
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

// 3. Query KPI Total Transaksi & Pendapatan
$q_trx = mysqli_query($conn, "SELECT 
    COUNT(t.id_transaksi) AS total_trx,
    COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS total_pendapatan
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $sql_where_trx");
$d_trx = ($q_trx) ? mysqli_fetch_assoc($q_trx) : [];

$total_transaksi  = (int)($d_trx['total_trx'] ?? 0);
$total_pendapatan = (float)($d_trx['total_pendapatan'] ?? 0);

// 4. Query KPI Gate Aktif & Jumlah Area
$q_area = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT a.nama_gate) AS jml_gate,
    COUNT(DISTINCT a.nama_area) AS jml_area
    FROM area_lokasi a
    $sql_where_area");
$d_area = ($q_area) ? mysqli_fetch_assoc($q_area) : [];

$gate_aktif = (int)($d_area['jml_gate'] ?? 0);
$jml_area   = (int)($d_area['jml_area'] ?? 0);

// 5. Query KPI Perangkat Aktif (Turnstile & CCTV)
$q_perangkat = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN p.jenis_perangkat = 'Turnstile' AND p.status_perangkat = 'Aktif' THEN 1 END) AS jml_turnstile,
    COUNT(CASE WHEN p.jenis_perangkat = 'CCTV' AND p.status_perangkat = 'Aktif' THEN 1 END) AS jml_cctv
    FROM perangkat p
    INNER JOIN area_lokasi a ON p.id_area = a.id_area
    $sql_where_area");
$d_perangkat = ($q_perangkat) ? mysqli_fetch_assoc($q_perangkat) : [];

$turnstile_aktif = (int)($d_perangkat['jml_turnstile'] ?? 0);
$cctv_aktif      = (int)($d_perangkat['jml_cctv'] ?? 0);

// 6. Data Grafik Harian Per Jam (08:00 - 20:00 sesuai tampilan Figma dd/mm HH:00)
$tgl_label = date('d/m');
$jam_slots = ['08', '10', '12', '14', '16', '18', '20'];
$harian_labels = [];
$harian_traffic = [];
$harian_pendapatan = [];

foreach ($jam_slots as $j) {
    $harian_labels[] = [$tgl_label, "$j:00"];
    $harian_traffic[$j] = 0;
    $harian_pendapatan[$j] = 0;
}

$q_harian = mysqli_query($conn, "SELECT 
    LPAD(HOUR(t.waktu_transaksi), 2, '0') AS jam_trx,
    COUNT(t.id_transaksi) AS jml_pengunjung,
    COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS omzet
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $sql_where_trx
    GROUP BY jam_trx");

if ($q_harian) {
    while ($rh = mysqli_fetch_assoc($q_harian)) {
        $h = (int)$rh['jam_trx'];
        // Kelompokkan ke slot 2-jaman terdekat (08, 10, 12, 14, 16, 18, 20)
        $slot = str_pad((string)(floor($h / 2) * 2), 2, '0', STR_PAD_LEFT);
        if (isset($harian_traffic[$slot])) {
            $harian_traffic[$slot] += (int)$rh['jml_pengunjung'];
            $harian_pendapatan[$slot] += (float)$rh['omzet'];
        }
    }
}
$harian_traffic = array_values($harian_traffic);
$harian_pendapatan = array_values($harian_pendapatan);

// 7. Data Grafik Bulanan (6 Bulan Terakhir: Jan, Feb, Mar, dst.)
$nama_bulan_id = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulanan_labels = [];
$bulanan_traffic = [];
$bulanan_pendapatan = [];

for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $ym = date('Y-m', $ts);
    $m_num = (int)date('n', $ts);
    $bulanan_labels[] = $nama_bulan_id[$m_num];

    $q_bln = mysqli_query($conn, "SELECT 
        COUNT(t.id_transaksi) AS jml,
        COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS omzet
        FROM transaksi t
        INNER JOIN area_lokasi a ON t.id_area = a.id_area
        WHERE a.kode_mitra = '$id_mitra_login' AND DATE_FORMAT(t.waktu_transaksi, '%Y-%m') = '$ym'");
    $rb = ($q_bln) ? mysqli_fetch_assoc($q_bln) : [];
    $bulanan_traffic[] = (int)($rb['jml'] ?? 0);
    $bulanan_pendapatan[] = (float)($rb['omzet'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content {
            max-width: 100%;
            width: 100%;
        }
        /* Paksa 3 kolom filter sejajar horizontal */
        .filter-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            width: 100%;
        }
        .filter-footer-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }
        /* Header Card Grafik Sesuai Figma */
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 1px solid #eef2f6;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toggle-buttons {
            display: inline-flex;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 3px;
            background-color: #ffffff;
            gap: 2px;
        }
        .toggle-buttons button {
            border: none;
            background-color: transparent;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #333333;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .toggle-buttons button:hover:not(.active) {
            background-color: #f4f6f9;
        }
        .toggle-buttons button.active {
            background-color: #004687;
            color: #ffffff;
        }
        .chart-sub-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 11px;
            color: #94a3b8;
        }
        .chart-legend-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }
        .chart-legend-dot span {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            background-color: #F2C94C;
            display: inline-block;
        }
        @media (max-width: 992px) {
            .filter-grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- Sidebar Khusus Mitra -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fa-solid fa-border-all icon"></i>
                Dashboard
            </a>

            <!-- Menu Dropdown: Laporan -->
            <div class="menu-group">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-regular fa-file-lines icon"></i> Laporan
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="detailTransaksi.php">Analisis Transaksi</a>
                    <a href="daftarTransaksi.php">Daftar Transaksi</a>
                    <a href="rekapTransaksi.php">Rekap Transaksi</a>
                </div>
            </div>

            <!-- Menu Dropdown: Monitoring -->
            <div class="menu-group">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-solid fa-desktop icon"></i> Monitoring
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="cctv.php">CCTV</a>
                </div>
            </div>

            <a href="../logout.php" class="menu-item logout">
                <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
            </a>
        </nav>
    </aside>

    <!-- Konten Utama -->
    <main class="main-content">
        <header class="top-header">
            <p class="welcome-text">Selamat Datang, <?= htmlspecialchars($nama_mitra_login); ?>!</p>
            <h1 class="page-header-title">Dashboard</h1>
        </header>

        <!-- Filter Card 3 Kolom Sejajar -->
        <section class="filter-card">
            <form method="GET" action="dashboard.php">
                <div class="filter-grid-3">
                    <div class="filter-group">
                        <label>Area</label>
                        <select name="area">
                            <option value="">Pilih Area</option>
                            <?php if ($opt_area): while ($a = mysqli_fetch_assoc($opt_area)): ?>
                                <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= ($f_area === $a['nama_area']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($a['nama_area']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Lokasi</label>
                        <select name="lokasi">
                            <option value="">Pilih lokasi</option>
                            <?php if ($opt_lokasi): while ($l = mysqli_fetch_assoc($opt_lokasi)): ?>
                                <option value="<?= htmlspecialchars($l['nama_lokasi']); ?>" <?= ($f_lokasi === $l['nama_lokasi']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($l['nama_lokasi']); ?>
                                </option>
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

        <!-- 5 Kartu KPI -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-ticket"></i></div>
                <div class="card-info">
                    <span class="card-label">Total Transaksi</span>
                    <h2 class="card-value"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> Data real-time</span>
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
                <div class="card-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div class="card-info">
                    <span class="card-label">Area</span>
                    <h2 class="card-value"><?= $jml_area; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-video"></i></div>
                <div class="card-info">
                    <span class="card-label">Perangkat Aktif</span>
                    <div class="device-badges">
                        <span class="badge"><i class="fa-solid fa-door-open"></i> <?= $turnstile_aktif; ?> Turnstile</span>
                        <span class="badge"><i class="fa-solid fa-camera"></i> <?= $cctv_aktif; ?> CCTV</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <div class="card-info">
                    <span class="card-label">Pendapatan</span>
                    <h2 class="card-value">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> Data real-time</span>
                </div>
            </div>
        </section>

        <!-- Grafik Traffic & Pendapatan Sesuai Figma -->
        <section class="charts-grid">
            <!-- Grafik Kiri: Traffic (Bar Chart Kuning) -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-right-left"></i></span>
                        <h3 class="chart-title">Traffic</h3>
                    </div>
                    <div class="toggle-buttons">
                        <button type="button" id="btnTrafficHarian" class="active" onclick="switchTraffic('harian')">Harian</button>
                        <button type="button" id="btnTrafficBulanan" onclick="switchTraffic('bulanan')">Bulanan</button>
                    </div>
                </div>
                <div class="chart-sub-meta">
                    <span>Jumlah Pengunjung</span>
                    <div class="chart-legend-dot"><span></span> Pengunjung</div>
                </div>
                <div class="chart-canvas-wrapper" style="height: 250px;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>

            <!-- Grafik Kanan: Pendapatan (Line Chart Kuning Emas) -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-chart-line"></i></span>
                        <h3 class="chart-title">Pendapatan</h3>
                    </div>
                    <div class="toggle-buttons">
                        <button type="button" id="btnOmzetHarian" class="active" onclick="switchOmzet('harian')">Harian</button>
                        <button type="button" id="btnOmzetBulanan" onclick="switchOmzet('bulanan')">Bulanan</button>
                    </div>
                </div>
                <div class="chart-sub-meta">
                    <span>Pendapatan (Rp)</span>
                    <div class="chart-legend-dot"><span></span> Pendapatan</div>
                </div>
                <div class="chart-canvas-wrapper" style="height: 250px;">
                    <canvas id="pendapatanChart"></canvas>
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

    const harianLabels = <?= json_encode($harian_labels); ?>;
    const harianTraffic = <?= json_encode($harian_traffic); ?>;
    const harianOmzet = <?= json_encode($harian_pendapatan); ?>;

    const bulananLabels = <?= json_encode($bulanan_labels); ?>;
    const bulananTraffic = <?= json_encode($bulanan_traffic); ?>;
    const bulananOmzet = <?= json_encode($bulanan_pendapatan); ?>;

    // 1. Bar Chart Traffic (Kuning Sesuai Figma)
    const trafficChart = new Chart(document.getElementById('trafficChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: harianLabels,
            datasets: [{
                label: 'Pengunjung',
                data: harianTraffic,
                backgroundColor: '#FDE047',
                hoverBackgroundColor: '#EAB308',
                borderRadius: 4,
                barThickness: 22
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
                            return ` Pengunjung: ${ctx.parsed.y.toLocaleString('id-ID')} tiket`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 }, color: '#64748b' },
                    title: {
                        display: true,
                        text: 'Tanggal & Jam',
                        font: { size: 11, weight: '600' },
                        color: '#334155'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { precision: 0, font: { size: 10 }, color: '#64748b' }
                }
            }
        }
    });

    // 2. Line Chart Pendapatan (Garis Kuning Emas Sesuai Figma)
    const pendapatanChart = new Chart(document.getElementById('pendapatanChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: harianLabels,
            datasets: [{
                label: 'Pendapatan',
                data: harianOmzet,
                borderColor: '#FACC15',
                backgroundColor: '#FACC15',
                pointBackgroundColor: '#EAB308',
                pointBorderColor: '#EAB308',
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 2.5,
                fill: false,
                tension: 0
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
                            return ` Pendapatan: Rp ${ctx.parsed.y.toLocaleString('id-ID')}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#64748b' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 10 },
                        color: '#64748b',
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

    function switchTraffic(mode) {
        document.getElementById('btnTrafficHarian').classList.toggle('active', mode === 'harian');
        document.getElementById('btnTrafficBulanan').classList.toggle('active', mode === 'bulanan');
        trafficChart.data.labels = (mode === 'harian') ? harianLabels : bulananLabels;
        trafficChart.data.datasets[0].data = (mode === 'harian') ? harianTraffic : bulananTraffic;
        trafficChart.options.scales.x.title.text = (mode === 'harian') ? 'Tanggal & Jam' : 'Bulan';
        trafficChart.update();
    }

    function switchOmzet(mode) {
        document.getElementById('btnOmzetHarian').classList.toggle('active', mode === 'harian');
        document.getElementById('btnOmzetBulanan').classList.toggle('active', mode === 'bulanan');
        pendapatanChart.data.labels = (mode === 'harian') ? harianLabels : bulananLabels;
        pendapatanChart.data.datasets[0].data = (mode === 'harian') ? harianOmzet : bulananOmzet;
        pendapatanChart.update();
    }
</script>
</body>
</html>