<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Proses Hapus User dari Database
if (isset($_GET['hapus'])) {
    $hapus_id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM users WHERE id = $hapus_id");
    header("Location: daftarUser.php?msg=deleted");
    exit();
}

// Ambil Data User + Nama Role dari Database
$query_users = "SELECT u.*, r.nama_role 
                FROM users u 
                LEFT JOIN roles r ON u.id_role = r.id_role 
                ORDER BY u.id ASC";
$res_users = mysqli_query($conn, $query_users);$users_list = [];
while ($row = mysqli_fetch_assoc($res_users)) {
    $users_list[] =$row;
}

// Ambil Daftar Role untuk Dropdown Filter
$res_roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY nama_role ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar User - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-with-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-tambah-data { background-color: #004b87; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-tambah-data:hover { background-color: #003663; }
        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; }
        .badge-aktif { background-color: #e6f7ed; color: #1e7e34; }
        .badge-nonaktif { background-color: #ffe5e5; color: #c82333; }
        .action-btn-group { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-icon-action { width: 30px; height: 30px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-edit { background-color: #eef5fc; color: #004b87; }
        .btn-delete { background-color: #ffe5e5; color: #eb5757; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-logo"><img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img"></div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="detailTransaksi.php">Detail Transaksi</a>
                        <a href="daftarTransaksi.php">Daftar Transaksi</a>
                        <a href="rekapTransaksi.php">Rekap Transaksi</a>
                    </div>
                </div>
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-desktop icon"></i> Monitoring <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="cctv.php">CCTV</a>
                        <a href="monitoringArea.php">Perangkat Area</a>
                    </div>
                </div>
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-briefcase icon"></i> Manajemen <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="daftarUser.php" class="sub-active">User</a>
                        <a href="daftarRole.php">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php">Perangkat</a>
                    </div>
                </div>
                <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="header-with-action">
                <header class="top-header" style="margin-bottom: 0;">
                    <p class="welcome-text">Manajemen</p>
                    <h1 class="page-header-title">USER</h1>
                </header>
                <a href="tambahUser.php" class="btn-tambah-data"><i class="fa-solid fa-plus"></i> Tambah User</a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600;">
                    <i class="fa-solid fa-circle-check"></i> Data user berhasil diperbarui di database!
                </div>
            <?php endif; ?>

            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group">
                        <label>Role</label>
                        <select id="filterRole">
                            <option value="">Semua role</option>
                            <?php while ($r = mysqli_fetch_assoc($res_roles)): ?>
                                <option value="<?= htmlspecialchars($r['nama_role']); ?>"><?= htmlspecialchars($r['nama_role']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus">
                            <option value="">Semua status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="btn-reset" onclick="resetFilter()">Reset</button>
                        <button type="button" class="btn-filter" onclick="applyFilter()">Filter</button>
                    </div>
                </div>
            </section>

            <section class="table-section-card">
                <div class="table-toolbar">
                    <div class="page-size-box">
                        <select id="rowsPerPage" onchange="changePageSize()">
                            <option value="5">5/page</option>
                            <option value="10" selected>10/page</option>
                            <option value="25">25/page</option>
                        </select>
                    </div>
                    <div class="search-box">
                        <input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search nama, email, atau role...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="userTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>ID User</th>
                                <th>Nama User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($users_list) === 0): ?>
                                <tr><td colspan="7" style="text-align:center; padding:20px; color:#828282;">Belum ada data user di database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users_list as $index =>$user): ?>
                                <tr data-role="<?= htmlspecialchars($user['nama_role'] ?? ''); ?>"
                                    data-status="<?= htmlspecialchars($user['status_user']); ?>">
                                    <td><?= $index + 1; ?></td>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($user['kode_user'] ?: 'USR-' . str_pad($user['id'], 3, '0', STR_PAD_LEFT)); ?></td>
                                    <td style="text-align: left; font-weight: 500;"><?= htmlspecialchars($user['nama_user'] ?: '-'); ?></td>
                                    <td style="text-align: left;"><?= htmlspecialchars($user['email']); ?></td>
                                    <td><?= htmlspecialchars($user['nama_role'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge-status <?= $user['status_user'] === 'Active' ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                            <?= htmlspecialchars($user['status_user']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="editUser.php?id=<?= $user['id']; ?>" class="btn-icon-action btn-edit" title="Edit User">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </a>
                                            <a href="daftarUser.php?hapus=<?= $user['id']; ?>" class="btn-icon-action btn-delete" title="Hapus User" onclick="return confirm('Hapus user <?= htmlspecialchars($user['email']); ?> dari database?')">
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

                <div class="pagination-container" id="paginationControls">
                    <button class="page-btn" onclick="prevPage()"><i class="fa-solid fa-chevron-left"></i></button>
                    <span id="pageNumbers"></span>
                    <button class="page-btn" onclick="nextPage()"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }
        let currentPage = 1, rowsPerPage = 10;
        const tbody = document.getElementById("tableBody");
        const allRows = Array.from(tbody.getElementsByTagName("tr"));
        let filteredRows = [...allRows];

        function applyFilter() {
            const roleVal = document.getElementById("filterRole").value;
            const statusVal = document.getElementById("filterStatus").value;
            const searchVal = document.getElementById("searchInput").value.toLowerCase();

            filteredRows = allRows.filter(row => {
                const rRole = row.getAttribute("data-role") || "";
                const rStatus = row.getAttribute("data-status") || "";
                const rText = row.innerText.toLowerCase();
                return (roleVal === "" || rRole === roleVal) &&
                       (statusVal === "" || rStatus === statusVal) &&
                       rText.includes(searchVal);
            });
            currentPage = 1;
            renderTable();
        }
        function resetFilter() {
            document.getElementById("filterRole").value = "";
            document.getElementById("filterStatus").value = "";
            document.getElementById("searchInput").value = "";
            filteredRows = [...allRows];
            currentPage = 1;
            renderTable();
        }
        function changePageSize() { rowsPerPage = parseInt(document.getElementById("rowsPerPage").value); currentPage = 1; renderTable(); }
        function renderTable() {
            allRows.forEach(r => r.style.display = "none");
            const start = (currentPage - 1) * rowsPerPage;
            filteredRows.slice(start, start + rowsPerPage).forEach((r, idx) => {
                r.style.display = "";
                if (r.getElementsByTagName("td").length > 1) r.getElementsByTagName("td")[0].innerText = start + idx + 1;
            });
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            const pNum = document.getElementById("pageNumbers");
            pNum.innerHTML = "";
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement("button");
                btn.innerText = i;
                btn.className = "page-num" + (i === currentPage ? " active" : "");
                btn.onclick = () => { currentPage = i; renderTable(); };
                pNum.appendChild(btn);
            }
        }
        function prevPage() { if (currentPage > 1) { currentPage--; renderTable(); } }
        function nextPage() { if (currentPage < Math.ceil(filteredRows.length / rowsPerPage)) { currentPage++; renderTable(); } }
        renderTable();
    </script>
</body>
</html>