<?php
session_start();
require_once '../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ambil Data Transaksi yang berelasi dengan Area & Lokasi dari Database
$query = "SELECT t.*, a.nama_area, a.nama_lokasi, a.nama_gate 
          FROM transaksi t 
          LEFT JOIN area_lokasi a ON t.id_area = a.id_area 
          ORDER BY t.waktu_transaksi DESC";
$result = mysqli_query($conn, $query);
$transaksi_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $transaksi_list[] = $row;
}

// Helper untuk menyingkat string QR panjang menjadi MPOS...0052
function formatShortQr($qrString) {
    if (strlen($qrString) > 12) {
        return substr($qrString, 0, 4) . '...' . substr($qrString, -4);
    }
    return $qrString;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Transaksi - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Library SheetJS untuk Export Excel (.xlsx) Asli -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<style>
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
.toolbar-right-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
/* Badge Penerbit & Jenis Tiket sesuai referensi */
.badge-pill-soft {
    background-color: #cbd5e1;
    color: #1e293b;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}
.badge-tipe-purple {
    background-color: #6366f1;
    color: #ffffff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}
/* String QR Code + Tombol Copy */
.qr-string-cell {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #3b82f6;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
}
.btn-copy-qr {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 13px;
    padding: 2px 4px;
    border-radius: 4px;
    transition: color 0.15s, background-color 0.15s;
}
.btn-copy-qr:hover {
    color: #004687;
    background-color: #e2e8f0;
}
/* Thumbnail Capture Foto CCTV */
.thumb-media-box {
    width: 46px;
    height: 46px;
    border-radius: 6px;
    border: 1px solid #d0d7de;
    padding: 2px;
    background: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
}
.thumb-media-box:hover {
    transform: scale(1.12);
    box-shadow: 0 4px 10px rgba(0, 75, 135, 0.2);
}
.thumb-media-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}
/* Modal Preview Foto CCTV */
.media-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.media-modal-box {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    max-width: 340px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.media-modal-box img {
    width: 220px;
    height: 220px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    margin: 12px 0;
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
<a href="dashboard.php" class="menu-item">
<i class="fa-solid fa-border-all icon"></i> Dashboard
</a>
<!-- Menu Dropdown: Laporan (Aktif) -->
<div class="menu-group active">
<div class="menu-header" onclick="toggleDropdown(this)">
<i class="fa-regular fa-file-lines icon"></i> Laporan
<i class="fa-solid fa-chevron-down arrow"></i>
</div>
<div class="submenu">
<a href="detailTransaksi.php">Detail Transaksi</a>
<a href="daftarTransaksi.php" class="sub-active">Daftar Transaksi</a>
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
<p class="welcome-text">Laporan</p>
<h1 class="page-header-title">Daftar Transaksi</h1>
</header>

<!-- Filter Card -->
<section class="filter-card">
<div class="filter-row align-center">
<div class="filter-group">
<label>Area</label>
<select id="filterArea">
<option value="">Pilih area</option>
<option value="TMII">TMII</option>
<option value="Ancol">Ancol</option>
<option value="Ragunan">Ragunan</option>
<option value="Monas">Monas</option>
<option value="Kota Tua">Kota Tua</option>
</select>
</div>
<div class="filter-group">
<label>Penerbit</label>
<select id="filterPenerbit">
<option value="">Pilih penerbit</option>
<option value="Bank BCA">Bank BCA</option>
<option value="GOERS">GOERS</option>
<option value="APPSKU">APPSKU</option>
<option value="Petugas">Petugas</option>
</select>
</div>
<div class="filter-group">
<label>Jenis Tiket</label>
<select id="filterJenis">
<option value="">Semua jenis tiket</option>
<option value="Tiket Masuk Utama">Tiket Masuk Utama</option>
<option value="Tiket Wahana">Tiket Wahana</option>
<option value="Tiket Masuk">Tiket Masuk</option>
<option value="Tiket Cawan">Tiket Cawan</option>
<option value="Tiket Museum">Tiket Museum</option>
</select>
</div>
<div class="filter-buttons">
<button type="button" class="btn-reset" onclick="resetFilter()">Reset</button>
<button type="button" class="btn-filter" onclick="applyFilter()">Filter</button>
</div>
</div>
</section>

<!-- Tabel Daftar Transaksi -->
<section class="table-section-card">
<div class="table-toolbar">
<div class="page-size-box">
<select id="rowsPerPage" onchange="changePageSize()">
<option value="5">5/page</option>
<option value="10" selected>10/page</option>
<option value="25">25/page</option>
</select>
</div>
<div class="toolbar-right-group">
<div class="search-box">
<input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search lokasi, gate, QR code...">
</div>
<button type="button" class="btn-excel-download" onclick="exportToExcel()">
<i class="fa-solid fa-file-excel"></i> Download Excel
</button>
</div>
</div>

<div class="table-responsive">
<table class="custom-table" id="transaksiTable">
<thead>
<tr>
<th style="width: 4%;">No</th>
<th>Lokasi</th>
<th>Gate</th>
<th>Waktu Transaksi</th>
<th>Penerbit</th>
<th>Tiket Kategori</th>
<th>Jenis Tiket</th>
<th>Tipe Tiket</th>
<th>QR Code</th>
<th>Foto</th>
<th>Nominal</th>
</tr>
</thead>
<tbody id="tableBody">
<?php if (count($transaksi_list) === 0): ?>
<tr><td colspan="11" style="text-align:center; padding:20px; color:#828282;">Belum ada data transaksi di database.</td></tr>
<?php else: ?>
<?php foreach ($transaksi_list as $index => $row): 
$fullQr = $row['kode_qr'] ?? '-';
$shortQr = formatShortQr($fullQr);
$waktuFormatted = date('d M Y H:i:s', strtotime($row['waktu_transaksi']));
?>
<tr data-area="<?= htmlspecialchars($row['nama_area'] ?? ''); ?>" 
    data-penerbit="<?= htmlspecialchars($row['penerbit']); ?>" 
    data-jenis="<?= htmlspecialchars($row['jenis_tiket']); ?>" 
    data-qrfull="<?= htmlspecialchars($fullQr); ?>" 
    data-nominal="<?= $row['nominal']; ?>"
    data-hasfoto="<?= !empty($row['foto_cctv']) ? 'Tercapture CCTV' : 'Tidak Ada Foto'; ?>">
<td><?= $index + 1; ?></td>
<td style="font-weight: 500;"><?= htmlspecialchars($row['nama_lokasi'] ?? '-'); ?></td>
<td><?= htmlspecialchars($row['nama_gate'] ?? '-'); ?></td>
<td style="white-space: nowrap;"><?= $waktuFormatted; ?></td>
<td><span class="badge-pill-soft"><?= htmlspecialchars(strtoupper($row['penerbit'])); ?></span></td>
<td><?= htmlspecialchars($row['tiket_kategori']); ?></td>
<td><span class="badge-pill-soft"><?= htmlspecialchars($row['jenis_tiket']); ?></span></td>
<td><span class="badge-tipe-purple"><?= htmlspecialchars($row['tipe_tiket']); ?></span></td>
<td>
<div class="qr-string-cell" title="<?= htmlspecialchars($fullQr); ?>">
<span><?= htmlspecialchars($shortQr); ?></span>
<button type="button" class="btn-copy-qr" onclick="copyQrString(this, '<?= htmlspecialchars($fullQr); ?>')" title="Salin kode QR lengkap">
<i class="fa-regular fa-copy"></i>
</button>
</div>
</td>
<td>
<?php if (!empty($row['foto_cctv'])): ?>
<div class="thumb-media-box" title="Capture Wajah CCTV" onclick="openMediaPreview('Capture CCTV - <?= htmlspecialchars($row['nama_gate'] ?? ''); ?>', '<?= htmlspecialchars($row['foto_cctv']); ?>')">
<img src="<?= htmlspecialchars($row['foto_cctv']); ?>" alt="Foto CCTV">
</div>
<?php else: ?>
<span style="color: #94a3b8; font-size:12px;">-</span>
<?php endif; ?>
</td>
<td style="font-weight: 600; white-space: nowrap;">Rp <?= number_format($row['nominal'], 0, ',', '.'); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination Controls -->
<div class="pagination-container" id="paginationControls">
<button class="page-btn" onclick="prevPage()"><i class="fa-solid fa-chevron-left"></i></button>
<span id="pageNumbers"></span>
<button class="page-btn" onclick="nextPage()"><i class="fa-solid fa-chevron-right"></i></button>
</div>
</section>
</main>
</div>

<!-- Modal Preview Capture Wajah CCTV -->
<div class="media-modal-overlay" id="mediaModal" onclick="closeMediaPreview()">
<div class="media-modal-box" onclick="event.stopPropagation()">
<h4 id="mediaModalTitle" style="color: #004687; font-size: 15px; margin-bottom: 4px;">Preview</h4>
<img id="mediaModalImg" src="" alt="Preview">
<div>
<button type="button" class="btn-filter" style="padding: 8px 20px; width: 100%;" onclick="closeMediaPreview()">Tutup</button>
</div>
</div>
</div>

<script>
function toggleDropdown(element) {
    element.parentElement.classList.toggle('active');
}

function copyQrString(btn, fullText) {
    navigator.clipboard.writeText(fullText).then(() => {
        const icon = btn.querySelector('i');
        icon.className = 'fa-solid fa-check';
        icon.style.color = '#1e7e34';
        setTimeout(() => {
            icon.className = 'fa-regular fa-copy';
            icon.style.color = '';
        }, 1500);
    });
}

function openMediaPreview(title, imgSrc) {
    document.getElementById('mediaModalTitle').innerText = title;
    document.getElementById('mediaModalImg').src = imgSrc;
    document.getElementById('mediaModal').style.display = 'flex';
}

function closeMediaPreview() {
    document.getElementById('mediaModal').style.display = 'none';
}

let currentPage = 1;
let rowsPerPage = 10;
const tbody = document.getElementById("tableBody");
const allRows = Array.from(tbody.getElementsByTagName("tr"));
let filteredRows = [...allRows];

function applyFilter() {
    const areaVal = document.getElementById("filterArea").value;
    const penerbitVal = document.getElementById("filterPenerbit").value;
    const jenisVal = document.getElementById("filterJenis").value;
    const searchVal = document.getElementById("searchInput").value.toLowerCase();

    filteredRows = allRows.filter(row => {
        if (row.getElementsByTagName("td").length <= 1) return true;
        const rowArea = row.getAttribute("data-area") || "";
        const rowPenerbit = row.getAttribute("data-penerbit") || "";
        const rowJenis = row.getAttribute("data-jenis") || "";
        const rowQrFull = (row.getAttribute("data-qrfull") || "").toLowerCase();
        const rowText = row.innerText.toLowerCase() + " " + rowQrFull;

        const matchArea = areaVal === "" || rowArea === areaVal;
        const matchPenerbit = penerbitVal === "" || rowPenerbit === penerbitVal;
        const matchJenis = jenisVal === "" || rowJenis === jenisVal;
        const matchSearch = rowText.includes(searchVal);

        return matchArea && matchPenerbit && matchJenis && matchSearch;
    });

    currentPage = 1;
    renderTable();
}

function resetFilter() {
    document.getElementById("filterArea").value = "";
    document.getElementById("filterPenerbit").value = "";
    document.getElementById("filterJenis").value = "";
    document.getElementById("searchInput").value = "";
    filteredRows = [...allRows];
    currentPage = 1;
    renderTable();
}

function changePageSize() {
    rowsPerPage = parseInt(document.getElementById("rowsPerPage").value);
    currentPage = 1;
    renderTable();
}

function renderTable() {
    allRows.forEach(row => row.style.display = "none");
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const pageRows = filteredRows.slice(startIndex, endIndex);

    pageRows.forEach((row, idx) => {
        row.style.display = "";
        if (row.getElementsByTagName("td").length > 1) {
            row.getElementsByTagName("td")[0].innerText = startIndex + idx + 1;
        }
    });
    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
    const pageNumbers = document.getElementById("pageNumbers");
    pageNumbers.innerHTML = "";
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.innerText = i;
        btn.className = "page-num" + (i === currentPage ? " active" : "");
        btn.onclick = () => {
            currentPage = i;
            renderTable();
        };
        pageNumbers.appendChild(btn);
    }
}

function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        renderTable();
    }
}

function nextPage() {
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderTable();
    }
}

