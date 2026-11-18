<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); // samakan 'hari ini' dengan waktu transaksi (WIB)
require_once '../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Sesi login berakhir, silakan muat ulang halaman.']);
        exit();
    }
    header("Location: ../login.php");
    exit();
}

// =========================================================================
// HANDLER AJAX UNTUK FILTER GRAFIK & KPI (TANPA FILE TERPISAH)
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $dari     = trim($_GET['dari'] ?? '');
    $sampai   = trim($_GET['sampai'] ?? '');
    $kategori = trim($_GET['kategori'] ?? '');
    $mitra    = trim($_GET['mitra'] ?? '');
    $area     = trim($_GET['area'] ?? '');
    $lokasi   = trim($_GET['lokasi'] ?? '');

    $where = ["t.status_transaksi = 'Berhasil'"];

    if ($dari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
        $dari_esc = mysqli_real_escape_string($conn, $dari);
        $where[] = "DATE(t.waktu_transaksi) >= '$dari_esc'";
    }
    if ($sampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
        $sampai_esc = mysqli_real_escape_string($conn, $sampai);
        $where[] = "DATE(t.waktu_transaksi) <= '$sampai_esc'";
    }
    if ($kategori !== '') {
        $kat_esc = mysqli_real_escape_string($conn, $kategori);
        $where[] = "m.kategori_mitra = '$kat_esc'";
    }
    if ($mitra !== '') {
        $mitra_esc = mysqli_real_escape_string($conn, $mitra);
        $where[] = "m.nama_mitra = '$mitra_esc'";
    }
    if ($area !== '') {
        $area_esc = mysqli_real_escape_string($conn, $area);
        $where[] = "a.nama_area = '$area_esc'";
    }
    if ($lokasi !== '') {
        $lok_esc = mysqli_real_escape_string($conn, $lokasi);
        $where[] = "a.nama_lokasi = '$lok_esc'";
    }

    $where_sql = "WHERE " . implode(" AND ", $where);

    // JOIN fleksibel (mendukung kode_mitra maupun id_mitra di tabel area_lokasi)
    $join_sql = "FROM transaksi t
                 LEFT JOIN area_lokasi a ON t.id_area = a.id_area
                 LEFT JOIN mitra m ON COALESCE(NULLIF(a.kode_mitra, ''), a.id_mitra) = m.kode_mitra";

    // 1. Hitung ulang KPI sesuai filter
    $sql_kpi = "SELECT 
                    COUNT(t.id_transaksi) AS total_trx,
                    COALESCE(SUM(t.nominal), 0) AS total_pendapatan,
                    COUNT(DISTINCT a.nama_gate) AS total_gate,
                    COUNT(DISTINCT a.nama_area) AS total_area,
                    COUNT(DISTINCT m.kode_mitra) AS total_mitra
                $join_sql
                $where_sql";
    $res_kpi = mysqli_query($conn, $sql_kpi);
    $kpi_row = $res_kpi ? mysqli_fetch_assoc($res_kpi) : [];

    // 2. Data statis per jam (00 - 23)
    $traffic_jam    = array_fill(0, 24, 0);
    $pendapatan_jam = array_fill(0, 24, 0.0);

    $sql_jam = "SELECT 
                    HOUR(t.waktu_transaksi) AS jam,
                    COUNT(t.id_transaksi) AS jml_pengunjung,
                    COALESCE(SUM(t.nominal), 0) AS total_pendapatan
                $join_sql
                $where_sql
                GROUP BY HOUR(t.waktu_transaksi)";

    $res_jam = mysqli_query($conn, $sql_jam);
    if (!$res_jam) {
        http_response_code(500);
        echo json_encode(['error' => 'Query gagal: ' . mysqli_error($conn)]);
        exit();
    }

    while ($row = mysqli_fetch_assoc($res_jam)) {
        $h = (int)$row['jam'];
        if ($h >= 0 && $h <= 23) {
            $traffic_jam[$h]    = (int)$row['jml_pengunjung'];
            $pendapatan_jam[$h] = (float)$row['total_pendapatan'];
        }
    }

    // 3. Data statis per bulan (Jan - Des)
    $bulan_traffic    = array_fill(0, 12, 0);
    $bulan_pendapatan = array_fill(0, 12, 0.0);

    $sql_bulan = "SELECT 
                      MONTH(t.waktu_transaksi) AS bln,
                      COUNT(t.id_transaksi) AS jml_pengunjung,
                      COALESCE(SUM(t.nominal), 0) AS total_pendapatan
                  $join_sql
                  $where_sql
                  GROUP BY MONTH(t.waktu_transaksi)";

    $res_bulan = mysqli_query($conn, $sql_bulan);
    if ($res_bulan) {
        while ($row = mysqli_fetch_assoc($res_bulan)) {
            $idx = (int)$row['bln'] - 1;
            if ($idx >= 0 && $idx <= 11) {
                $bulan_traffic[$idx]    = (int)$row['jml_pengunjung'];
                $bulan_pendapatan[$idx] = (float)$row['total_pendapatan'];
            }
        }
    }

    echo json_encode([
        'stats' => [
            'transaksi'  => number_format((int)($kpi_row['total_trx'] ?? 0), 0, ',', '.'),
            'gate'       => (string)($kpi_row['total_gate'] ?? 0),
            'area'       => (string)($kpi_row['total_area'] ?? 0),
            'mitra'      => (string)($kpi_row['total_mitra'] ?? 0),
            'pendapatan' => 'Rp ' . number_format((float)($kpi_row['total_pendapatan'] ?? 0), 0, ',', '.')
        ],
        'traffic'          => array_values($traffic_jam),
        'pendapatan'       => array_values($pendapatan_jam),
        'bulan_traffic'    => array_values($bulan_traffic),
        'bulan_pendapatan' => array_values($bulan_pendapatan)
    ]);
    exit();
}

