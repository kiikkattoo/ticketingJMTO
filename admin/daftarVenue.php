<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once '../koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Proses Hapus Konfigurasi Venue
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $res_cek = mysqli_query($conn, "SELECT kode_mitra FROM tema_venue WHERE id_tema = $id_hapus LIMIT 1");
    if ($res_cek && $row_cek = mysqli_fetch_assoc($res_cek)) {
        $kode_hapus = mysqli_real_escape_string($conn, $row_cek['kode_mitra']);
        mysqli_query($conn, "DELETE FROM jenis_tiket_venue WHERE kode_mitra = '$kode_hapus'");
        mysqli_query($conn, "DELETE FROM tema_venue WHERE id_tema = $id_hapus");
    }
    header("Location: daftarVenue.php?status=deleted");
    exit();
}

// Ambil daftar mitra untuk dropdown filter
$mitra_options = [];
$res_m = mysqli_query($conn, "SELECT kode_mitra, nama_mitra FROM mitra ORDER BY nama_mitra ASC");
if ($res_m) {
    while ($m = mysqli_fetch_assoc($res_m)) {
        $mitra_options[] = $m;
    }
}

// Parameter Filter
$filter_mitra  = trim($_GET['mitra'] ?? '');
$filter_modul  = trim($_GET['modul'] ?? '');

$where_clauses = ["1=1"];
if ($filter_mitra !== '') {
    $fm_esc = mysqli_real_escape_string($conn, $filter_mitra);
    $where_clauses[] = "tv.kode_mitra = '$fm_esc'";
}
if ($filter_modul === 'revenue_on') {
    $where_clauses[] = "tv.show_revenue = 1";
} elseif ($filter_modul === 'revenue_off') {
    $where_clauses[] = "tv.show_revenue = 0";
} elseif ($filter_modul === 'multi_on') {
    $where_clauses[] = "tv.multi_tiket = 1";
} elseif ($filter_modul === 'multi_off') {
    $where_clauses[] = "tv.multi_tiket = 0";
}

$where_sql = implode(' AND ', $where_clauses);

// Query utama daftar konfigurasi venue + jumlah jenis tiket
$sql_venue = "SELECT tv.*, m.nama_mitra, m.logo_mitra,
                     (SELECT COUNT(*) FROM jenis_tiket_venue jtv WHERE jtv.kode_mitra = tv.kode_mitra) AS total_tiket
              FROM tema_venue tv
              LEFT JOIN mitra m ON tv.kode_mitra = m.kode_mitra
              WHERE $where_sql
              ORDER BY tv.id_tema ASC";
