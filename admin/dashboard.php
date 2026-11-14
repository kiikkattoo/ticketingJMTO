<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// ==========================================================
// 1. QUERY 6 KARTU KPI REAL-TIME DARI DATABASE
// ==========================================================

// Card 1 & 6: Total Transaksi & Total Pendapatan
$res_trx = mysqli_query($conn, "SELECT COUNT(*) AS total_trx, COALESCE(SUM(nominal), 0) AS total_pendapatan FROM transaksi WHERE status_transaksi = 'Berhasil'");
$row_trx = mysqli_fetch_assoc($res_trx);
$total_transaksi  = (int)$row_trx['total_trx'];
$total_pendapatan = (float)$row_trx['total_pendapatan'];

// Card 2 & 3: Gate Aktif & Jumlah Area
$res_area_cnt = mysqli_query($conn, "SELECT COUNT(DISTINCT nama_gate) AS total_gate, COUNT(DISTINCT nama_area) AS total_area FROM area_lokasi");
$row_area_cnt = mysqli_fetch_assoc($res_area_cnt);
$total_gate = (int)$row_area_cnt['total_gate'];
$total_area = (int)$row_area_cnt['total_area'];

// Card 4: Mitra Aktif
$res_mitra_cnt = mysqli_query($conn, "SELECT COUNT(*) AS total_mitra FROM mitra WHERE status_mitra = 'Aktif'");
$total_mitra = (int)mysqli_fetch_assoc($res_mitra_cnt)['total_mitra'];

// Card 5: Perangkat Aktif (Turnstile & CCTV)
$res_turnstile = mysqli_query($conn, "SELECT COUNT(*) AS jml FROM perangkat WHERE jenis_perangkat = 'Turnstile' AND status_perangkat = 'Aktif'");
$total_turnstile = (int)mysqli_fetch_assoc($res_turnstile)['jml'];

$res_cctv = mysqli_query($conn, "SELECT COUNT(*) AS jml FROM perangkat WHERE jenis_perangkat = 'CCTV' AND status_perangkat = 'Aktif'");
$total_cctv = (int)mysqli_fetch_assoc($res_cctv)['jml'];

// ==========================================================
// 2. QUERY OPSI DROPDOWN FILTER DARI DATABASE
// ==========================================================
$opt_mitra  = mysqli_query($conn, "SELECT DISTINCT nama_mitra FROM mitra ORDER BY nama_mitra ASC");
$opt_area   = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi ORDER BY nama_area ASC");
$opt_lokasi = mysqli_query($conn, "SELECT DISTINCT nama_lokasi FROM area_lokasi ORDER BY nama_lokasi ASC");