// Export Excel (.xlsx) Asli Menggunakan SheetJS
function exportToExcel() {
    let totalNominal = 0;
    const excelData = [];

    // Header Kolom Excel
    excelData.push([
        "No",
        "Lokasi",
        "Gate",
        "Waktu Transaksi",
        "Penerbit",
        "Tiket Kategori",
        "Jenis Tiket",
        "Tipe Tiket",
        "QR Code (Lengkap)",
        "Status Capture CCTV",
        "Nominal (Rp)"
    ]);

    // Isi Data Berdasarkan Baris yang Sedang Terfilter
    let rowNumber = 1;
    filteredRows.forEach((row) => {
        const cells = row.getElementsByTagName("td");
        if (cells.length <= 1) return;

        const qrFull = row.getAttribute("data-qrfull") || "-";
        const statusFoto = row.getAttribute("data-hasfoto") || "-";
        const nominal = parseInt(row.getAttribute("data-nominal")) || 0;
        totalNominal += nominal;

        excelData.push([
            rowNumber++,
            cells[1].innerText.trim(),
            cells[2].innerText.trim(),
            cells[3].innerText.trim(),
            cells[4].innerText.trim(),
            cells[5].innerText.trim(),
            cells[6].innerText.trim(),
            cells[7].innerText.trim(),
            qrFull,
            statusFoto,
            nominal
        ]);
    });

    // Baris Total Keseluruhan di Bagian Bawah
    excelData.push([
        "", "", "", "", "", "", "", "", "", "TOTAL NOMINAL", totalNominal
    ]);

    // Buat Worksheet & Workbook Excel (.xlsx)
    const ws = XLSX.utils.aoa_to_sheet(excelData);

    // Atur Lebar Kolom Agar Rapi Saat Dibuka
    ws['!cols'] = [
        { wch: 5 },  // No
        { wch: 24 }, // Lokasi
        { wch: 15 }, // Gate
        { wch: 20 }, // Waktu Transaksi
        { wch: 14 }, // Penerbit
        { wch: 16 }, // Tiket Kategori
        { wch: 20 }, // Jenis Tiket
        { wch: 14 }, // Tipe Tiket
        { wch: 24 }, // QR Code
        { wch: 20 }, // Status Foto
        { wch: 16 }  // Nominal
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Daftar Transaksi");

    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Daftar_Transaksi_JMTO_${today}.xlsx`);
}

renderTable();
</script>
</body>
</html>