// =========================================================================
// 1. QUERY 6 KARTU KPI REAL-TIME DARI DATABASE
// =========================================================================
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

// =========================================================================
// 2. QUERY OPSI DROPDOWN FILTER DARI DATABASE
// =========================================================================
// Mitra ikut mengambil kategori_mitra supaya dropdown Mitra bisa difilter per kategori
$opt_mitra  = mysqli_query($conn, "SELECT DISTINCT nama_mitra, kategori_mitra FROM mitra ORDER BY nama_mitra ASC");
$opt_area   = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi ORDER BY nama_area ASC");
$opt_lokasi = mysqli_query($conn, "SELECT DISTINCT nama_lokasi FROM area_lokasi ORDER BY nama_lokasi ASC");

// =========================================================================
// 3. QUERY DATASET GRAFIK PER AREA (SUMBU X = NAMA AREA)
// =========================================================================
$query_chart_area = "SELECT 
                        a.nama_area,
                        COUNT(CASE WHEN DATE(t.waktu_transaksi) = CURDATE() AND t.status_transaksi = 'Berhasil' THEN 1 END) AS traffic_harian,
                        COUNT(CASE WHEN MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE()) AND t.status_transaksi = 'Berhasil' THEN 1 END) AS traffic_bulanan,
                        COALESCE(SUM(CASE WHEN DATE(t.waktu_transaksi) = CURDATE() AND t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS pend_harian,
                        COALESCE(SUM(CASE WHEN MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE()) AND t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS pend_bulanan
                    FROM area_lokasi a
                    LEFT JOIN transaksi t ON a.id_area = t.id_area
                    GROUP BY a.nama_area
                    ORDER BY a.nama_area ASC";

$res_chart_area     = mysqli_query($conn, $query_chart_area);
$area_labels        = [];
$traffic_harian     = [];
$traffic_bulanan    = [];
$pendapatan_harian  = [];
$pendapatan_bulanan = [];

while ($ca = mysqli_fetch_assoc($res_chart_area)) {
    $area_labels[]        = $ca['nama_area'];
    $traffic_harian[]     = (int)$ca['traffic_harian'];
    $traffic_bulanan[]    = (int)$ca['traffic_bulanan'];
    $pendapatan_harian[]  = (float)$ca['pend_harian'];
    $pendapatan_bulanan[] = (float)$ca['pend_bulanan'];
}

// =========================================================================
// 4. LABEL STATIS: JAM 00 - 23 DAN BULAN
// =========================================================================
$jam_labels = [];
for ($i = 0; $i <= 23; $i++) {
    $jam_labels[] = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}
$bulan_nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== Header chart & tombol toggle (Harian/Bulanan) ===== */
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
        .chart-card .toggle-buttons {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .chart-card .toggle-buttons button {
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
        .chart-card .toggle-buttons button:hover:not(.active) {
            background: #eef3f9;
            color: #004687;
        }
        .chart-card .toggle-buttons button.active {
            background: #004b87;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0, 75, 135, 0.28);
        }

        /* ===== Kartu statistik (KPI) tata letak 3 kolom ===== */
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
        .stats-grid .stat-card .card-icon {
            flex: 0 0 36% !important;
            max-width: 36%;
            display: flex !important;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            color: #ffffff !important;
            padding: 0;
            margin: 0;
        }
        .stats-grid .stat-card .card-icon svg {
            width: 58px;
            height: 58px;
            display: block;
        }
        .stats-grid .stat-card .card-info {
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            flex: 1 1 auto;
            min-width: 0;
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

        /* ===== Periode: kalender rentang tanggal ===== */
        .range-picker { position: relative; }
        .range-picker input {
            width: 100%;
            box-sizing: border-box;
            height: 40px;
            padding: 0 38px 0 16px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #1e293b;
            cursor: pointer;
        }
        .range-picker input::placeholder { color: #6b7280; }
        .range-picker input:focus { outline: none; border-color: #004687; }
        .range-picker .range-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            font-size: 14px;
            pointer-events: none;
        }
        .range-popup {
            position: fixed;
            z-index: 1000;
            background: #fafafa;
            border-radius: 14px;
            padding: 16px 20px 14px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
            overflow-y: auto;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        .range-popup[hidden] { display: none; }
        .rp-month + .rp-month { margin-top: 14px; }
        .rp-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 28px;
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
        }
        .rp-nav {
            border: none;
            background: transparent;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            color: #475569;
            font-size: 13px;
        }
        .rp-nav:hover { background: #e5eaf1; }
        .rp-grid { display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 2px; }
        .rp-dow {
            text-align: center;
            font-size: 10px;
            letter-spacing: 0.06em;
            color: #9ca3af;
            padding: 6px 0 8px;
        }
        .rp-day {
            border: none;
            background: transparent;
            height: 38px;
            padding: 0;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rp-day span {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .rp-day:hover span { background: #e5eaf1; }
        .rp-day.rp-today span { font-weight: 700; color: #004687; }
        .rp-day.rp-mid { background: #dbeafe; }
        .rp-day.rp-start span, .rp-day.rp-end span {
            background: #1a6fe8;
            color: #ffffff;
            font-weight: 600;
        }
        .rp-day.rp-start.rp-band { background: linear-gradient(to right, transparent 50%, #dbeafe 50%); }
        .rp-day.rp-end.rp-band { background: linear-gradient(to left, transparent 50%, #dbeafe 50%); }
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

    <!-- Area Konten Utama -->
    <main class="main-content">
        <header class="top-header">
            <p class="welcome-text">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_user'] ?? 'Admin'); ?>!</p>
            <h1 class="page-header-title">Dashboard</h1>
        </header>

        <!-- Filter Card 5 Input -->
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
                            <option value="<?= htmlspecialchars($m['nama_mitra']); ?>" data-kategori="<?= htmlspecialchars($m['kategori_mitra'] ?? ''); ?>"><?= htmlspecialchars($m['nama_mitra']); ?></option>
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
                    <div class="range-picker" id="rangePicker">
                        <input type="text" id="filterPeriode" placeholder="Pilih periode" readonly autocomplete="off" aria-haspopup="dialog">
                        <i class="fa-regular fa-calendar range-icon"></i>
                        <div class="range-popup" id="rangePopup" hidden></div>
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="button" class="btn-reset" onclick="resetDashboardFilter()">Reset</button>
                    <button type="button" class="btn-filter" onclick="applyDashboardFilter()">Filter</button>
                </div>
            </div>
        </section>

        <!-- 6 Stat Cards -->
        <section class="stats-grid">
            <a href="detailTransaksi.php" class="stat-card clickable-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-20 24 24)"><mask id="mTicket" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="3" cy="24" r="3.8" fill="#000"/><circle cx="45" cy="24" r="3.8" fill="#000"/><rect x="15" y="17.2" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="22.5" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="27.8" width="18" height="3" rx="1.2" fill="#000"/></mask><rect x="3" y="12" width="42" height="24" rx="5.5" mask="url(#mTicket)"/></g></svg></div>
                <div class="card-info">
                    <span class="card-label">Total Transaksi</span>
                    <h2 class="card-value" id="statTransaksi"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-database"></i> Data real-time</span>
                </div>
            </a>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-8 24 18)"><mask id="mGate" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><rect x="9" y="9.5" width="26" height="9" rx="1.6" fill="#000"/></mask><rect x="4" y="5" width="36" height="22" rx="3.6" mask="url(#mGate)"/><rect x="15" y="12" width="14" height="13" rx="1.3"/></g><path d="M24 45V33.5M19 38.5l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Gate Aktif</span>
                    <h2 class="card-value" id="statGate"><?= $total_gate; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><mask id="mPin" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="24" cy="16.5" r="5.4" fill="#000"/></mask><path d="M24 3C16.3 3 10 9.2 10 16.8 10 26.8 24 38 24 38S38 26.8 38 16.8C38 9.2 31.7 3 24 3z" mask="url(#mPin)"/><ellipse cx="24" cy="38" rx="18.5" ry="5.4" fill="none" stroke="currentColor" stroke-width="2"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Area</span>
                    <h2 class="card-value" id="statArea"><?= $total_area; ?></h2>
                </div>
            </div>

            <a href="daftarMitra.php" class="stat-card clickable-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><circle cx="12" cy="11" r="4.8"/><path d="M3.5 26c0-5 3.8-8 8.5-8s8.5 3 8.5 8z"/><circle cx="36" cy="31" r="4.8"/><path d="M27.5 46c0-5 3.8-8 8.5-8s8.5 3 8.5 8z"/><path d="M25 31A11 11 0 0 1 36 20M18 31A18 18 0 0 1 36 13" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Mitra Aktif</span>
                    <h2 class="card-value" id="statMitra"><?= $total_mitra; ?></h2>
                </div>
            </a>

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

// ===== Filter bertingkat: Kategori Mitra -> Mitra =====
const selKategori = document.getElementById('filterKategori');
const selMitra    = document.getElementById('filterMitra');

// Simpan semua mitra (beserta kategorinya) sekali saat halaman dimuat
const semuaMitra = Array.from(selMitra.options)
    .filter(o => o.value !== '')
    .map(o => ({ nama: o.value, kategori: o.dataset.kategori || '' }));

function updateOpsiMitra() {
    const kat     = selKategori.value;
    const dipilih = selMitra.value;
    const hasil   = semuaMitra.filter(m => !kat || m.kategori === kat);

    selMitra.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = (kat && hasil.length === 0) ? 'Tidak ada mitra di kategori ini' : 'Pilih mitra';
    selMitra.appendChild(placeholder);

    hasil.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.nama;
        opt.dataset.kategori = m.kategori;
        opt.textContent = m.nama;
        selMitra.appendChild(opt);
    });

    // Pertahankan pilihan mitra sebelumnya hanya jika masih cocok dengan kategori
    selMitra.value = hasil.some(m => m.nama === dipilih) ? dipilih : '';
}

selKategori.addEventListener('change', updateOpsiMitra);

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

const jamLabels   = <?= json_encode($jam_labels); ?>;
const bulanLabels = <?= json_encode($bulan_nama); ?>;

// Teks keterangan periode untuk mode tanpa filter
const bulanPanjang  = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const sekarang      = new Date();
const labelHariIni  = sekarang.getDate() + ' ' + bulanLabels[sekarang.getMonth()] + ' ' + sekarang.getFullYear();
const labelBulanIni = bulanPanjang[sekarang.getMonth()] + ' ' + sekarang.getFullYear();

let isFiltered       = false;
let periodLabel      = 'Semua Waktu';
let filteredData     = null;
let trafficPeriod    = 'harian';
let pendapatanPeriod = 'harian';

function formatHourlyTooltipTitle(context) {
    const chart  = context[0].chart;
    const label  = context[0].label;
    const period = (chart.canvas.id === 'trafficChart') ? trafficPeriod : pendapatanPeriod;
    if (isFiltered && period === 'harian') {
        return `Jam ${label}:00 - ${label}:59`;
    }
    if (!isFiltered) {
        return `${label} · ${period === 'harian' ? 'Hari ini' : 'Bulan ini'}`;
    }
    return label;
}

// Keterangan tambahan tooltip (mode filter): tegaskan bahwa angka adalah akumulasi
function formatAkumulasiNote(ctx) {
    if (!isFiltered) return '';
    const rentang = (periodLabel && periodLabel !== 'Semua Waktu') ? periodLabel : 'seluruh data';
    return 'Akumulasi ' + rentang;
}

// INISIALISASI CHART
const COLOR_BAR       = '#FFE580';
const COLOR_BAR_HOVER = '#F2C400';
const COLOR_LINE      = '#F7D95A';
const COLOR_LINE_DARK = '#C99A00';

Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#6b7280';

const plotAreaBg = {
    id: 'plotAreaBg',
    beforeDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;
        ctx.save();
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(chartArea.left, chartArea.top, chartArea.right - chartArea.left, chartArea.bottom - chartArea.top);
        ctx.restore();
    }
};

function xAxisTitlePlugin(getPeriod) {
    return {
        id: 'xAxisTitleAuto',
        beforeUpdate(chart) {
            const p = getPeriod();
            let text;
            if (isFiltered) {
                text = (p === 'harian') ? 'Total per jam · ' + periodLabel : 'Total per bulan · ' + periodLabel;
            } else {
                text = (p === 'harian') ? 'Area · Hari ini (' + labelHariIni + ')' : 'Area · Bulan ini (' + labelBulanIni + ')';
            }
            chart.options.scales.x.title.text = text;
        }
    };
}

function makeXScale() {
    return {
        grid: { display: false },
        border: { display: false },
        ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 11 } },
        title: { display: true, text: 'Area', color: '#111827', font: { size: 11, weight: '600' }, padding: { top: 10 } }
    };
}

const emptyNote = {
    id: 'emptyNote',
    afterDraw(chart) {
        const data = chart.data.datasets[0].data;
        if (data.some(v => v > 0)) return;
        const period = (chart.canvas.id === 'trafficChart') ? trafficPeriod : pendapatanPeriod;
        const pesan  = isFiltered ? 'Belum ada transaksi pada periode ini'
                     : (period === 'harian' ? 'Belum ada transaksi hari ini' : 'Belum ada transaksi bulan ini');
        const { ctx, chartArea } = chart;
        ctx.save();
        ctx.fillStyle = '#9ca3af';
        ctx.font = "500 13px 'Poppins', sans-serif";
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(pesan, (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
        ctx.restore();
    }
};

const tooltipStyle = {
    backgroundColor: '#004b87',
    padding: 12,
    cornerRadius: 10,
    displayColors: false,
    titleFont: { size: 12, weight: '600' },
    bodyFont: { size: 12 }
};

const legendStyle = {
    align: 'end',
    position: 'top',
    labels: { usePointStyle: true, pointStyle: 'rect', boxWidth: 10, boxHeight: 10 }
};

const hoverCursor = (evt, els) => {
    evt.native.target.style.cursor = els.length ? 'pointer' : 'default';
};

// TRAFFIC (BAR)
const ctxTraffic = document.getElementById('trafficChart').getContext('2d');
const trafficChart = new Chart(ctxTraffic, {
    type: 'bar',
    data: {
        labels: areaDataset.labels,
        datasets: [{
            label: 'Pengunjung',
            data: areaDataset.trafficHarian,
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
                    label: ctx => `Total Pengunjung: ${ctx.parsed.y.toLocaleString('id-ID')} orang`,
                    afterLabel: formatAkumulasiNote
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
    plugins: [plotAreaBg, emptyNote, xAxisTitlePlugin(() => trafficPeriod)]
});

// PENDAPATAN (LINE)
const ctxPendapatan = document.getElementById('pendapatanChart').getContext('2d');
const pendapatanChart = new Chart(ctxPendapatan, {
    type: 'line',
    data: {
        labels: areaDataset.labels,
        datasets: [{
            label: 'Pendapatan',
            data: areaDataset.pendapatanHarian,
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
                    label: ctx => `Total Pendapatan: Rp ${ctx.parsed.y.toLocaleString('id-ID')}`,
                    afterLabel: formatAkumulasiNote
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
    plugins: [plotAreaBg, emptyNote, xAxisTitlePlugin(() => pendapatanPeriod)]
});

// LOGIKA RENDER & TOGGLE CONTROLS
function renderTrafficChart() {
    if (!isFiltered || !filteredData) {
        trafficChart.data.labels = areaDataset.labels;
        trafficChart.data.datasets[0].data = (trafficPeriod === 'harian') ? areaDataset.trafficHarian : areaDataset.trafficBulanan;
    } else if (trafficPeriod === 'harian') {
        trafficChart.data.labels = jamLabels;
        trafficChart.data.datasets[0].data = filteredData.traffic;
    } else {
        trafficChart.data.labels = bulanLabels;
        trafficChart.data.datasets[0].data = filteredData.bulan_traffic;
    }
    trafficChart.update();
}

function renderPendapatanChart() {
    if (!isFiltered || !filteredData) {
        pendapatanChart.data.labels = areaDataset.labels;
        pendapatanChart.data.datasets[0].data = (pendapatanPeriod === 'harian') ? areaDataset.pendapatanHarian : areaDataset.pendapatanBulanan;
    } else if (pendapatanPeriod === 'harian') {
        pendapatanChart.data.labels = jamLabels;
        pendapatanChart.data.datasets[0].data = filteredData.pendapatan;
    } else {
        pendapatanChart.data.labels = bulanLabels;
        pendapatanChart.data.datasets[0].data = filteredData.bulan_pendapatan;
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

async function applyDashboardFilter() {
    const range  = rangePicker.getRange();
    const params = new URLSearchParams();
    params.set('ajax', '1');

    if (range) {
        params.set('dari', range.from);
        params.set('sampai', range.to);
    }

    const kat = document.getElementById('filterKategori').value;
    const mtr = document.getElementById('filterMitra').value;
    const are = document.getElementById('filterArea').value;
    const lok = document.getElementById('filterLokasi').value;
    if (kat) params.set('kategori', kat);
    if (mtr) params.set('mitra', mtr);
    if (are) params.set('area', are);
    if (lok) params.set('lokasi', lok);

    const btn = document.querySelector('.filter-buttons .btn-filter');
    try {
        btn.disabled = true;
        const res = await fetch('dashboard.php?' + params.toString(), { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || ('HTTP ' + res.status));
        filteredData = data;
    } catch (err) {
        console.error(err);
        alert('Data periode gagal dimuat: ' + err.message);
        return;
    } finally {
        btn.disabled = false;
    }

    if (filteredData.stats) {
        document.getElementById('statTransaksi').innerText  = filteredData.stats.transaksi;
        document.getElementById('statGate').innerText       = filteredData.stats.gate;
        document.getElementById('statArea').innerText       = filteredData.stats.area;
        document.getElementById('statMitra').innerText      = filteredData.stats.mitra;
        document.getElementById('statPendapatan').innerText = filteredData.stats.pendapatan;
    }

    periodLabel = rangePicker.label() || 'Semua Waktu';
    isFiltered = true;
    renderTrafficChart();
    renderPendapatanChart();
}

function resetDashboardFilter() {
    document.getElementById('filterKategori').value = "";
    updateOpsiMitra(); // kembalikan dropdown Mitra ke daftar lengkap
    document.getElementById('filterMitra').value = "";
    document.getElementById('filterArea').value = "";
    document.getElementById('filterLokasi').value = "";
    document.getElementById('filterPeriode').value = "";
    isFiltered   = false;
    filteredData = null;
    periodLabel  = 'Semua Waktu';
    rangePicker.clear();
    document.getElementById('statTransaksi').innerText  = initialStats.transaksi;
    document.getElementById('statGate').innerText       = initialStats.gate;
    document.getElementById('statArea').innerText       = initialStats.area;
    document.getElementById('statMitra').innerText      = initialStats.mitra;
    document.getElementById('statPendapatan').innerText = initialStats.pendapatan;
    renderTrafficChart();
    renderPendapatanChart();
}

// ===== Kalender rentang tanggal (Periode) =====
const rangePicker = (function () {
    const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const DAYS   = ['MIN', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB'];
    const input  = document.getElementById('filterPeriode');
    const popup  = document.getElementById('rangePopup');
    let from = null, to = null;
    let viewYear = 0, viewMonth = 0;

    const pad      = n => String(n).padStart(2, '0');
    const iso      = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
    const show     = s => `${s.slice(8, 10)}/${s.slice(5, 7)}/${s.slice(0, 4)}`;
    const todayIso = (() => { const n = new Date(); return iso(n.getFullYear(), n.getMonth(), n.getDate()); })();

    function label() {
        if (!from) return '';
        const end = to || from;
        return end === from ? show(from) : `${show(from)} - ${show(end)}`;
    }

    function resetView() {
        const ref   = from ? new Date(from + 'T00:00:00') : new Date();
        const first = from ? new Date(ref.getFullYear(), ref.getMonth(), 1) : new Date(ref.getFullYear(), ref.getMonth() - 1, 1);
        viewYear  = first.getFullYear();
        viewMonth = first.getMonth();
    }

    function monthHtml(y, m, nav) {
        const firstDow = new Date(y, m, 1).getDay();
        const total    = new Date(y, m + 1, 0).getDate();
        const end      = to || from;
        const ranged   = from && end && from !== end;

        let h = `<div class="rp-month"><div class="rp-head"><span>${MONTHS[m]} ${y}</span>`;
        if (nav === 'prev') h += '<button type="button" class="rp-nav" data-nav="-1" aria-label="Bulan sebelumnya"><i class="fa-solid fa-chevron-up"></i></button>';
        if (nav === 'next') h += '<button type="button" class="rp-nav" data-nav="1" aria-label="Bulan berikutnya"><i class="fa-solid fa-chevron-down"></i></button>';
        h += '</div><div class="rp-grid">';
        h += DAYS.map(d => `<div class="rp-dow">${d}</div>`).join('');

        for (let i = 0; i < firstDow; i++) h += '<div></div>';
        for (let d = 1; d <= total; d++) {
            const s = iso(y, m, d);
            let cls = 'rp-day';
            if (from && s === from) cls += ' rp-start' + (ranged ? ' rp-band' : '');
            if (end && s === end)   cls += ' rp-end' + (ranged ? ' rp-band' : '');
            if (ranged && s > from && s < end) cls += ' rp-mid';
            if (s === todayIso) cls += ' rp-today';
            h += `<button type="button" class="${cls}" data-d="${s}"><span>${d}</span></button>`;
        }
        return h + '</div></div>';
    }

    function render() {
        const nm = viewMonth === 11 ? 0 : viewMonth + 1;
        const ny = viewMonth === 11 ? viewYear + 1 : viewYear;
        popup.innerHTML = monthHtml(viewYear, viewMonth, 'prev') + monthHtml(ny, nm, 'next');
    }

    function position() {
        const r = input.getBoundingClientRect();
        popup.style.top       = (r.bottom + 6) + 'px';
        popup.style.left      = r.left + 'px';
        popup.style.width     = Math.min(Math.max(r.width, 340), 440) + 'px';
        popup.style.maxHeight = Math.max(260, window.innerHeight - r.bottom - 20) + 'px';
    }

    function open() { resetView(); render(); popup.hidden = false; position(); }
    function close() { popup.hidden = true; }

    function pick(s) {
        if (!from || to) {
            from = s;
            to   = null;
            input.value = label();
            render();
        } else {
            if (s < from) { to = from; from = s; } else { to = s; }
            input.value = label();
            close();
        }
    }

    input.addEventListener('click', e => { e.stopPropagation(); popup.hidden ? open() : close(); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === 'ArrowDown') { e.preventDefault(); if (popup.hidden) open(); }
        if (e.key === 'Escape') close();
    });

    popup.addEventListener('click', e => {
        e.stopPropagation();
        const nav = e.target.closest('[data-nav]');
        if (nav) {
            const first = new Date(viewYear, viewMonth + parseInt(nav.dataset.nav), 1);
            viewYear  = first.getFullYear();
            viewMonth = first.getMonth();
            render();
            return;
        }
        const day = e.target.closest('[data-d]');
        if (day) pick(day.dataset.d);
    });

    document.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', () => { if (!popup.hidden) position(); });
    window.addEventListener('scroll', () => { if (!popup.hidden) position(); }, true);

    return {
        getRange: () => from ? { from: from, to: to || from } : null,
        label: label,
        clear: () => { from = null; to = null; input.value = ''; close(); }
    };
})();
</script>
</body>
</html>