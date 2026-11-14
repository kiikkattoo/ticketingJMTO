<?php
session_start();
require_once '../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ambil Data Role Asli dari Database Beserta Jumlah User yang Menggunakan Role Tersebut
$query_role = "SELECT r.id_role, r.nama_role AS role, COUNT(u.id) AS jumlah_user 
               FROM roles r 
               LEFT JOIN users u ON r.id_role = u.id_role 
               GROUP BY r.id_role, r.nama_role 
               ORDER BY r.id_role ASC";
$res_role = mysqli_query($conn, $query_role);
$role_list = [];
while ($row = mysqli_fetch_assoc($res_role)) {
    $role_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Role - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Icon Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Styling Tombol Aksi Icon-Only Sesuai Referensi */
.action-icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}
.action-edit {
    background-color: #eef5fc;
    color: #004687;
    border: 1px solid #dbeafe;
}
.action-edit:hover {
    background-color: #dbeafe;
}
.action-delete {
    background-color: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.action-delete:hover {
    background-color: #fecaca;
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
<!-- Menu Dropdown: Manajemen (Aktif & Terbuka) -->
<div class="menu-group active">
<div class="menu-header" onclick="toggleDropdown(this)">
<i class="fa-solid fa-briefcase icon"></i> Manajemen
<i class="fa-solid fa-chevron-down arrow"></i>
</div>
<div class="submenu">
<a href="daftarUser.php">User</a>
<a href="daftarRole.php" class="sub-active">Role</a>
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
<header class="top-header-with-btn" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
<div>
<p class="welcome-text">Manajemen</p>
<h1 class="page-header-title">Daftar Role</h1>
</div>
<a href="tambahRole.php" class="btn-add-primary" style="text-decoration: none; background:#004687; color:#fff; padding:10px 18px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
<i class="fa-solid fa-plus" style="position:static !important; margin:0;"></i> Tambah Role
</a>
</header>

<!-- Filter Card -->
<section class="filter-card">
<div class="filter-row align-center">
<div class="filter-group flex-2">
<label>Role</label>
<select id="filterRole">
<option value="">Pilih kategori role</option>
<?php foreach ($role_list as $r): ?>
<option value="<?= htmlspecialchars($r['role']); ?>"><?= htmlspecialchars($r['role']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="filter-buttons">
<button type="button" class="btn-reset" onclick="resetFilter()">Reset</button>
<button type="button" class="btn-filter" onclick="applyFilter()">Filter</button>
</div>
</div>
</section>

<!-- Section Tabel Daftar Role -->
<section class="table-section-card">
<div class="table-toolbar">
<div class="page-size-box">
<select id="rowsPerPage" onchange="changePageSize()">
<option value="5">5/page</option>
<option value="10" selected>10/page</option>
<option value="25">25/page</option>
</select>
</div>
</div>

<!-- Tabel Data Role -->
<div class="table-responsive">
<table class="custom-table" id="roleTable">
<thead>
<tr>
<th style="width: 10%;">No</th>
<th style="width: 35%;">Role</th>
<th style="width: 35%;">Jumlah User</th>
<th style="width: 20%;">Aksi</th>
</tr>
</thead>
<tbody id="tableBody">
<?php if (count($role_list) === 0): ?>
<tr><td colspan="4" style="text-align:center; padding:20px; color:#828282;">Belum ada data role di database.</td></tr>
<?php else: ?>
<?php foreach ($role_list as $index => $row): ?>
<tr data-role-name="<?= strtolower(htmlspecialchars($row['role'])); ?>">
<td><?= $index + 1; ?></td>
<td style="font-weight: 500;"><?= htmlspecialchars($row['role']); ?></td>
<td><span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:12px; font-weight:600; font-size:12px;"><?= number_format($row['jumlah_user'], 0, ',', '.'); ?> users</span></td>
<td>
<div style="display: inline-flex; gap: 8px; align-items: center;">
    <a href="editRole.php?id=<?= $row['id_role']; ?>" class="action-icon-btn action-edit" title="Edit Role">
        <i class="fa-regular fa-pen-to-square" style="position: static !important; margin: 0;"></i>
    </a>
    <a href="daftarRole.php?hapus=<?= $row['id_role']; ?>" class="action-icon-btn action-delete" title="Hapus Role" onclick="return confirm('Yakin ingin menghapus role ini?')">
        <i class="fa-regular fa-trash-can" style="position: static !important; margin: 0;"></i>
    </a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination Controls -->
<div class="pagination-container" id="paginationControls">
<button class="page-btn" onclick="prevPage()"><i class="fa-solid fa-chevron-left" style="position:static !important; margin:0;"></i></button>
<span id="pageNumbers"></span>
<button class="page-btn" onclick="nextPage()"><i class="fa-solid fa-chevron-right" style="position:static !important; margin:0;"></i></button>
</div>
</section>
</main>
</div>

<script>
function toggleDropdown(element) {
    element.parentElement.classList.toggle('active');
}

let currentPage = 1;
let rowsPerPage = 10;
const tbody = document.getElementById("tableBody");
const allRows = Array.from(tbody.getElementsByTagName("tr"));
let filteredRows = [...allRows];

function applyFilter() {
    const roleVal = document.getElementById("filterRole").value.toLowerCase();
    filteredRows = allRows.filter(row => {
        if (row.cells.length <= 1) return true;
        const roleText = row.getAttribute("data-role-name") || "";
        return roleVal === "" || roleText === roleVal;
    });
    currentPage = 1;
    renderTable();
}

function resetFilter() {
    document.getElementById("filterRole").value = "";
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
        if(row.cells.length > 1) {
            row.cells[0].innerText = startIndex + idx + 1;
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

renderTable();
</script>
</body>
</html>