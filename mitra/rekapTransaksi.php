<?php 
session_start();
require '../koneksi.php';
include '../includes/load_tema_mitra.php'; 

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}
if (strtolower($_SESSION["nama_role"] ?? '') !== 'mitra') {
    header("Location: ../admin/rekapTransaksi.php");
    exit();
}

$id_mitra_login = mysqli_real_escape_string($conn, $_SESSION['id_mitra'] ?? '');

$f_area    = $_GET['area'] ?? '';
$f_metode  = $_GET['metode'] ?? '';
$f_periode = $_GET['periode'] ?? '';

$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi WHERE kode_mitra = '$id_mitra_login' ORDER BY nama_area ASC");

$where = [
    "a.kode_mitra = '$id_mitra_login'",
    "t.status_transaksi = 'Berhasil'"
];

if (!empty($f_area)) {
    $safe_area = mysqli_real_escape_string($conn, $f_area);
    $where[] = "a.nama_area = '$safe_area'";
}
if (!empty($f_metode)) {
    $safe_metode = mysqli_real_escape_string($conn, $f_metode);
    $where[] = "t.penerbit = '$safe_metode'";
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
    COUNT(t.id_transaksi) AS total_tiket,
    COALESCE(SUM(t.nominal), 0) AS total_pendapatan,
    COUNT(DISTINCT DATE(t.waktu_transaksi)) AS jml_hari
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $where_sql";

$res_kpi = mysqli_query($conn, $query_kpi);
$kpi = ($res_kpi) ? mysqli_fetch_assoc($res_kpi) : [];

$total_tiket      = (int)($kpi['total_tiket'] ?? 0);
$total_pendapatan = (float)($kpi['total_pendapatan'] ?? 0);
$jml_hari         = max(1, (int)($kpi['jml_hari'] ?? 1));
$rata_pendapatan  = ($total_tiket > 0) ? ($total_pendapatan / $jml_hari) : 0;

$query_rekap = "SELECT 
    DATE(t.waktu_transaksi) AS tanggal_trx,
    a.nama_area,
    a.nama_gate,
    t.penerbit,
    COUNT(t.id_transaksi) AS jml_tiket,
    COALESCE(SUM(t.nominal), 0) AS total_nominal
    FROM transaksi t
    INNER JOIN area_lokasi a ON t.id_area = a.id_area
    $where_sql
    GROUP BY DATE(t.waktu_transaksi), a.nama_area, a.nama_gate, t.penerbit
    ORDER BY tanggal_trx DESC, a.nama_area ASC";

$res_rekap = mysqli_query($conn, $query_rekap);
$rekap_list = [];
if ($res_rekap) {
    while ($row = mysqli_fetch_assoc($res_rekap)) {
        $rekap_list[] = $row;
    }
}

$channels = ['Bank DKI', 'GOERS', 'APPSKU', 'Petugas'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Transaksi Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== LAYOUT UMUM ===== */
        .main-content { max-width: 100%; width: 100%; }

        /* ===== KARTU FILTER =====
           Baris 1: Area | Metode Pembayaran | Periode
           Baris 2: Reset + Filter (rata kanan, sejajar kolom 3) */
        .filter-card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 16px;
            padding: 26px 28px;
            margin-bottom: 26px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .filter-card form {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px 22px;
            align-items: end;
        }
        .filter-rekap-top { display: contents; }
        .filter-rekap-top > .filter-group:nth-child(1) { grid-column: 1; grid-row: 1; }
        .filter-rekap-top > .filter-group:nth-child(2) { grid-column: 2; grid-row: 1; }
        .filter-rekap-bottom { grid-column: 3; grid-row: 1; margin-top: 0; max-width: none; }
        .filter-rekap-buttons { grid-column: 3; grid-row: 2; display: flex; justify-content: flex-end; gap: 12px; }

        .filter-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
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
            border-color: var(--mitra-primer, #004687);
            box-shadow: 0 0 0 3px rgba(0, 70, 135, 0.10);
        }
        .filter-rekap-buttons .btn-reset,
        .filter-rekap-buttons .btn-filter {
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
        .filter-rekap-buttons .btn-reset { background: #ffffff; border: 1.5px solid var(--mitra-primer, #004687); color: #0f172a; }
        .filter-rekap-buttons .btn-reset:hover { background: #f1f5f9; }
        .filter-rekap-buttons .btn-filter { background: var(--mitra-primer, #004687); border: 1.5px solid var(--mitra-primer, #004687); color: #ffffff; }
        .filter-rekap-buttons .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,0.2); }

        /* ===== KARTU STATISTIK (3 kolom) ===== */
        .stats-grid-3col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 26px; }
        .stats-grid-3col .stat-card {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 18px;
            min-height: 124px;
            padding: 20px 26px;
            background: var(--mitra-primer, #004687);
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
            font-size: clamp(18px, 1.7vw, 27px);
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            line-height: 1.15;
            white-space: nowrap;
        }
        .stats-grid-3col .stat-card .card-trend { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.88); }

        /* ===== KARTU TABEL ===== */
        .table-card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 18px;
            padding: 26px 28px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
            margin-top: 20px;
        }
        .table-section-title { font-size: 18px; font-weight: 700; color: var(--mitra-primer, #004687); margin: 0 0 20px; }
        .table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
        .page-size-select {
            height: 40px;
            padding: 0 14px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
            cursor: pointer;
            outline: none;
        }
        .search-box-wrapper { flex: 1; max-width: 460px; }
        .search-box-wrapper input {
            width: 100%;
            height: 40px;
            box-sizing: border-box;
            padding: 0 14px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            background: #f8fafc;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .search-box-wrapper input:focus {
            background: #ffffff;
            border-color: var(--mitra-primer, #004687);
            box-shadow: 0 0 0 3px rgba(0, 70, 135, 0.10);
        }
        .btn-csv {
            height: 40px;
            background-color: #15803d;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 0 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .btn-csv:hover { background-color: #166534; transform: translateY(-1px); box-shadow: 0 6px 14px rgba(21,128,61,0.28); }

        /* Tabel */
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid #dfe6ee; border-radius: 12px; }
        #rekapTable { width: 100%; border-collapse: collapse; font-size: 13px; text-align: center; }
        #rekapTable thead th {
            background-color: var(--mitra-primer, #004687);
            color: #ffffff;
            font-weight: 600;
            padding: 14px 12px;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.14);
        }
        #rekapTable thead th:last-child { border-right: none; }
        #rekapTable tbody td { padding: 13px 12px; border: 1px solid #e9eef4; color: #334155; }
        #rekapTable tbody tr td:first-child { border-left: none; }
        #rekapTable tbody tr td:last-child { border-right: none; }
        #rekapTable tbody tr:last-child td { border-bottom: none; }
        #rekapTable tbody tr:nth-child(even) { background-color: #f8fafc; }
        #rekapTable tbody tr { transition: background .15s; }
        #rekapTable tbody tr:hover { background-color: #eef3fa; }

        /* Pagination */
        .pagination-bar { display: flex; justify-content: flex-end; align-items: center; gap: 6px; margin-top: 18px; }
        .pagination-bar button {
            min-width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--mitra-primer, #004687);
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            padding: 0 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background .15s;
        }
        .pagination-bar button:hover:not(:disabled):not(.active-page) { background-color: #eef3fa; }
        .pagination-bar button.active-page { background-color: var(--mitra-primer, #004687); color: #ffffff; }
        .pagination-bar button:disabled { color: #a0aec0; cursor: not-allowed; }

        /* ===== RESPONSIF ===== */
        @media (max-width: 1200px) {
            .stats-grid-3col { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .filter-card form { grid-template-columns: 1fr; }
            .filter-rekap-top > .filter-group:nth-child(1),
            .filter-rekap-top > .filter-group:nth-child(2),
            .filter-rekap-bottom,
            .filter-rekap-buttons { grid-column: 1; grid-row: auto; }
            .filter-rekap-buttons { justify-content: stretch; }
            .filter-rekap-buttons .btn-reset, .filter-rekap-buttons .btn-filter { flex: 1; }
            .stats-grid-3col { grid-template-columns: 1fr; }
            .table-card { padding: 20px 16px; }
            .search-box-wrapper { max-width: 100%; flex: 1 1 100%; order: 3; }
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
                    <a href="analisisTransaksi.php">Analisis Transaksi</a>
                    <a href="daftarTransaksi.php">Daftar Transaksi</a>
                    <a href="rekapTransaksi.php" class="active-sub">Rekap Transaksi</a>
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
            <h1 class="page-header-title">Rekap Transaksi</h1>
        </header>

        <section class="filter-card">
            <form method="GET" action="rekapTransaksi.php">
                <div class="filter-rekap-top">
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
                    <div class="filter-rekap-buttons">
                        <a href="rekapTransaksi.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                        <button type="submit" class="btn-filter">Filter</button>
                    </div>
                </div>
                <div class="filter-rekap-bottom">
                    <div class="filter-group">
                        <label>Periode</label>
                        <select name="periode">
                            <option value="">Semua Periode</option>
                            <option value="Hari Ini" <?= ($f_periode === 'Hari Ini') ? 'selected' : ''; ?>>Hari Ini</option>
                            <option value="Minggu Ini" <?= ($f_periode === 'Minggu Ini') ? 'selected' : ''; ?>>Minggu Ini</option>
                            <option value="Bulan Ini" <?= ($f_periode === 'Bulan Ini') ? 'selected' : ''; ?>>Bulan Ini</option>
                        </select>
                    </div>
                </div>
            </form>
        </section>

        <section class="stats-grid-3col">
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-ticket"></i></div>
                <div class="card-info">
                    <span class="card-label">Total Tiket</span>
                    <h2 class="card-value"><?= number_format($total_tiket, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-check-circle"></i> Transaksi berhasil</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <div class="card-info">
                    <span class="card-label">Total Pendapatan</span>
                    <h2 class="card-value">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-database"></i> Akumulasi bersih</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="card-icon"><i class="fa-solid fa-torii-gate"></i></div>
                <div class="card-info">
                    <span class="card-label">Rata-rata Pendapatan (Per hari)</span>
                    <h2 class="card-value">Rp <?= number_format($rata_pendapatan, 0, ',', '.'); ?></h2>
                    <span class="card-trend"><i class="fa-solid fa-calendar-day"></i> Rata-rata <?= $jml_hari; ?> hari aktif</span>
                </div>
            </div>
        </section>

        <section class="table-card">
            <h2 class="table-section-title">Rekap Transaksi (Per Mitra)</h2>

            <div class="table-toolbar">
                <select id="rowsPerPage" class="page-size-select" onchange="changePageSize()">
                    <option value="10">10/page</option>
                    <option value="25">25/page</option>
                    <option value="50">50/page</option>
                    <option value="100">100/page</option>
                </select>
                <div class="search-box-wrapper">
                    <input type="text" id="searchInput" placeholder="Search" onkeyup="filterTable()">
                </div>
                <button type="button" class="btn-csv" onclick="exportTableToCSV('rekap_transaksi_mitra.csv')">
                    <i class="fa-solid fa-file-excel"></i> Download CSV
                </button>
            </div>

            <div class="table-responsive">
                <table id="rekapTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Area & Gate</th>
                            <th>Metode Pembayaran</th>
                            <th>Total Tiket</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (count($rekap_list) > 0): ?>
                            <?php foreach ($rekap_list as $idx => $rkp): 
                                $tgl_fmt = !empty($rkp['tanggal_trx']) ? date('d/m/Y', strtotime($rkp['tanggal_trx'])) : '-';
                                $area_gate = trim(($rkp['nama_area'] ?? '-') . (!empty($rkp['nama_gate']) ? ' - ' . $rkp['nama_gate'] : ''));
                            ?>
                                <tr class="data-row">
                                    <td><?= $idx + 1; ?></td>
                                    <td><?= htmlspecialchars($tgl_fmt); ?></td>
                                    <td><?= htmlspecialchars($area_gate); ?></td>
                                    <td><?= htmlspecialchars($rkp['penerbit'] ?? '-'); ?></td>
                                    <td><?= number_format((int)($rkp['jml_tiket'] ?? 0), 0, ',', '.'); ?></td>
                                    <td>Rp <?= number_format((float)($rkp['total_nominal'] ?? 0), 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 24px; color: #777;">Belum ada data rekap transaksi untuk mitra ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar" id="paginationControls"></div>
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

    let currentPage = 1;
    let rowsPerPage = 10;

    function getFilteredRows() {
        const keyword = document.getElementById('searchInput').value.toLowerCase();
        const allRows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        return allRows.filter(row => row.innerText.toLowerCase().includes(keyword));
    }

    function renderTable() {
        const allRows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        if (allRows.length === 0) return;

        const filtered = getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(filtered.length / rowsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;

        allRows.forEach(r => r.style.display = 'none');

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filtered.slice(start, end).forEach((row, idx) => {
            row.style.display = '';
            row.cells[0].innerText = start + idx + 1;
        });

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('paginationControls');
        container.innerHTML = '';

        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&lt;';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => { currentPage--; renderTable(); };
        container.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            if (i === currentPage) btn.classList.add('active-page');
            btn.onclick = () => { currentPage = i; renderTable(); };
            container.appendChild(btn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '&gt;';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => { currentPage++; renderTable(); };
        container.appendChild(nextBtn);
    }

    function filterTable() { currentPage = 1; renderTable(); }
    function changePageSize() {
        rowsPerPage = parseInt(document.getElementById('rowsPerPage').value, 10);
        currentPage = 1;
        renderTable();
    }

    function exportTableToCSV(filename) {
        const rows = document.querySelectorAll('#rekapTable tr');
        let csv = [];
        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const cols = row.querySelectorAll('td, th');
            let rowData = [];
            cols.forEach(col => {
                let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(csvFile);
        link.download = filename;
        link.click();
    }

    document.addEventListener('DOMContentLoaded', renderTable);
</script>
</body>
</html>