// ==========================================================
// 3. QUERY DATASET GRAFIK PER AREA (SUMBU X = NAMA AREA)
// ==========================================================
$query_chart_area = "SELECT 
                        a.nama_area,
                        COUNT(CASE WHEN DATE(t.waktu_transaksi) = CURDATE() AND t.status_transaksi = 'Berhasil' THEN 1 END) AS traffic_harian,
                        COUNT(CASE WHEN t.status_transaksi = 'Berhasil' THEN 1 END) AS traffic_bulanan,
                        COALESCE(SUM(CASE WHEN DATE(t.waktu_transaksi) = CURDATE() AND t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS pend_harian,
                        COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS pend_bulanan
                     FROM area_lokasi a
                     LEFT JOIN transaksi t ON a.id_area = t.id_area
                     GROUP BY a.nama_area
                     ORDER BY a.nama_area ASC";
$res_chart_area = mysqli_query($conn, $query_chart_area);

$area_labels = [];
$traffic_harian = [];
$traffic_bulanan = [];
$pendapatan_harian = [];
$pendapatan_bulanan = [];

while ($ca = mysqli_fetch_assoc($res_chart_area)) {
    $area_labels[]        = $ca['nama_area'];
    $traffic_harian[]     = (int)$ca['traffic_harian'];
    $traffic_bulanan[]    = (int)$ca['traffic_bulanan'];
    $pendapatan_harian[]  = (float)$ca['pend_harian'];
    $pendapatan_bulanan[] = (float)$ca['pend_bulanan'];
}

// ==========================================================
// 4. QUERY DATASET SHIFT (PAGI 00-12, SIANG 13-18, MALAM 19-23)
// ==========================================================
$hourly_traffic = array_fill(0, 24, 0);
$hourly_pendapatan = array_fill(0, 24, 0);

$res_hourly = mysqli_query($conn, "SELECT HOUR(waktu_transaksi) AS jam, COUNT(*) AS jml, COALESCE(SUM(nominal), 0) AS nom FROM transaksi WHERE status_transaksi = 'Berhasil' GROUP BY HOUR(waktu_transaksi)");
while ($h = mysqli_fetch_assoc($res_hourly)) {
    $jam = (int)$h['jam'];
    $hourly_traffic[$jam]    = (int)$h['jml'];
    $hourly_pendapatan[$jam] = (float)$h['nom'];
}

$tgl_short = date('d/m');
$tgl_full  = date('d/m/Y');

// Pagi (00:00 - 12:00)
$pagi_labels = []; $pagi_traffic = []; $pagi_pendapatan = [];
for ($i = 0; $i <= 12; $i++) {
    $jam_str = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
    $pagi_labels[]     = [$tgl_short, $jam_str];
    $pagi_traffic[]    = $hourly_traffic[$i];
    $pagi_pendapatan[] = $hourly_pendapatan[$i];
}

// Siang (13:00 - 18:00)
$siang_labels = []; $siang_traffic = []; $siang_pendapatan = [];
for ($i = 13; $i <= 18; $i++) {
    $jam_str = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
    $siang_labels[]     = [$tgl_full, $jam_str];
    $siang_traffic[]    = $hourly_traffic[$i];
    $siang_pendapatan[] = $hourly_pendapatan[$i];
}

// Malam (19:00 - 23:00)
$malam_labels = []; $malam_traffic = []; $malam_pendapatan = [];
for ($i = 19; $i <= 23; $i++) {
    $jam_str = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
    $malam_labels[]     = [$tgl_full, $jam_str];
    $malam_traffic[]    = $hourly_traffic[$i];
    $malam_pendapatan[] = $hourly_pendapatan[$i];
}

// ==========================================================
// 5. QUERY DATASET BULANAN (JAN - DES)
// ==========================================================
$bulan_nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulanan_traffic = array_fill(0, 12, 0);
$bulanan_pendapatan = array_fill(0, 12, 0);

$res_monthly = mysqli_query($conn, "SELECT MONTH(waktu_transaksi) AS bln, COUNT(*) AS jml, COALESCE(SUM(nominal), 0) AS nom FROM transaksi WHERE status_transaksi = 'Berhasil' GROUP BY MONTH(waktu_transaksi)");
while ($m = mysqli_fetch_assoc($res_monthly)) {
    $idx = (int)$m['bln'] - 1;
    $bulanan_traffic[$idx]    = (int)$m['jml'];
    $bulanan_pendapatan[$idx] = (float)$m['nom'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icon Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Styling Tombol Harian / Bulanan agar rapi & biru solid saat aktif */
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toggle-buttons {
            display: inline-flex;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            overflow: hidden;
            background-color: #ffffff;
        }
        .toggle-buttons button {
            border: none;
            background-color: transparent;
            padding: 6px 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #333333;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .toggle-buttons button:not(:last-child) {
            border-right: 1px solid #d0d7de;
        }
        .toggle-buttons button:hover:not(.active) {
            background-color: #f4f6f9;
        }
        .toggle-buttons button.active {
            background-color: #004b87;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <!-- Sidebar Fixed -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
            </div>

            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fa-solid fa-border-all icon"></i> Dashboard
                </a>

                <!-- Menu Dropdown: Laporan -->
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-regular fa-file-lines icon"></i> Laporan
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="detailTransaksi.php">Detail Transaksi</a>
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
                        <a href="monitoringArea.php">Perangkat Area</a>
                    </div>
                </div>

                <!-- Menu Dropdown: Manajemen -->
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
                    </div>
                </div>

                <a href="../logout.php" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
                </a>
            </nav>
        </aside>

        <!-- Area Konten Utama -->
        <main class="main-content">
            <header class="top-header">
                <p class="welcome-text">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_user'] ?? 'Admin'); ?>!</p>
                <h1 class="page-header-title">Dashboard</h1>
            </header>

            <!-- Filter Card 5 Input Sesuai Desain -->
            <section class="filter-card">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Kategori Mitra</label>
                        <select id="filterKategori">
                            <option value="">Pilih kategori mitra</option>
                            <option value="Transportasi">Transportasi</option>
                            <option value="Wisata & Rekreasi">Wisata & Rekreasi</option>
                            <option value="Retail & F&B">Retail & F&B</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Mitra</label>
                        <select id="filterMitra">
                            <option value="">Pilih mitra</option>
                            <?php while ($m = mysqli_fetch_assoc($opt_mitra)): ?>
                                <option value="<?= htmlspecialchars($m['nama_mitra']); ?>"><?= htmlspecialchars($m['nama_mitra']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Area</label>
                        <select id="filterArea">
                            <option value="">Pilih Area</option>
                            <?php while ($a = mysqli_fetch_assoc($opt_area)): ?>
                                <option value="<?= htmlspecialchars($a['nama_area']); ?>"><?= htmlspecialchars($a['nama_area']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-row align-center" style="margin-top: 15px;">
                    <div class="filter-group">
                        <label>Lokasi</label>
                        <select id="filterLokasi">
                            <option value="">Pilih lokasi</option>
                            <?php while ($l = mysqli_fetch_assoc($opt_lokasi)): ?>
                                <option value="<?= htmlspecialchars($l['nama_lokasi']); ?>"><?= htmlspecialchars($l['nama_lokasi']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Periode</label>
                        <select id="filterPeriode">
                            <option value="">Pilih periode</option>
                            <option value="Hari Ini">Hari Ini</option>
                            <option value="Minggu Ini">Minggu Ini</option>
                            <option value="Bulan Ini">Bulan Ini</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="btn-reset" onclick="resetDashboardFilter()">Reset</button>
                        <button type="button" class="btn-filter" onclick="applyDashboardFilter()">Filter</button>
                    </div>
                </div>
            </section>

            <!-- 6 Stat Cards Sesuai Desain Figma -->
            <section class="stats-grid">
                <!-- Card 1: Total Transaksi -> detailTransaksi.php -->
                <a href="detailTransaksi.php" class="stat-card clickable-card">
                    <div class="card-icon"><i class="fa-solid fa-ticket"></i></div>
                    <div class="card-info">
                        <span class="card-label">Total Transaksi</span>
                        <h2 class="card-value" id="statTransaksi"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
                        <span class="card-trend"><i class="fa-solid fa-database"></i> Data real-time</span>
                    </div>
                </a>

                <!-- Card 2: Gate Aktif -->
                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-torii-gate"></i></div>
                    <div class="card-info">
                        <span class="card-label">Gate Aktif</span>
                        <h2 class="card-value" id="statGate"><?= $total_gate; ?></h2>
                    </div>
                </div>

                <!-- Card 3: Area -->
                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div class="card-info">
                        <span class="card-label">Area</span>
                        <h2 class="card-value" id="statArea"><?= $total_area; ?></h2>
                    </div>
                </div>

                <!-- Card 4: Mitra Aktif -> daftarMitra.php -->
                <a href="daftarMitra.php" class="stat-card clickable-card">
                    <div class="card-icon"><i class="fa-solid fa-handshake"></i></div>
                    <div class="card-info">
                        <span class="card-label">Mitra Aktif</span>
                        <h2 class="card-value" id="statMitra"><?= $total_mitra; ?></h2>
                    </div>
                </a>

                <!-- Card 5: Perangkat Aktif -> monitoringArea.php -->
                <a href="monitoringArea.php" class="stat-card clickable-card">
                    <div class="card-icon"><i class="fa-solid fa-video"></i></div>
                    <div class="card-info">
                        <span class="card-label">Perangkat Aktif</span>
                        <div class="device-sub-badges">
                            <span><i class="fa-solid fa-person-booth"></i> <?= $total_turnstile; ?> Turnstile</span>
                            <span><i class="fa-solid fa-camera"></i> <?= $total_cctv; ?> CCTV</span>
                        </div>
                    </div>
                </a>

                <!-- Card 6: Pendapatan -> rekapTransaksi.php -->
                <a href="rekapTransaksi.php" class="stat-card clickable-card">
                    <div class="card-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                    <div class="card-info">
                        <span class="card-label">Pendapatan</span>
                        <h2 class="card-value" id="statPendapatan">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                        <span class="card-trend"><i class="fa-solid fa-database"></i> Data real-time</span>
                    </div>
                </a>
            </section>

            <!-- Charts Grid -->
            <section class="charts-grid">
                <!-- Chart 1: Traffic -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <span class="chart-title-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
                            <h3 class="chart-title">Traffic</h3>
                        </div>
                        <div class="chart-controls-right">
                            <!-- Sub-filter Pagi / Siang / Malam (Muncul saat Harian & Terfilter) -->
                            <div class="shift-toggle-buttons" id="trafficShiftGroup" style="display: none;">
                                <button type="button" onclick="switchTrafficShift('pagi', this)">Pagi</button>
                                <button type="button" class="active" onclick="switchTrafficShift('siang', this)">Siang</button>
                                <button type="button" onclick="switchTrafficShift('malam', this)">Malam</button>
                            </div>
                            <!-- Toggle Harian / Bulanan -->
                            <div class="toggle-buttons">
                                <button type="button" class="active" onclick="switchTrafficPeriod('harian', this)">Harian</button>
                                <button type="button" onclick="switchTrafficPeriod('bulanan', this)">Bulanan</button>
                            </div>
                        </div>
                    </div>
                    <div class="chart-canvas-wrapper" style="height: 290px;">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Pendapatan -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title-group">
                            <span class="chart-title-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <h3 class="chart-title">Pendapatan</h3>
                        </div>
                        <div class="chart-controls-right">
                            <!-- Sub-filter Pagi / Siang / Malam (Muncul saat Harian & Terfilter) -->
                            <div class="shift-toggle-buttons" id="pendapatanShiftGroup" style="display: none;">
                                <button type="button" onclick="switchPendapatanShift('pagi', this)">Pagi</button>
                                <button type="button" class="active" onclick="switchPendapatanShift('siang', this)">Siang</button>
                                <button type="button" onclick="switchPendapatanShift('malam', this)">Malam</button>
                            </div>
                            <!-- Toggle Harian / Bulanan -->
                            <div class="toggle-buttons">
                                <button type="button" class="active" onclick="switchPendapatanPeriod('harian', this)">Harian</button>
                                <button type="button" onclick="switchPendapatanPeriod('bulanan', this)">Bulanan</button>
                            </div>
                        </div>
                    </div>
                    <div class="chart-canvas-wrapper" style="height: 290px;">
                        <canvas id="pendapatanChart"></canvas>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('active');
        }

        // DATASET DINAMIS DARI DATABASE MYSQL
        const initialStats = {
            transaksi: "<?= number_format($total_transaksi, 0, ',', '.'); ?>",
            gate: "<?= $total_gate; ?>",
            area: "<?= $total_area; ?>",
            mitra: "<?= $total_mitra; ?>",
            pendapatan: "Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?>"
        };

        const areaDataset = {
            labels: <?= json_encode($area_labels); ?>,
            trafficHarian: <?= json_encode($traffic_harian); ?>,
            trafficBulanan: <?= json_encode($traffic_bulanan); ?>,
            pendapatanHarian: <?= json_encode($pendapatan_harian); ?>,
            pendapatanBulanan: <?= json_encode($pendapatan_bulanan); ?>
        };

        const shiftDatasets = {
            pagi: {
                labels: <?= json_encode($pagi_labels); ?>,
                traffic: <?= json_encode($pagi_traffic); ?>,
                pendapatan: <?= json_encode($pagi_pendapatan); ?>
            },
            siang: {
                labels: <?= json_encode($siang_labels); ?>,
                traffic: <?= json_encode($siang_traffic); ?>,
                pendapatan: <?= json_encode($siang_pendapatan); ?>
            },
            malam: {
                labels: <?= json_encode($malam_labels); ?>,
                traffic: <?= json_encode($malam_traffic); ?>,
                pendapatan: <?= json_encode($malam_pendapatan); ?>
            }
        };

        const bulananFilteredDataset = {
            labels: <?= json_encode($bulan_nama); ?>,
            traffic: <?= json_encode($bulanan_traffic); ?>,
            pendapatan: <?= json_encode($bulanan_pendapatan); ?>
        };

        let isFiltered = false;
        let trafficPeriod = 'harian';
        let trafficShift = 'siang';
        let pendapatanPeriod = 'harian';
        let pendapatanShift = 'siang';

        function formatHourlyTooltipTitle(context) {
            const rawLabel = context[0].chart.data.labels[context[0].dataIndex];
            if (Array.isArray(rawLabel) && rawLabel.length === 2) {
                const tanggal = rawLabel[0].length === 5 ? rawLabel[0] + '/<?= date("Y"); ?>' : rawLabel[0];
                const jamMulai = rawLabel[1];
                const jamAngka = jamMulai.split(':')[0];
                return `${tanggal} (${jamMulai} - ${jamAngka}:59)`;
            }
            return rawLabel;
        }

        const commonXScale = {
            grid: { display: false },
            ticks: {
                maxRotation: 0,
                minRotation: 0,
                autoSkip: false,
                font: { size: 10 }
            }
        };

        // INISIALISASI CHART
        const ctxTraffic = document.getElementById('trafficChart').getContext('2d');
        const trafficChart = new Chart(ctxTraffic, {
            type: 'bar',
            data: {
                labels: areaDataset.labels,
                datasets: [{
                    label: 'Pengunjung',
                    data: areaDataset.trafficBulanan,
                    backgroundColor: '#F2C94C',
                    hoverBackgroundColor: '#D4A017',
                    borderRadius: 5,
                    maxBarThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            title: formatHourlyTooltipTitle,
                            label: function(ctx) {
                                return `Total Pengunjung: ${ctx.parsed.y.toLocaleString('id-ID')} orang`;
                            }
                        }
                    }
                },
                scales: {
                    x: commonXScale,
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } }
                }
            }
        });

        const ctxPendapatan = document.getElementById('pendapatanChart').getContext('2d');
        const pendapatanChart = new Chart(ctxPendapatan, {
            type: 'line',
            data: {
                labels: areaDataset.labels,
                datasets: [{
                    label: 'Pendapatan',
                    data: areaDataset.pendapatanBulanan,
                    borderColor: '#F2C94C',
                    backgroundColor: '#F2C94C',
                    borderWidth: 3,
                    pointBackgroundColor: '#F2C94C',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            title: formatHourlyTooltipTitle,
                            label: function(ctx) {
                                return `Total Pendapatan: Rp ${ctx.parsed.y.toLocaleString('id-ID')}`;
                            }
                        }
                    }
                },
                scales: {
                    x: commonXScale,
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } }
                }
            }
        });

        // LOGIKA RENDER & TOGGLE CONTROLS
        function renderTrafficChart() {
            const shiftGroup = document.getElementById('trafficShiftGroup');
            if (!isFiltered) {
                shiftGroup.style.display = 'none';
                trafficChart.data.labels = areaDataset.labels;
                trafficChart.data.datasets[0].data = (trafficPeriod === 'harian')
                    ? areaDataset.trafficBulanan
                    : areaDataset.trafficBulanan;
                trafficChart.options.scales.x.ticks.font.size = 11;
            } else {
                if (trafficPeriod === 'harian') {
                    shiftGroup.style.display = 'inline-flex';
                    trafficChart.data.labels = shiftDatasets[trafficShift].labels;
                    trafficChart.data.datasets[0].data = shiftDatasets[trafficShift].traffic;
                    trafficChart.options.scales.x.ticks.font.size = (trafficShift === 'pagi') ? 9.5 : 11;
                } else {
                    shiftGroup.style.display = 'none';
                    trafficChart.data.labels = bulananFilteredDataset.labels;
                    trafficChart.data.datasets[0].data = bulananFilteredDataset.traffic;
                    trafficChart.options.scales.x.ticks.font.size = 11;
                }
            }
            trafficChart.update();
        }

        function renderPendapatanChart() {
            const shiftGroup = document.getElementById('pendapatanShiftGroup');
            if (!isFiltered) {
                shiftGroup.style.display = 'none';
                pendapatanChart.data.labels = areaDataset.labels;
                pendapatanChart.data.datasets[0].data = (pendapatanPeriod === 'harian')
                    ? areaDataset.pendapatanBulanan
                    : areaDataset.pendapatanBulanan;
                pendapatanChart.options.scales.x.ticks.font.size = 11;
            } else {
                if (pendapatanPeriod === 'harian') {
                    shiftGroup.style.display = 'inline-flex';
                    pendapatanChart.data.labels = shiftDatasets[pendapatanShift].labels;
                    pendapatanChart.data.datasets[0].data = shiftDatasets[pendapatanShift].pendapatan;
                    pendapatanChart.options.scales.x.ticks.font.size = (pendapatanShift === 'pagi') ? 9.5 : 11;
                } else {
                    shiftGroup.style.display = 'none';
                    pendapatanChart.data.labels = bulananFilteredDataset.labels;
                    pendapatanChart.data.datasets[0].data = bulananFilteredDataset.pendapatan;
                    pendapatanChart.options.scales.x.ticks.font.size = 11;
                }
            }
            pendapatanChart.update();
        }

        function switchTrafficPeriod(period, btn) {
            trafficPeriod = period;
            btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderTrafficChart();
        }

        function switchPendapatanPeriod(period, btn) {
            pendapatanPeriod = period;
            btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderPendapatanChart();
        }

        function switchTrafficShift(shift, btn) {
            trafficShift = shift;
            btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderTrafficChart();
        }

        function switchPendapatanShift(shift, btn) {
            pendapatanShift = shift;
            btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderPendapatanChart();
        }

        function applyDashboardFilter() {
            isFiltered = true;
            renderTrafficChart();
            renderPendapatanChart();
        }

        function resetDashboardFilter() {
            document.getElementById('filterKategori').value = "";
            document.getElementById('filterMitra').value = "";
            document.getElementById('filterArea').value = "";
            document.getElementById('filterLokasi').value = "";
            document.getElementById('filterPeriode').value = "";
            isFiltered = false;
            document.getElementById('statTransaksi').innerText = initialStats.transaksi;
            document.getElementById('statGate').innerText = initialStats.gate;
            document.getElementById('statArea').innerText = initialStats.area;
            document.getElementById('statMitra').innerText = initialStats.mitra;
            document.getElementById('statPendapatan').innerText = initialStats.pendapatan;
            renderTrafficChart();
            renderPendapatanChart();
        }
    </script>
</body>
</html>