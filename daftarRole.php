<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Hapus Role dari Database
if (isset($_GET['hapus'])) {
    $hapus_id = mysqli_real_escape_string($conn, $_GET['hapus']);
    mysqli_query($conn, "DELETE FROM roles WHERE id_role = '$hapus_id'");
    header("Location: daftarRole.php?msg=deleted");
    exit();
}

$res_roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY id_role ASC");
$roles_list = [];
while ($row = mysqli_fetch_assoc($res_roles)) {
    $roles_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Role - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-with-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-tambah-data { background-color: #004b87; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-tambah-data:hover { background-color: #003663; }
        .badge-akses { background-color: #eef5fc; color: #004b87; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; margin: 2px; text-transform: capitalize; }
        .action-btn-group { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-icon-action { width: 30px; height: 30px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-edit { background-color: #eef5fc; color: #004b87; }
        .btn-delete { background-color: #ffe5e5; color: #eb5757; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-logo"><img src="jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img"></div>
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
                        <a href="daftarUser.php">User</a>
                        <a href="daftarRole.php" class="sub-active">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php">Perangkat</a>
                    </div>
                </div>
                <a href="logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="header-with-action">
                <header class="top-header" style="margin-bottom: 0;">
                    <p class="welcome-text">Manajemen</p>
                    <h1 class="page-header-title">ROLE</h1>
                </header>
                <a href="tambahRole.php" class="btn-tambah-data"><i class="fa-solid fa-plus"></i> Tambah Role</a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600;">
                    <i class="fa-solid fa-circle-check"></i> Data role berhasil diperbarui di database!
                </div>
            <?php endif; ?>

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
                        <input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search kode atau nama role...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="roleTable">
                        <thead>
                            <tr>
                                <th style="width: 6%;">No</th>
                                <th>Kode Role</th>
                                <th>Nama Role</th>
                                <th>Hak Akses Modul</th>
                                <th style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($roles_list) === 0): ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px; color:#828282;">Belum ada data role di database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($roles_list as $index => $role): 
                                    $aksesArr = array_filter(explode(',', $role['hak_akses']));
                                ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($role['id_role']); ?></td>
                                    <td style="text-align: left; font-weight: 500;"><?= htmlspecialchars($role['nama_role']); ?></td>
                                    <td style="text-align: left;">
                                        <?php foreach ($aksesArr as $aks): ?>
                                            <span class="badge-akses"><?= htmlspecialchars(trim($aks)); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="editRole.php?id=<?= urlencode($role['id_role']); ?>" class="btn-icon-action btn-edit" title="Edit Role">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </a>
                                            <a href="daftarRole.php?hapus=<?= urlencode($role['id_role']); ?>" class="btn-icon-action btn-delete" title="Hapus Role" onclick="return confirm('Hapus role <?= htmlspecialchars($role['nama_role']); ?>?')">
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
            </section>
        </main>
    </div>
    <script>
        function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }
        const allRows = Array.from(document.getElementById("tableBody").getElementsByTagName("tr"));
        function applyFilter() {
            const q = document.getElementById("searchInput").value.toLowerCase();
            allRows.forEach(r => { r.style.display = r.innerText.toLowerCase().includes(q) ? "" : "none"; });
        }
        function changePageSize() {}
    </script>
</body>
</html>