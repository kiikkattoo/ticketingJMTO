<?php 
session_start();
require '../koneksi.php';
include '../includes/load_tema_mitra.php'; 

// Proteksi Halaman & Role Mitra
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}
if (strtolower($_SESSION["nama_role"] ?? '') !== 'mitra') {
    header("Location: ../admin/cctv.php");
    exit();
}

$id_mitra_login = mysqli_real_escape_string($conn, $_SESSION['id_mitra'] ?? '');

// Tangkap Parameter Filter GET
$f_area   = $_GET['area'] ?? '';
$f_lokasi = $_GET['lokasi'] ?? '';

// 1. Opsi Dropdown Area & Lokasi (Terkunci hanya milik Mitra yang login)
$opt_area   = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");
$opt_lokasi = mysqli_query($conn, "SELECT DISTINCT nama_lokasi FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_lokasi ASC");

// 2. Bangun Klausa WHERE (Selalu terkunci ke kode_mitra & jenis_perangkat = 'CCTV')
$where = [
    "a.kode_mitra = '$id_mitra_login'",
    "p.jenis_perangkat = 'CCTV'"
];

if (!empty($f_area)) {
    $safe_area = mysqli_real_escape_string($conn, $f_area);
    $where[] = "a.nama_area = '$safe_area'";
}
if (!empty($f_lokasi)) {
    $safe_lokasi = mysqli_real_escape_string($conn, $f_lokasi);
    $where[] = "a.nama_lokasi = '$safe_lokasi'";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// 3. Query Hitung KPI CCTV Aktif & CCTV Mati
$query_kpi = "SELECT 
    COUNT(CASE WHEN p.status_perangkat = 'Aktif' THEN 1 END) AS cctv_aktif,
    COUNT(CASE WHEN p.status_perangkat != 'Aktif' THEN 1 END) AS cctv_mati
    FROM perangkat p
    INNER JOIN area_lokasi a ON p.id_area = a.id_area
    $where_sql";

$res_kpi = mysqli_query($conn, $query_kpi);
$kpi = ($res_kpi) ? mysqli_fetch_assoc($res_kpi) : [];

$cctv_aktif = (int)($kpi['cctv_aktif'] ?? 0);
$cctv_mati  = (int)($kpi['cctv_mati'] ?? 0);

// 4. Query Daftar Perangkat CCTV Mitra (ORDER BY memakai a.nama_area dan p.id_perangkat)
$query_cctv = "SELECT 
    p.*,
    a.nama_area,
    a.nama_lokasi,
    a.nama_gate
    FROM perangkat p
    INNER JOIN area_lokasi a ON p.id_area = a.id_area
    $where_sql
    ORDER BY a.nama_area ASC, p.id_perangkat ASC";

$res_cctv = mysqli_query($conn, $query_cctv);
$cctv_list = [];
if ($res_cctv) {
    while ($row = mysqli_fetch_assoc($res_cctv)) {
        $cctv_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring CCTV Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { max-width: 100%; width: 100%; }
        .filter-cctv-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: flex-end; }
        .filter-cctv-buttons { display: flex; gap: 10px; }
        .stats-grid-2col { display: grid; grid-template-columns: repeat(2, minmax(260px, 340px)); gap: 20px; margin-bottom: 24px; }
        .cctv-section-card { background: #ffffff; border-radius: 14px; padding: 24px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04); min-height: 360px; }
        .cctv-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .cctv-section-title { font-size: 20px; font-weight: 700; color: #004687; margin: 0; }
        .cctv-search-box { flex: 0 1 380px; width: 100%; }
        .cctv-search-box input { width: 100%; padding: 10px 16px; border: 1px solid #d0d7de; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; color: #333; }
        .cctv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .cctv-item-card { border-radius: 12px; overflow: hidden; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08); background: #0d1b2a; display: flex; flex-direction: column; transition: transform 0.2s ease; }
        .cctv-item-card:hover { transform: translateY(-2px); }
        .cctv-item-header { background-color: #004687; color: #ffffff; padding: 11px 14px; font-size: 12px; font-weight: 600; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .status-dot.online { background-color: #22c55e; box-shadow: 0 0 6px #22c55e; }
        .status-dot.offline { background-color: #ef4444; }
        .cctv-feed-wrapper { position: relative; width: 100%; height: 220px; background: #111827; overflow: hidden; }
        .cctv-feed-wrapper img, .cctv-feed-wrapper video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .cctv-offline-overlay { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.78); color: #f87171; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .btn-fullscreen { position: absolute; right: 10px; bottom: 10px; width: 30px; height: 30px; border-radius: 6px; border: none; background: rgba(0, 0, 0, 0.6); color: #ffffff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: background 0.2s; }
        .btn-fullscreen:hover { background: #004687; }
        .cctv-empty-state { text-align: center; padding: 60px 20px; color: #6b7280; font-size: 14px; }
        @media (max-width: 1024px) { .cctv-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .filter-cctv-row, .stats-grid-2col, .cctv-grid { grid-template-columns: 1fr; } }
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
            <div class="menu-group active">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-solid fa-desktop icon"></i> Monitoring
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="cctv.php" class="active-sub">CCTV</a>
                </div>
            </div>
            <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <p class="welcome-text">Monitoring CCTV</p>
            <h1 class="page-header-title">CCTV</h1>
        </header>

        <section class="filter-card">
            <form method="GET" action="cctv.php">
                <div class="filter-cctv-row">
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
                        <label>Lokasi</label>
                        <select name="lokasi">
                            <option value="">Semua Lokasi</option>
                            <?php if ($opt_lokasi): while ($l = mysqli_fetch_assoc($opt_lokasi)): ?>
                                <option value="<?= htmlspecialchars($l['nama_lokasi']); ?>" <?= ($f_lokasi === $l['nama_lokasi']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($l['nama_lokasi']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="filter-cctv-buttons">
                        <a href="cctv.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                        <button type="submit" class="btn-filter">Filter</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="stats-grid-2col">
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-video"></i></div>
                <div class="card-info">
                    <span class="card-label">CCTV Aktif</span>
                    <h2 class="card-value"><?= $cctv_aktif; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-video-slash"></i></div>
                <div class="card-info">
                    <span class="card-label">CCTV Mati</span>
                    <h2 class="card-value"><?= $cctv_mati; ?></h2>
                </div>
            </div>
        </section>

        <section class="cctv-section-card">
            <div class="cctv-toolbar">
                <h2 class="cctv-section-title">CCTV</h2>
                <div class="cctv-search-box">
                    <input type="text" id="searchCctv" placeholder="Search" onkeyup="filterCctvCards()">
                </div>
            </div>

            <?php if (count($cctv_list) > 0): ?>
                <div class="cctv-grid" id="cctvGrid">
                    <?php foreach ($cctv_list as $cam): 
                        $is_aktif = (strtolower($cam['status_perangkat'] ?? '') === 'aktif');
                        $nama_cam = $cam['nama_perangkat'] ?? ($cam['id_perangkat'] ?? 'CCTV');
                        $label_cam = trim(
                            ($cam['nama_area'] ?? 'Area') . ' - ' . 
                            ($cam['nama_lokasi'] ?? ($cam['nama_gate'] ?? 'Lokasi')) . ' - ' . 
                            $nama_cam
                        );
                        $stream_src = !empty($cam['stream_url']) ? $cam['stream_url'] : (!empty($cam['foto_cctv']) ? $cam['foto_cctv'] : '../img/cctv-placeholder.jpg');
                    ?>
                        <div class="cctv-item-card" data-search="<?= htmlspecialchars(strtolower($label_cam)); ?>">
                            <div class="cctv-item-header" title="<?= htmlspecialchars($label_cam); ?>">
                                <span class="status-dot <?= $is_aktif ? 'online' : 'offline'; ?>"></span>
                                <span><?= htmlspecialchars($label_cam); ?></span>
                            </div>
                            <div class="cctv-feed-wrapper" id="feed-<?= htmlspecialchars($cam['id_perangkat']); ?>">
                                <img src="<?= htmlspecialchars($stream_src); ?>" alt="<?= htmlspecialchars($label_cam); ?>" onerror="this.src='https://placehold.co/600x400/0f172a/94a3b8?text=Live+Feed+CCTV'">
                                
                                <?php if (!$is_aktif): ?>
                                    <div class="cctv-offline-overlay">
                                        <i class="fa-solid fa-video-slash fa-lg"></i>
                                        <span>Sinyal Terputus / Offline</span>
                                    </div>
                                <?php endif; ?>

                                <button type="button" class="btn-fullscreen" title="Layar Penuh" onclick="toggleFullScreen('feed-<?= htmlspecialchars($cam['id_perangkat']); ?>')">
                                    <i class="fa-solid fa-expand"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="cctv-empty-state" id="emptySearchMsg" style="display: none;">
                    Kamera CCTV tidak ditemukan.
                </div>
            <?php else: ?>
                <div class="cctv-empty-state">
                    Belum ada perangkat CCTV yang terdaftar untuk area mitra ini.
                </div>
            <?php endif; ?>
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

    function filterCctvCards() {
        const keyword = document.getElementById('searchCctv').value.toLowerCase();
        const cards = document.querySelectorAll('.cctv-item-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const text = card.getAttribute('data-search') || '';
            if (text.includes(keyword)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const emptyMsg = document.getElementById('emptySearchMsg');
        if (emptyMsg) {
            emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    function toggleFullScreen(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        if (!document.fullscreenElement) {
            el.requestFullscreen().catch(err => console.warn(err));
        } else {
            document.exitFullscreen();
        }
    }
</script>
</body>
</html>