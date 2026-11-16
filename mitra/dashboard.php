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

// 6. Data Grafik Harian Per Jam, dibagi 3 shift (Pagi 00-12, Siang 13-18, Malam 19-23)
$hourly_traffic    = array_fill(0, 24, 0);
$hourly_pendapatan = array_fill(0, 24, 0);

$q_harian = mysqli_query($conn, "SELECT 
    HOUR(t.waktu_transaksi) AS jam_trx,
    COUNT(t.id_transaksi) AS jml_pengunjung,
    COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS omzet
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $sql_where_trx
    GROUP BY HOUR(t.waktu_transaksi)");

if ($q_harian) {
    while ($rh = mysqli_fetch_assoc($q_harian)) {
        $h = (int)$rh['jam_trx'];
        $hourly_traffic[$h]    = (int)$rh['jml_pengunjung'];
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

// Shift default mengikuti jam saat ini
$jam_now = (int)date('G');
$default_shift = ($jam_now <= 12) ? 'pagi' : (($jam_now <= 18) ? 'siang' : 'malam');

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

        /* ===== Header chart & tombol toggle (sama dengan dashboard admin) ===== */
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

        @media (max-width: 992px) {
            .filter-grid-3 {
                grid-template-columns: 1fr;
            }
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
                    <a href="analisisTransaksi.php">Analisis Transaksi</a>
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
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-20 24 24)"><mask id="mTicket" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="3" cy="24" r="3.8" fill="#000"/><circle cx="45" cy="24" r="3.8" fill="#000"/><rect x="15" y="17.2" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="22.5" width="18" height="3" rx="1.2" fill="#000"/><rect x="15" y="27.8" width="18" height="3" rx="1.2" fill="#000"/></mask><rect x="3" y="12" width="42" height="24" rx="5.5" mask="url(#mTicket)"/></g></svg></div>
                <div class="card-info">
                    <span class="card-label">Total Transaksi</span>
                    <h2 class="card-value"><?= number_format($total_transaksi, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> Data real-time</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><g transform="rotate(-8 24 18)"><mask id="mGate" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><rect x="9" y="9.5" width="26" height="9" rx="1.6" fill="#000"/></mask><rect x="4" y="5" width="36" height="22" rx="3.6" mask="url(#mGate)"/><rect x="15" y="12" width="14" height="13" rx="1.3"/></g><path d="M24 45V33.5M19 38.5l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Gate Aktif</span>
                    <h2 class="card-value"><?= $gate_aktif; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><mask id="mPin" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="24" cy="16.5" r="5.4" fill="#000"/></mask><path d="M24 3C16.3 3 10 9.2 10 16.8 10 26.8 24 38 24 38S38 26.8 38 16.8C38 9.2 31.7 3 24 3Z" mask="url(#mPin)"/><ellipse cx="24" cy="38" rx="18.5" ry="5.4" fill="none" stroke="currentColor" stroke-width="2"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Area</span>
                    <h2 class="card-value"><?= $jml_area; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><mask id="mProj" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="28" cy="22.5" r="7.2" fill="#000"/><circle cx="11" cy="22.5" r="1.9" fill="#000"/></mask><rect x="3" y="11" width="42" height="23" rx="6.5" mask="url(#mProj)"/><circle cx="28" cy="22.5" r="3.5"/><rect x="22" y="36" width="12" height="3.2" rx="1.6"/></svg></div>
                <div class="card-info">
                    <span class="card-label">Perangkat Aktif</span>
                    <div class="device-sub-badges">
                        <span><i class="dev-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="6" height="14" rx="1.5"/><circle cx="9" cy="10.5" r="1"/><line x1="10" y1="10.5" x2="21" y2="10.5"/><line x1="9.8" y1="11.3" x2="17" y2="18"/><line x1="8.6" y1="11.6" x2="12" y2="20"/></svg></i> <?= $turnstile_aktif; ?> Turnstile</span>
                        <span><i class="dev-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 8.5l12-3.5 2.2 6.3-12 3.5z"/><path d="M16 8l4.5-1.2v4.6L17.6 12"/><path d="M7.5 15.3V20H13"/></svg></i> <?= $cctv_aktif; ?> CCTV</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-icon"><svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true"><path d="M5 20L15 10l7 6L34 5" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M26 4.6h8.4V13" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><g transform="rotate(-8 24 35)"><mask id="mMoney" maskUnits="userSpaceOnUse" x="-20" y="-20" width="90" height="90"><rect x="-20" y="-20" width="90" height="90" fill="#fff"/><circle cx="24" cy="35" r="6.4" fill="#000"/><circle cx="10.5" cy="35" r="1.9" fill="#000"/><circle cx="37.5" cy="35" r="1.9" fill="#000"/></mask><rect x="4" y="26" width="40" height="18" rx="3.2" mask="url(#mMoney)"/><circle cx="24" cy="35" r="3.1"/></g></svg></div>
                <div class="card-info">
                    <span class="card-label">Pendapatan</span>
                    <h2 class="card-value">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-caret-up"></i> Data real-time</span>
                </div>
            </div>
        </section>

        <!-- Grafik Traffic & Pendapatan -->
        <section class="charts-grid">
            <!-- Grafik Kiri: Traffic (Bar Chart Kuning) -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
                        <h3 class="chart-title">Traffic</h3>
                    </div>
                    <div class="chart-controls-right">
                        <!-- Sub-filter Pagi / Siang / Malam (tampil saat mode Harian) -->
                        <div class="shift-toggle-buttons" id="trafficShiftGroup">
                            <button type="button" class="<?= $default_shift === 'pagi' ? 'active' : ''; ?>" onclick="switchTrafficShift('pagi', this)">Pagi</button>
                            <button type="button" class="<?= $default_shift === 'siang' ? 'active' : ''; ?>" onclick="switchTrafficShift('siang', this)">Siang</button>
                            <button type="button" class="<?= $default_shift === 'malam' ? 'active' : ''; ?>" onclick="switchTrafficShift('malam', this)">Malam</button>
                        </div>
                        <!-- Toggle Harian / Bulanan -->
                        <div class="toggle-buttons">
                            <button type="button" id="btnTrafficHarian" class="active" onclick="switchTraffic('harian')">Harian</button>
                            <button type="button" id="btnTrafficBulanan" onclick="switchTraffic('bulanan')">Bulanan</button>
                        </div>
                    </div>
                </div>
                <div class="chart-canvas-wrapper" style="height: 290px;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>

            <!-- Grafik Kanan: Pendapatan (Line Chart Kuning) -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-group">
                        <span class="chart-title-icon"><i class="fa-solid fa-chart-line"></i></span>
                        <h3 class="chart-title">Pendapatan</h3>
                    </div>
                    <div class="chart-controls-right">
                        <!-- Sub-filter Pagi / Siang / Malam (tampil saat mode Harian) -->
                        <div class="shift-toggle-buttons" id="omzetShiftGroup">
                            <button type="button" class="<?= $default_shift === 'pagi' ? 'active' : ''; ?>" onclick="switchOmzetShift('pagi', this)">Pagi</button>
                            <button type="button" class="<?= $default_shift === 'siang' ? 'active' : ''; ?>" onclick="switchOmzetShift('siang', this)">Siang</button>
                            <button type="button" class="<?= $default_shift === 'malam' ? 'active' : ''; ?>" onclick="switchOmzetShift('malam', this)">Malam</button>
                        </div>
                        <!-- Toggle Harian / Bulanan -->
                        <div class="toggle-buttons">
                            <button type="button" id="btnOmzetHarian" class="active" onclick="switchOmzet('harian')">Harian</button>
                            <button type="button" id="btnOmzetBulanan" onclick="switchOmzet('bulanan')">Bulanan</button>
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
        const currentGroup = element.parentElement;
        document.querySelectorAll('.menu-group').forEach(group => {
            if (group !== currentGroup) group.classList.remove('active');
        });
        currentGroup.classList.toggle('active');
    }

    const shiftDatasets = <?= json_encode($shift_data); ?>;
    let trafficMode = 'harian';
    let omzetMode = 'harian';
    let trafficShift = '<?= $default_shift; ?>';
    let omzetShift = '<?= $default_shift; ?>';

    const bulananLabels = <?= json_encode($bulanan_labels); ?>;
    const bulananTraffic = <?= json_encode($bulanan_traffic); ?>;
    const bulananOmzet = <?= json_encode($bulanan_pendapatan); ?>;

    // ===== Judul tooltip: "dd/mm/yyyy (08:00 - 08:59)" untuk harian, nama bulan untuk bulanan =====
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

    // ===== Gaya chart interaktif (sama dengan dashboard admin) =====
    const COLOR_BAR        = '#FFE580';   // kuning muda (normal)
    const COLOR_BAR_HOVER  = '#F2C400';   // kuning solid (saat di-hover)
    const COLOR_LINE       = '#F7D95A';
    const COLOR_LINE_DARK  = '#C99A00';

    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.color = '#6b7280';

    // Latar abu-abu muda di area plot
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

    function makeXScale(titleText) {
        return {
            grid: { display: false },
            border: { display: false },
            ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 11 } },
            title: { display: true, text: titleText, color: '#111827',
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

    // ---------- 1. TRAFFIC (BAR) ----------
    const trafficChart = new Chart(document.getElementById('trafficChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: shiftDatasets[trafficShift].labels,
            datasets: [{
                label: 'Pengunjung',
                data: shiftDatasets[trafficShift].traffic,
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
                x: makeXScale('Tanggal & Jam'),
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#e5e7eb' },
                    ticks: { precision: 0 }
                }
            }
        },
        plugins: [plotAreaBg]
    });

    // ---------- 2. PENDAPATAN (LINE) ----------
    const pendapatanChart = new Chart(document.getElementById('pendapatanChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: shiftDatasets[omzetShift].labels,
            datasets: [{
                label: 'Pendapatan',
                data: shiftDatasets[omzetShift].pendapatan,
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
                x: makeXScale('Tanggal & Jam'),
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
        plugins: [plotAreaBg]
    });

    // ---------- Render ulang chart sesuai mode (Harian/Bulanan) & shift ----------
    function renderTraffic() {
        const group = document.getElementById('trafficShiftGroup');
        if (trafficMode === 'harian') {
            group.style.display = 'inline-flex';
            trafficChart.data.labels = shiftDatasets[trafficShift].labels;
            trafficChart.data.datasets[0].data = shiftDatasets[trafficShift].traffic;
            trafficChart.options.scales.x.title.text = 'Tanggal & Jam';
            trafficChart.options.scales.x.ticks.font.size = (trafficShift === 'pagi') ? 9.5 : 11;
        } else {
            group.style.display = 'none';
            trafficChart.data.labels = bulananLabels;
            trafficChart.data.datasets[0].data = bulananTraffic;
            trafficChart.options.scales.x.title.text = 'Bulan';
            trafficChart.options.scales.x.ticks.font.size = 11;
        }
        trafficChart.update();
    }

    function renderOmzet() {
        const group = document.getElementById('omzetShiftGroup');
        if (omzetMode === 'harian') {
            group.style.display = 'inline-flex';
            pendapatanChart.data.labels = shiftDatasets[omzetShift].labels;
            pendapatanChart.data.datasets[0].data = shiftDatasets[omzetShift].pendapatan;
            pendapatanChart.options.scales.x.title.text = 'Tanggal & Jam';
            pendapatanChart.options.scales.x.ticks.font.size = (omzetShift === 'pagi') ? 9.5 : 11;
        } else {
            group.style.display = 'none';
            pendapatanChart.data.labels = bulananLabels;
            pendapatanChart.data.datasets[0].data = bulananOmzet;
            pendapatanChart.options.scales.x.title.text = 'Bulan';
            pendapatanChart.options.scales.x.ticks.font.size = 11;
        }
        pendapatanChart.update();
    }

    function switchTraffic(mode) {
        trafficMode = mode;
        document.getElementById('btnTrafficHarian').classList.toggle('active', mode === 'harian');
        document.getElementById('btnTrafficBulanan').classList.toggle('active', mode === 'bulanan');
        renderTraffic();
    }

    function switchOmzet(mode) {
        omzetMode = mode;
        document.getElementById('btnOmzetHarian').classList.toggle('active', mode === 'harian');
        document.getElementById('btnOmzetBulanan').classList.toggle('active', mode === 'bulanan');
        renderOmzet();
    }

    function switchTrafficShift(shift, btn) {
        trafficShift = shift;
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTraffic();
    }

    function switchOmzetShift(shift, btn) {
        omzetShift = shift;
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderOmzet();
    }
</script>
</body>
</html>