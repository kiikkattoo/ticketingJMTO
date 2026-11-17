<?php
session_start();
require_once dirname(__FILE__) . '/../koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ambil Opsi Area untuk Dropdown Filter
$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi ORDER BY nama_area ASC");

// Filter GET (Area & Rentang Tanggal Periode)
$f_area      = $_GET['area'] ?? '';
$tgl_mulai   = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

$where_clauses = ["t.status_transaksi = 'Berhasil'"];

if ($f_area !== '') {
    $val_area = mysqli_real_escape_string($conn, $f_area);
    $where_clauses[] = "a.nama_area = '$val_area'";
}

// Logika Filter Rentang Tanggal (Tanggal Awal s/d Tanggal Akhir)
if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $safe_mulai   = mysqli_real_escape_string($conn, $tgl_mulai);
    $safe_selesai = mysqli_real_escape_string($conn, $tgl_selesai);
    $where_clauses[] = "DATE(t.waktu_transaksi) BETWEEN '$safe_mulai' AND '$safe_selesai'";
} elseif (!empty($tgl_mulai)) {
    $safe_mulai = mysqli_real_escape_string($conn, $tgl_mulai);
    $where_clauses[] = "DATE(t.waktu_transaksi) >= '$safe_mulai'";
} elseif (!empty($tgl_selesai)) {
    $safe_selesai = mysqli_real_escape_string($conn, $tgl_selesai);
    $where_clauses[] = "DATE(t.waktu_transaksi) <= '$safe_selesai'";
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Query KPI Card 1 & Card 2 (Total Tiket & Total Pendapatan)
$sql_kpi = "SELECT COUNT(t.id_transaksi) AS total_tiket,
                   COALESCE(SUM(t.nominal), 0) AS total_pendapatan
            FROM transaksi t
            LEFT JOIN area_lokasi a ON t.id_area = a.id_area
            WHERE $where_sql";
$kpi = mysqli_fetch_assoc(mysqli_query($conn, $sql_kpi));

// 2. Query KPI Card 3 (Rincian per Penerbit)
$sql_penerbit = "SELECT t.penerbit, COUNT(t.id_transaksi) AS jml
                 FROM transaksi t
                 LEFT JOIN area_lokasi a ON t.id_area = a.id_area
                 WHERE $where_sql
                 GROUP BY t.penerbit
                 ORDER BY jml DESC";
$res_penerbit = mysqli_query($conn, $sql_penerbit);
$penerbit_list = [];
while ($sp = mysqli_fetch_assoc($res_penerbit)) {
    $penerbit_list[] = $sp;
}

// 3. Query Tabel Rekap Asli
$query_rekap = "SELECT
                    a.nama_area,
                    a.nama_lokasi,
                    a.nama_gate,
                    t.penerbit,
                    t.tiket_kategori,
                    t.jenis_tiket,
                    t.tipe_tiket,
                    COUNT(t.id_transaksi) AS jumlah_transaksi,
                    SUM(t.nominal) AS total_nominal
                FROM transaksi t
                LEFT JOIN area_lokasi a ON t.id_area = a.id_area
                WHERE $where_sql
                GROUP BY a.nama_area, a.nama_lokasi, a.nama_gate, t.penerbit, t.tiket_kategori, t.jenis_tiket, t.tipe_tiket
                ORDER BY a.nama_area ASC, a.nama_lokasi ASC";
$res_rekap = mysqli_query($conn, $query_rekap);
$rekap_list = [];
while ($row = mysqli_fetch_assoc($res_rekap)) {
    $rekap_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Transaksi - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Library SheetJS untuk Export Excel (.xlsx) Asli -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<style>
.rekap-blue-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 992px) {
    .rekap-blue-grid { grid-template-columns: 1fr; }
}
.rekap-blue-card {
    background-color: #004687;
    color: #ffffff;
    border-radius: 16px;
    padding: 24px 26px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 14px rgba(0, 75, 135, 0.16);
}
.rekap-blue-icon {
    font-size: 38px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
}
.rekap-blue-info { flex: 1; }
.rekap-blue-label {
    font-size: 12px;
    font-weight: 500;
    color: #cbd5e1;
    display: block;
    margin-bottom: 4px;
}
.rekap-blue-value {
    font-size: 26px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 6px 0;
    line-height: 1.2;
}
.rekap-blue-trend {
    font-size: 11px;
    color: #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.penerbit-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px 14px;
    margin-top: 8px;
}
.penerbit-mini-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.penerbit-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #ffffff;
    color: #004687;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    padding: 3px;
    box-sizing: border-box;
}
.penerbit-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.penerbit-mini-text span {
    display: block;
    font-size: 10px;
    color: #cbd5e1;
    line-height: 1.1;
}
.penerbit-mini-text strong {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
}
.btn-excel-download {
    background-color: #1e7e34;
    color: #ffffff;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
}
.btn-excel-download:hover { background-color: #166327; }
.toolbar-right-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.total-row { background-color: #f8fafc; font-weight: 700; color: #004687; }

/* ===== Date Range Picker (Periode) ===== */
.rp-wrap { position: relative; }
.rp-input {
    width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px;
    font-family: inherit; font-size: 13px; background: #fff; cursor: pointer;
    color: #334155; box-sizing: border-box;
}
.rp-input::placeholder { color: #94a3b8; }
.rp-popup {
    display: none; position: absolute; top: calc(100% + 8px); left: 0; z-index: 999;
    background: #fafafa; border-radius: 16px; padding: 22px 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); width: 340px;
}
.rp-popup.open { display: block; }
.rp-month { margin-bottom: 18px; }
.rp-month:last-child { margin-bottom: 0; }
.rp-month-head {
    display: flex; justify-content: space-between; align-items: center;
    font-weight: 600; font-size: 14px; color: #111; margin-bottom: 12px;
}
.rp-nav {
    background: none; border: none; cursor: pointer; font-size: 14px;
    color: #475569; padding: 2px 8px; border-radius: 6px;
}
.rp-nav:hover { background: #e2e8f0; }
.rp-nav.hidden { visibility: hidden; }
.rp-grid { display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 4px; }
.rp-dow { font-size: 10px; color: #b0b7c3; text-align: center; padding-bottom: 6px; }
.rp-day {
    height: 34px; display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: #465563; cursor: pointer; position: relative;
}
.rp-day.empty { cursor: default; }
.rp-day.in-range { background: #dbeafe; }
.rp-day.start { background: linear-gradient(to right, transparent 50%, #dbeafe 50%); }
.rp-day.end { background: linear-gradient(to left, transparent 50%, #dbeafe 50%); }
.rp-day.start.end { background: transparent; }
.rp-day span {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.rp-day:not(.empty):hover span { background: #e2e8f0; }
.rp-day.start span, .rp-day.end span { background: #0b6bdc !important; color: #fff; font-weight: 700; }
</style>
</head>
<body>
<div class="dashboard-wrapper">
<aside class="sidebar">
<div class="sidebar-logo"><img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img"></div>
<nav class="sidebar-menu">
<a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
<div class="menu-group active">
<div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
<div class="submenu">
<a href="detailTransaksi.php">Detail Transaksi</a>
<a href="daftarTransaksi.php">Daftar Transaksi</a>
<a href="rekapTransaksi.php" class="sub-active">Rekap Transaksi</a>
</div>
</div>
<div class="menu-group">
<div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-desktop icon"></i> Monitoring <i class="fa-solid fa-chevron-down arrow"></i></div>
<div class="submenu">
<a href="cctv.php">CCTV</a>
<a href="monitoringArea.php">Perangkat Area</a>
</div>
</div>
<div class="menu-group">
<div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-briefcase icon"></i> Manajemen <i class="fa-solid fa-chevron-down arrow"></i></div>
<div class="submenu">
<a href="daftarUser.php">User</a>
<a href="daftarRole.php">Role</a>
<a href="daftarMitra.php">Mitra</a>
<a href="daftarPerangkat.php">Perangkat</a>
<a href="daftarVenue.php">Venue</a>
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

<!-- Filter Card: Area (Kiri) & Periode (Kanan) + Tombol -->
<section class="filter-card" style="background: #fff; padding:20px; border-radius: 12px; margin-bottom:24px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
<form method="GET" action="">
<div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
<!-- Filter Area -->
<div style="flex: 1; min-width: 220px;">
<label style="display:block; font-size:13px; font-weight:500; margin-bottom: 6px; color: #334155;">Area</label>
<select name="area" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-family: inherit; font-size: 13px; background:#fff;">
<option value="">Semua Area</option>
<?php while ($a = mysqli_fetch_assoc($opt_area)): ?>
<option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= $f_area === $a['nama_area'] ? 'selected' : ''; ?>>
<?= htmlspecialchars($a['nama_area']); ?>
</option>
<?php endwhile; ?>
</select>
</div>

<!-- Filter Periode (Date Range Picker) -->
<div style="flex: 1; min-width: 280px;">
<label style="display:block; font-size:13px; font-weight:500; margin-bottom: 6px; color: #334155;">Periode</label>
<div class="rp-wrap" id="rpWrap">
<input type="text" id="rpDisplay" class="rp-input" placeholder="Pilih periode" readonly autocomplete="off">
<input type="hidden" name="tgl_mulai" id="rpStart" value="<?= htmlspecialchars($tgl_mulai); ?>">
<input type="hidden" name="tgl_selesai" id="rpEnd" value="<?= htmlspecialchars($tgl_selesai); ?>">
<div class="rp-popup" id="rpPopup">
<div class="rp-month" id="rpMonth1"></div>
<div class="rp-month" id="rpMonth2"></div>
</div>
</div>
</div>

<!-- Tombol Reset & Filter (Murni Teks Tanpa Ikon) -->
<div style="display: flex; gap: 10px;">
<a href="rekapTransaksi.php" style="padding: 10px 18px; background: #e2e8f0; color: #334155; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px; display:inline-flex; align-items:center;">Reset</a>
<button type="submit" style="padding: 10px 20px; background: #004687; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">Filter</button>
</div>
</div>
</form>
</section>

<!-- 3 Card Biru -->
<section class="rekap-blue-grid">
<!-- Card 1: Total Tiket -->
<div class="rekap-blue-card">
<div class="rekap-blue-icon">
<i class="fa-solid fa-ticket" style="transform: rotate(-35deg);"></i>
</div>
<div class="rekap-blue-info">
<span class="rekap-blue-label">Total Tiket</span>
<h2 class="rekap-blue-value"><?= number_format($kpi['total_tiket'], 0, ',', '.'); ?></h2>
<span class="rekap-blue-trend"><i class="fa-solid fa-caret-up"></i> Data real-time database</span>
</div>
</div>

<!-- Card 2: Total Pendapatan -->
<div class="rekap-blue-card">
<div class="rekap-blue-icon">
<i class="fa-solid fa-money-bill-trend-up"></i>
</div>
<div class="rekap-blue-info">
<span class="rekap-blue-label">Total Pendapatan</span>
<h2 class="rekap-blue-value">Rp <?= number_format($kpi['total_pendapatan'], 0, ',', '.'); ?></h2>
<span class="rekap-blue-trend"><i class="fa-solid fa-caret-up"></i> Data real-time database</span>
</div>
</div>

<!-- Card 3: Penerbit -->
<div class="rekap-blue-card">
<div class="rekap-blue-icon">
<i class="fa-solid fa-receipt"></i>
</div>
<div class="rekap-blue-info">
<span class="rekap-blue-label">Penerbit</span>
<div class="penerbit-mini-grid">
<?php if (count($penerbit_list) === 0): ?>
<div class="penerbit-mini-text"><strong>-</strong></div>
<?php else: ?>
<?php
// Pemetaan nama penerbit ke file logo di folder ../img/
$logo_map = [
    'bank dki' => '../img/logo-bankbca.png',
    'bank bca' => '../img/logo-bankbca.png',
    'appsku'   => '../img/logo-appsku.png',
    'goers'    => '../img/logo-goers.png',
];
?>
<?php foreach ($penerbit_list as $pen): ?>
<?php
$nama_pen = trim($pen['penerbit']);
$key_pen  = strtolower($nama_pen);
?>
<div class="penerbit-mini-item">
<div class="penerbit-avatar">
<?php if ($key_pen === 'petugas'): ?>
<i class="fa-solid fa-user-tie" style="font-size: 13px; color: #004687;"></i>
<?php elseif (isset($logo_map[$key_pen]) && file_exists($logo_map[$key_pen])): ?>
<img src="<?= $logo_map[$key_pen]; ?>" alt="<?= htmlspecialchars($nama_pen); ?>">
<?php else: ?>
<?= strtoupper(substr($nama_pen, 0, 2)); ?>
<?php endif; ?>
</div>
<div class="penerbit-mini-text">
<span><?= htmlspecialchars($nama_pen); ?></span>
<strong><?= number_format($pen['jml'], 0, ',', '.'); ?></strong>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>
</section>

<!-- Tabel Rekap Transaksi -->
<section class="table-section-card">
<div class="table-toolbar">
<div class="page-size-box">
<select id="rowsPerPage" onchange="changePageSize()">
<option value="10" selected>10/page</option>
<option value="25">25/page</option>
</select>
</div>
<div class="toolbar-right-group">
<div class="search-box">
<input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search lokasi, gate, penerbit...">
</div>
<button type="button" class="btn-excel-download" onclick="exportToExcel()">
<i class="fa-solid fa-file-excel"></i> Download Excel
</button>
</div>
</div>

<div class="table-responsive">
<table class="custom-table" id="rekapTable">
<thead>
<tr>
<th style="width: 4%;">No</th>
<th>Lokasi</th>
<th>Gate</th>
<th>Penerbit</th>
<th>Tiket Kategori</th>
<th>Jenis Tiket</th>
<th>Tipe Tiket</th>
<th>Jumlah</th>
<th>Nominal</th>
</tr>
</thead>
<tbody id="tableBody">
<?php if (count($rekap_list) === 0): ?>
<tr><td colspan="9" style="text-align:center; padding:20px; color:#828282;">Belum ada data transaksi di database.</td></tr>
<?php else: ?>
<?php foreach ($rekap_list as $index => $row): ?>
<tr data-area="<?= htmlspecialchars($row['nama_area'] ?? ''); ?>"
    data-penerbit="<?= htmlspecialchars($row['penerbit']); ?>"
    data-jumlah="<?= $row['jumlah_transaksi']; ?>"
    data-nominal="<?= $row['total_nominal']; ?>">
<td><?= $index + 1; ?></td>
<td style="font-weight: 500;"><?= htmlspecialchars($row['nama_lokasi'] ?? '-'); ?></td>
<td><?= htmlspecialchars($row['nama_gate'] ?? '-'); ?></td>
<td><?= htmlspecialchars($row['penerbit']); ?></td>
<td><?= htmlspecialchars($row['tiket_kategori']); ?></td>
<td><?= htmlspecialchars($row['jenis_tiket']); ?></td>
<td><?= htmlspecialchars($row['tipe_tiket']); ?></td>
<td style="font-weight: 600;"><?= number_format($row['jumlah_transaksi'], 0, ',', '.'); ?></td>
<td style="font-weight: 600;">Rp <?= number_format($row['total_nominal'], 0, ',', '.'); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
<tfoot>
<tr class="total-row">
<td colspan="7" style="text-align: right; padding-right: 16px;">TOTAL KESELURUHAN</td>
<td id="grandTotalJumlah">0</td>
<td id="grandTotalNominal">Rp 0</td>
</tr>
</tfoot>
</table>
</div>
</section>
</main>
</div>

<script>
function toggleDropdown(el) {
    el.parentElement.classList.toggle('active');
}

const allRows = Array.from(document.getElementById("tableBody").getElementsByTagName("tr"));
let filteredRows = [...allRows];

function formatNumber(n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function applyFilter() {
    const q = document.getElementById("searchInput").value.toLowerCase();
    filteredRows = allRows.filter(r => {
        if (r.getElementsByTagName("td").length <= 1) return false;
        const mQ = r.innerText.toLowerCase().includes(q);
        r.style.display = mQ ? "" : "none";
        return mQ;
    });
    updateTotals();
}

function updateTotals() {
    let sumJml = 0, sumNom = 0;
    filteredRows.forEach(r => {
        if (r.style.display !== "none" && r.getElementsByTagName("td").length > 1) {
            sumJml += parseInt(r.getAttribute("data-jumlah")) || 0;
            sumNom += parseInt(r.getAttribute("data-nominal")) || 0;
        }
    });
    document.getElementById("grandTotalJumlah").innerText = formatNumber(sumJml);
    document.getElementById("grandTotalNominal").innerText = "Rp " + formatNumber(sumNom);
}

function changePageSize() {}

// Export Excel (.xlsx) Asli Menggunakan SheetJS
function exportToExcel() {
    let sumJml = 0;
    let sumNom = 0;
    const excelData = [];

    // Header Kolom Excel
    excelData.push([
        "No",
        "Lokasi",
        "Gate",
        "Penerbit",
        "Tiket Kategori",
        "Jenis Tiket",
        "Tipe Tiket",
        "Jumlah Transaksi",
        "Total Nominal (Rp)"
    ]);

    // Isi Data Berdasarkan Baris yang Sedang Tampil
    let rowNumber = 1;
    filteredRows.forEach((r) => {
        const cells = r.getElementsByTagName("td");
        if (cells.length <= 1 || r.style.display === "none") return;

        const jml = parseInt(r.getAttribute("data-jumlah")) || 0;
        const nom = parseInt(r.getAttribute("data-nominal")) || 0;
        sumJml += jml;
        sumNom += nom;

        excelData.push([
            rowNumber++,
            cells[1].innerText.trim(),
            cells[2].innerText.trim(),
            cells[3].innerText.trim(),
            cells[4].innerText.trim(),
            cells[5].innerText.trim(),
            cells[6].innerText.trim(),
            jml,
            nom
        ]);
    });

    // Baris Total Keseluruhan di Bagian Bawah
    excelData.push([
        "", "", "", "", "", "",
        "TOTAL KESELURUHAN", sumJml, sumNom
    ]);

    const ws = XLSX.utils.aoa_to_sheet(excelData);
    ws['!cols'] = [
        { wch: 5 },  // No
        { wch: 24 }, // Lokasi
        { wch: 15 }, // Gate
        { wch: 14 }, // Penerbit
        { wch: 16 }, // Tiket Kategori
        { wch: 20 }, // Jenis Tiket
        { wch: 14 }, // Tipe Tiket
        { wch: 18 }, // Jumlah
        { wch: 20 }  // Nominal
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Rekap Transaksi");

    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Rekap_Transaksi_JMTO_${today}.xlsx`);
}

updateTotals();

/* ===== Date Range Picker (Periode) ===== */
(function () {
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const DOW = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

    const wrap = document.getElementById('rpWrap');
    const popup = document.getElementById('rpPopup');
    const display = document.getElementById('rpDisplay');
    const hidStart = document.getElementById('rpStart');
    const hidEnd = document.getElementById('rpEnd');

    function parseYMD(s) {
        if (!s) return null;
        const p = s.split('-');
        return new Date(+p[0], +p[1] - 1, +p[2]);
    }

    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function toDMY(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    let start = parseYMD(hidStart.value);
    let end = parseYMD(hidEnd.value);
    const base = start || new Date();
    let viewYear = base.getFullYear();
    let viewMonth = base.getMonth();

    function updateDisplay() {
        if (start && end) display.value = toDMY(start) + ' - ' + toDMY(end);
        else if (start) display.value = toDMY(start) + ' - ...';
        else if (end) display.value = '... - ' + toDMY(end);
        else display.value = '';
    }

    function buildMonth(container, year, month, showPrev, showNext) {
        const first = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        let html = '<div class="rp-month-head">';
        html += '<button type="button" class="rp-nav ' + (showPrev ? '' : 'hidden') + '" data-nav="-1">&#10094;</button>';
        html += '<span>' + MONTHS[month] + ' ' + year + '</span>';
        html += '<button type="button" class="rp-nav ' + (showNext ? '' : 'hidden') + '" data-nav="1">&#10095;</button>';
        html += '</div><div class="rp-grid">';

        DOW.forEach(d => html += '<div class="rp-dow">' + d + '</div>');
        for (let i = 0; i < first.getDay(); i++) html += '<div class="rp-day empty"></div>';

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month, d);
            let cls = 'rp-day';
            const t = date.getTime();
            const sT = start ? start.getTime() : null;
            const eT = end ? end.getTime() : null;

            if (sT !== null && t === sT) cls += ' start';
            if (eT !== null && t === eT) cls += ' end';
            if (sT !== null && eT !== null && t > sT && t < eT) cls += ' in-range';
            if (sT !== null && eT === null && t === sT) cls += ' end';

            html += '<div class="' + cls + '" data-date="' + toYMD(date) + '"><span>' + d + '</span></div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function render() {
        const nextMonth = new Date(viewYear, viewMonth + 1, 1);
        buildMonth(document.getElementById('rpMonth1'), viewYear, viewMonth, true, false);
        buildMonth(document.getElementById('rpMonth2'), nextMonth.getFullYear(), nextMonth.getMonth(), false, true);
    }

    function pickDate(date) {
        if (!start || (start && end)) {
            start = date;
            end = null;
        } else {
            if (date.getTime() < start.getTime()) {
                end = start;
                start = date;
            } else {
                end = date;
            }
            popup.classList.remove('open');
        }
        hidStart.value = start ? toYMD(start) : '';
        hidEnd.value = end ? toYMD(end) : '';
        updateDisplay();
        render();
    }

    display.addEventListener('click', function () {
        popup.classList.toggle('open');
    });

    popup.addEventListener('click', function (e) {
        const nav = e.target.closest('[data-nav]');
        if (nav) {
            const step = parseInt(nav.getAttribute('data-nav'));
            const d = new Date(viewYear, viewMonth + step, 1);
            viewYear = d.getFullYear();
            viewMonth = d.getMonth();
            render();
            return;
        }
        const cell = e.target.closest('.rp-day[data-date]');
        if (cell) pickDate(parseYMD(cell.getAttribute('data-date')));
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) popup.classList.remove('open');
    });

    updateDisplay();
    render();
})();
</script>
</body>
</html>