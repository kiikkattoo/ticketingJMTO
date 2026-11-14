<?php
session_start();
require_once '../koneksi.php';

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
        /* ===== Header chart & tombol toggle (Harian/Bulanan, Pagi/Siang/Malam) ===== */
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .chart-controls-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Wadah: kapsul putih dengan border tipis, tombol aktif "melayang" di dalamnya */
        .chart-card .toggle-buttons,
        .chart-card .shift-toggle-buttons {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        /* Tombol default */
        .chart-card .toggle-buttons button,
        .chart-card .shift-toggle-buttons button {
            border: none;
            background: transparent;
            padding: 7px 16px;
            border-radius: 7px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        /* Hover (tombol yang belum aktif) */
        .chart-card .toggle-buttons button:hover:not(.active),
        .chart-card .shift-toggle-buttons button:hover:not(.active) {
            background: #eef3f9;
            color: #004b87;
        }

        /* Tombol aktif: biru navy solid, sudut membulat, ada bayangan halus */
        .chart-card .toggle-buttons button.active,
        .chart-card .shift-toggle-buttons button.active {
            background: #004b87;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0, 75, 135, 0.28);
        }

        /* Aksesibilitas keyboard */
        .chart-card .toggle-buttons button:focus-visible,
        .chart-card .shift-toggle-buttons button:focus-visible {
            outline: 2px solid #8ab4dc;
            outline-offset: 1px;
        }

        /* ===== Kartu statistik (KPI) - tata letak 3 kolom, gaya sesuai desain ===== */
        .stats-grid {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 22px !important;
            margin-bottom: 24px;
        }
        .stats-grid .stat-card {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 14px !important;
            min-height: 142px !important;
            height: auto !important;
            padding: 22px 26px !important;
            background: #004080 !important;
            border-radius: 16px !important;
            color: #ffffff !important;
            text-decoration: none !important;
            box-shadow: 0 6px 14px rgba(0, 64, 128, 0.22) !important;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stats-grid a.stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(0, 64, 128, 0.32) !important;
        }

        /* Ikon: putih solid, langsung di kartu (tanpa kotak), di kolom kiri */
        .stats-grid .stat-card .card-icon {
            flex: 0 0 36% !important;
            max-width: 36%;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            border-radius: 0 !important;
            color: #ffffff !important;
            padding: 0;
            margin: 0;
        }
        .stats-grid .stat-card .card-icon svg {
            width: 58px;
            height: 58px;
            display: block;
        }

        /* Teks di kolom kanan */
        .stats-grid .stat-card .card-info {
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            flex: 1 1 auto;
            min-width: 0;
            padding: 0;
            margin: 0;
        }
        .stats-grid .stat-card .card-label {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            line-height: 1.3;
            margin: 0 !important;
        }
        .stats-grid .stat-card .card-value {
            font-size: clamp(22px, 1.7vw, 32px) !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            line-height: 1.15;
            margin: 0 !important;
            white-space: nowrap;
        }
        .stats-grid .stat-card .card-trend {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px !important;
            font-weight: 400;
            color: #d3deec !important;
            margin-top: 4px;
        }

        /* Perangkat aktif: ikon bulat putih + teks kecil, seperti desain */
        .stats-grid .stat-card .device-sub-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 14px;
            margin-top: 6px;
        }
        .stats-grid .stat-card .device-sub-badges span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 500;
            color: #e3ebf5;
            background: transparent;
            padding: 0;
            white-space: nowrap;
        }
        .stats-grid .stat-card .device-sub-badges .dev-ic {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #ffffff;
            color: #004080;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stats-grid .stat-card .device-sub-badges .dev-ic svg {
            width: 16px;
            height: 16px;
            display: block;
        }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr !important; }
            .stats-grid .stat-card .card-icon { flex: 0 0 90px !important; max-width: 90px; }
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
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-20 24 24)"><mask id="mTicket" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="3" cy="24" r="3.8" fill="#000"/><circle cx="45" cy="24" r="3.8" fill="#000"/><rect x="15" y="17.2" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="22.5" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="27.8" width="18" height="3" rx="1.2" fill="#000"/></mask><rect x="3" y="12" width="42" height="24" rx="5.5" mask="url(#mTicket)"/></g></svg></div>
                    <div class="card-info">
                        <span class="card-label">Total Transaksi</span>
                        <h2 class="card-value" id="statTransaksi"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
                        <span class="card-trend"><i class="fa-solid fa-database"></i> Data real-time</span>
                    </div>
                </a>

                <!-- Card 2: Gate Aktif -->
                <div class="stat-card">
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-8 24 18)"><mask id="mGate" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><rect x="9" y="9.5" width="26" height="9" rx="1.6" fill="#000"/></mask><rect x="4" y="5" width="36" height="22" rx="3.6" mask="url(#mGate)"/><rect x="15" y="12" width="14" height="13" rx="1.3"/></g><path d="M24 45V33.5M19 38.5l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <div class="card-info">
                        <span class="card-label">Gate Aktif</span>
                        <h2 class="card-value" id="statGate"><?= $total_gate; ?></h2>
                    </div>
                </div>

                <!-- Card 3: Area -->
                <div class="stat-card">
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><mask id="mPin" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="24" cy="16.5" r="5.4" fill="#000"/></mask><path d="M24 3C16.3 3 10 9.2 10 16.8 10 26.8 24 38 24 38S38 26.8 38 16.8C38 9.2 31.7 3 24 3Z" mask="url(#mPin)"/><ellipse cx="24" cy="38" rx="18.5" ry="5.4" fill="none" stroke="currentColor" stroke-width="2"/></svg></div>
                    <div class="card-info">
                        <span class="card-label">Area</span>
                        <h2 class="card-value" id="statArea"><?= $total_area; ?></h2>
                    </div>
                </div>

                <!-- Card 4: Mitra Aktif -> daftarMitra.php -->
                <a href="daftarMitra.php" class="stat-card clickable-card">
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><circle cx="12" cy="11" r="4.8"/><path d="M3.5 26c0-5 3.8-8 8.5-8s8.5 3 8.5 8z"/><circle cx="36" cy="31" r="4.8"/><path d="M27.5 46c0-5 3.8-8 8.5-8s8.5 3 8.5 8z"/><path d="M25 31A11 11 0 0 1 36 20M18 31A18 18 0 0 1 36 13" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/></svg></div>
                    <div class="card-info">
                        <span class="card-label">Mitra Aktif</span>
                        <h2 class="card-value" id="statMitra"><?= $total_mitra; ?></h2>
                    </div>
                </a>

                <!-- Card 5: Perangkat Aktif -> monitoringArea.php -->
                <a href="monitoringArea.php" class="stat-card clickable-card">
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><mask id="mProj" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="28" cy="22.5" r="7.2" fill="#000"/><circle cx="11" cy="22.5" r="1.9" fill="#000"/></mask><rect x="3" y="11" width="42" height="23" rx="6.5" mask="url(#mProj)"/><circle cx="28" cy="22.5" r="3.5"/><rect x="22" y="36" width="12" height="3.2" rx="1.6"/></svg></div>
                    <div class="card-info">
                        <span class="card-label">Perangkat Aktif</span>
                        <div class="device-sub-badges">
                            <span><i class="dev-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="6" height="14" rx="1.5"/><circle cx="9" cy="10.5" r="1"/><line x1="10" y1="10.5" x2="21" y2="10.5"/><line x1="9.8" y1="11.3" x2="17" y2="18"/><line x1="8.6" y1="11.6" x2="12" y2="20"/></svg></i> <?= $total_turnstile; ?> Turnstile</span>
                            <span><i class="dev-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 8.5l12-3.5 2.2 6.3-12 3.5z"/><path d="M16 8l4.5-1.2v4.6L17.6 12"/><path d="M7.5 15.3V20H13"/></svg></i> <?= $total_cctv; ?> CCTV</span>
                        </div>
                    </div>
                </a>

                <!-- Card 6: Pendapatan -> rekapTransaksi.php -->
                <a href="rekapTransaksi.php" class="stat-card clickable-card">
                    <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><path d="M5 20L15 10l7 6L34 5" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M26 4.6h8.4V13" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><g transform="rotate(-8 24 35)"><mask id="mMoney" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="24" cy="35" r="6.4" fill="#000"/><circle cx="10.5" cy="35" r="1.9" fill="#000"/><circle cx="37.5" cy="35" r="1.9" fill="#000"/></mask><rect x="4" y="26" width="40" height="18" rx="3.2" mask="url(#mMoney)"/><circle cx="24" cy="35" r="3.1"/></g></svg></div>
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

        // INISIALISASI CHART (gaya interaktif sesuai desain)
        const COLOR_BAR        = '#FFE580';   // kuning muda (normal)
        const COLOR_BAR_HOVER  = '#F2C400';   // kuning solid (saat di-hover)
        const COLOR_LINE       = '#F7D95A';
        const COLOR_LINE_DARK  = '#C99A00';

        Chart.defaults.font.family = "'Poppins', sans-serif";
        Chart.defaults.color = '#6b7280';

        // Latar abu-abu muda di area plot, seperti di desain
        const plotAreaBg = {
            id: 'plotAreaBg',
            beforeDraw(chart) {
                const { ctx, chartArea } = chart;
                if (!chartArea) return;
                ctx.save();
                ctx.fillStyle = '#f8fafc';
                ctx.fillRect(chartArea.left, chartArea.top,
                             chartArea.right - chartArea.left,
                             chartArea.bottom - chartArea.top);
                ctx.restore();
            }
        };

        // Judul sumbu X otomatis: "Area" / "Tanggal & Jam" / "Bulan"
        function xAxisTitlePlugin(getPeriod) {
            return {
                id: 'xAxisTitleAuto',
                beforeUpdate(chart) {
                    let text = 'Area';
                    if (isFiltered) text = (getPeriod() === 'harian') ? 'Tanggal & Jam' : 'Bulan';
                    chart.options.scales.x.title.text = text;
                }
            };
        }

        function makeXScale() {
            return {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 11 } },
                title: { display: true, text: 'Area', color: '#111827',
                         font: { size: 11, weight: '600' }, padding: { top: 10 } }
            };
        }

        const tooltipStyle = {
            backgroundColor: '#004b87',
            padding: 12,
            cornerRadius: 10,
            displayColors: false,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 12 }
        };

        const legendStyle = {
            position: 'top',
            align: 'end',
            labels: { usePointStyle: true, pointStyle: 'rect', boxWidth: 10, boxHeight: 10 }
        };

        const hoverCursor = (evt, els) => {
            evt.native.target.style.cursor = els.length ? 'pointer' : 'default';
        };

        // ---------- TRAFFIC (BAR) ----------
        const ctxTraffic = document.getElementById('trafficChart').getContext('2d');
        const trafficChart = new Chart(ctxTraffic, {
            type: 'bar',
            data: {
                labels: areaDataset.labels,
                datasets: [{
                    label: 'Pengunjung',
                    data: areaDataset.trafficBulanan,
                    backgroundColor: COLOR_BAR,
                    hoverBackgroundColor: COLOR_BAR_HOVER,
                    borderRadius: { topLeft: 6, topRight: 6 },
                    borderSkipped: false,
                    maxBarThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                onHover: hoverCursor,
                plugins: {
                    legend: legendStyle,
                    tooltip: {
                        ...tooltipStyle,
                        callbacks: {
                            title: formatHourlyTooltipTitle,
                            label: ctx => `Total Pengunjung: ${ctx.parsed.y.toLocaleString('id-ID')} orang`
                        }
                    }
                },
                scales: {
                    x: makeXScale(),
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#e5e7eb' },
                        ticks: { precision: 0 }
                    }
                }
            },
            plugins: [plotAreaBg, xAxisTitlePlugin(() => trafficPeriod)]
        });

        // ---------- PENDAPATAN (LINE) ----------
        const ctxPendapatan = document.getElementById('pendapatanChart').getContext('2d');
        const pendapatanChart = new Chart(ctxPendapatan, {
            type: 'line',
            data: {
                labels: areaDataset.labels,
                datasets: [{
                    label: 'Pendapatan',
                    data: areaDataset.pendapatanBulanan,
                    borderColor: COLOR_LINE,
                    borderWidth: 3,
                    tension: 0.35,
                    fill: true,
                    backgroundColor: function (context) {
                        const { ctx, chartArea } = context.chart;
                        if (!chartArea) return 'rgba(247,217,90,0.2)';
                        const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, 'rgba(247,217,90,0.45)');
                        g.addColorStop(1, 'rgba(247,217,90,0.02)');
                        return g;
                    },
                    pointBackgroundColor: COLOR_LINE,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: COLOR_LINE_DARK,
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 800, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                onHover: hoverCursor,
                plugins: {
                    legend: legendStyle,
                    tooltip: {
                        ...tooltipStyle,
                        callbacks: {
                            title: formatHourlyTooltipTitle,
                            label: ctx => `Total Pendapatan: Rp ${ctx.parsed.y.toLocaleString('id-ID')}`
                        }
                    }
                },
                scales: {
                    x: makeXScale(),
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#e5e7eb' },
                        ticks: {
                            callback: v => v >= 1000000 ? (v / 1000000).toLocaleString('id-ID') + ' jt'
                                         : v >= 1000    ? (v / 1000).toLocaleString('id-ID') + ' rb'
                                         : v
                        }
                    }
                }
            },
            plugins: [plotAreaBg, xAxisTitlePlugin(() => pendapatanPeriod)]
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