$res_venue = mysqli_query($conn, $sql_venue);
$venue_list = [];
if ($res_venue) {
    while ($row = mysqli_fetch_assoc($res_venue)) {
        $venue_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Venue - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        .btn-tambah-venue {
            background: #004687;
            color: #ffffff;
            text-decoration: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-tambah-venue:hover { background: #003366; }

        /* Kartu Filter */
        .filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }
        .filter-inputs {
            display: flex;
            gap: 20px;
            flex: 1;
            min-width: 320px;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 210px;
        }
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 6px;
        }
        .filter-group select {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .btn-reset {
            height: 40px;
            padding: 0 18px;
            border: 1px solid #004687;
            background: #ffffff;
            color: #004687;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-filter {
            height: 40px;
            padding: 0 22px;
            border: none;
            background: #004687;
            color: #ffffff;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Kartu Tabel */
        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px 24px;
        }
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 14px;
            flex-wrap: wrap;
        }
        .page-size-select {
            height: 40px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #1e293b;
        }
        .search-box {
            width: 320px;
            max-width: 100%;
            height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
        }

        .venue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .venue-table thead th {
            background: #004687;
            color: #ffffff;
            text-align: left;
            padding: 13px 16px;
            font-weight: 600;
            border: 1px solid #004687;
        }
        .venue-table tbody td {
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #1e293b;
        }
        .venue-table tbody tr:hover { background: #f8fafc; }

        /* Visual & Badge Konfigurasi Tampilan */
        .config-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .venue-thumb {
            height: 26px;
            max-width: 85px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 2px 6px;
            background: #ffffff;
        }
        .color-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 5px;
            color: #334155;
        }
        .color-dot {
            width: 11px;
            height: 11px;
            border-radius: 3px;
            display: inline-block;
            border: 1px solid rgba(0,0,0,0.12);
        }
        .config-bottom {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #334155;
        }
        .mod-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        .mod-on { color: #0f766e; }
        .mod-off { color: #64748b; }

        /* Tombol Aksi */
        .aksi-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-icon-edit, .btn-icon-del {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 13px;
            transition: opacity 0.2s;
        }
        .btn-icon-edit {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }
        .btn-icon-del {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .btn-icon-edit:hover, .btn-icon-del:hover { opacity: 0.85; }

        /* Pagination bawah kanan */
        .table-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
        }
        .page-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #334155;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .page-btn.active {
            background: #004687;
            color: #ffffff;
            border-color: #004687;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
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
            <div class="menu-group active">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-solid fa-briefcase icon"></i> Manajemen
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="daftarUser.php">User</a>
                    <a href="daftarRole.php">Role</a>
                    <a href="daftarMitra.php">Mitra</a>
                    <a href="daftarPerangkat.php">Perangkat</a>
                    <a href="daftarVenue.php" class="active">Venue</a>
                </div>
            </div>
            <a href="../logout.php" class="menu-item logout">
                <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
            </a>
        </nav>
    </aside>

    <!-- Konten Utama -->
    <main class="main-content">
        <div class="page-top-bar">
            <header class="top-header" style="margin-bottom:0;">
                <p class="welcome-text">Manajemen</p>
                <h1 class="page-header-title">Venue</h1>
            </header>
            <a href="tambahVenue.php" class="btn-tambah-venue">
                <i class="fa-solid fa-plus"></i> Tambah Venue
            </a>
        </div>

        <!-- Kartu Filter -->
        <form method="GET" class="filter-card">
            <div class="filter-inputs">
                <div class="filter-group">
                    <label for="filterMitra">Mitra / Venue</label>
                    <select name="mitra" id="filterMitra">
                        <option value="">Semua mitra</option>
                        <?php foreach ($mitra_options as $m): ?>
                            <option value="<?= htmlspecialchars($m['kode_mitra']); ?>" <?= ($filter_mitra === $m['kode_mitra']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($m['nama_mitra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterModul">Konfigurasi Modul</label>
                    <select name="modul" id="filterModul">
                        <option value="">Pilih konfigurasi</option>
                        <option value="revenue_on" <?= ($filter_modul === 'revenue_on') ? 'selected' : ''; ?>>Tampil Pendapatan (Revenue-Share)</option>
                        <option value="revenue_off" <?= ($filter_modul === 'revenue_off') ? 'selected' : ''; ?>>Tanpa Pendapatan (Volume-Only)</option>
                        <option value="multi_on" <?= ($filter_modul === 'multi_on') ? 'selected' : ''; ?>>Multi-Jenis Tiket</option>
                        <option value="multi_off" <?= ($filter_modul === 'multi_off') ? 'selected' : ''; ?>>Tiket Tunggal</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <a href="daftarVenue.php" class="btn-reset">Reset</a>
                <button type="submit" class="btn-filter">Filter</button>
            </div>
        </form>

        <!-- Kartu Tabel -->
        <div class="table-card">
            <div class="table-controls">
                <select id="pageSizeSelect" class="page-size-select" onchange="renderTable()">
                    <option value="10">10/page</option>
                    <option value="25">25/page</option>
                    <option value="50">50/page</option>
                </select>
                <input type="text" id="searchInput" class="search-box" placeholder="Search" onkeyup="renderTable()">
            </div>

            <div style="overflow-x:auto;">
                <table class="venue-table" id="venueTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No.</th>
                            <th style="width: 220px;">Mitra Venue</th>
                            <th>Konfigurasi Tampilan</th>
                            <th style="width: 130px; text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="venueTbody">
                        <?php if (empty($venue_list)): ?>
                            <tr class="empty-row">
                                <td colspan="4" style="text-align:center; padding:28px; color:#64748b;">
                                    Belum ada konfigurasi tema venue. Klik <b>+ Tambah Venue</b> untuk menambahkan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($venue_list as $idx => $v):
                                $logo_src = !empty($v['logo_portal']) ? '../' . $v['logo_portal'] : (!empty($v['logo_mitra']) ? '../' . $v['logo_mitra'] : '../img/jasamarga-logo.png');
                                $nama_tampil = !empty($v['nama_mitra']) ? $v['nama_mitra'] : $v['kode_mitra'];
                                $jml_tiket = (int)$v['total_tiket'] > 0 ? (int)$v['total_tiket'] : 1;
                            ?>
                            <tr class="data-row">
                                <td class="row-no"><?= $idx + 1; ?>.</td>
                                <td>
                                    <strong style="display:block; color:#0f172a;"><?= htmlspecialchars($nama_tampil); ?></strong>
                                    <small style="color:#64748b; font-size:11px;"><?= htmlspecialchars($v['nama_tema']); ?></small>
                                </td>
                                <td>
                                    <div class="config-top">
                                        <img src="<?= htmlspecialchars($logo_src); ?>" alt="Logo" class="venue-thumb">
                                        <span class="color-pill">
                                            <i class="color-dot" style="background:<?= htmlspecialchars($v['warna_primer']); ?>;"></i>
                                            <?= htmlspecialchars(strtoupper($v['warna_primer'])); ?>
                                        </span>
                                        <span class="color-pill">
                                            <i class="color-dot" style="background:<?= htmlspecialchars($v['warna_aksen']); ?>;"></i>
                                            <?= htmlspecialchars(strtoupper($v['warna_aksen'])); ?>
                                        </span>
                                    </div>
                                    <div class="config-bottom">
                                        <?php if ((int)$v['show_revenue'] === 1): ?>
                                            <span class="mod-badge mod-on"><i class="fa-solid fa-check"></i> Pendapatan</span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-off"><i class="fa-solid fa-xmark"></i> Tanpa Pendapatan</span>
                                        <?php endif; ?>

                                        <?php if ((int)$v['multi_tiket'] === 1): ?>
                                            <span class="mod-badge mod-on"><i class="fa-solid fa-check"></i> Multi-Tiket (<?= $jml_tiket; ?>)</span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-off">&bull; Tiket Tunggal</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="aksi-wrap">
                                        <a href="editVenue.php?id=<?= (int)$v['id_tema']; ?>" class="btn-icon-edit" title="Edit Tema Venue">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </a>
                                        <a href="daftarVenue.php?hapus=<?= (int)$v['id_tema']; ?>" class="btn-icon-del" title="Hapus Konfigurasi"
                                           onclick="return confirm('Hapus konfigurasi tema untuk <?= htmlspecialchars(addslashes($nama_tampil)); ?>?');">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer" id="paginationControls"></div>
        </div>
    </main>
</div>

<script>
function toggleDropdown(el) {
    el.parentElement.classList.toggle('active');
}

let currentPage = 1;
const allRows = Array.from(document.querySelectorAll('#venueTbody .data-row'));

function renderTable() {
    if (allRows.length === 0) return;

    const keyword  = document.getElementById('searchInput').value.toLowerCase();
    const pageSize = parseInt(document.getElementById('pageSizeSelect').value, 10);

    const filtered = allRows.filter(row => row.innerText.toLowerCase().includes(keyword));
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    if (currentPage > totalPages) currentPage = 1;

    allRows.forEach(r => r.style.display = 'none');

    const start = (currentPage - 1) * pageSize;
    const end   = start + pageSize;
    filtered.slice(start, end).forEach((row, i) => {
        row.style.display = '';
        row.querySelector('.row-no').innerText = (start + i + 1) + '.';
    });

    // Render tombol pagination
    const pagEl = document.getElementById('paginationControls');
    let html = `<button type="button" class="page-btn" onclick="goPage(${Math.max(1, currentPage - 1)})">&lt;</button>`;
    for (let p = 1; p <= totalPages; p++) {
        html += `<button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
    }
    html += `<button type="button" class="page-btn" onclick="goPage(${Math.min(totalPages, currentPage + 1)})">&gt;</button>`;
    pagEl.innerHTML = html;
}

function goPage(p) {
    currentPage = p;
    renderTable();
}

renderTable();
</script>
</body>
</html>