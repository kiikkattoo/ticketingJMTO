<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Hapus Perangkat dari Database
if (isset($_GET['hapus'])) {$hapus_id = mysqli_real_escape_string($conn,$_GET['hapus']);
    mysqli_query($conn, "DELETE FROM perangkat WHERE id_perangkat = '$hapus_id'");
    header("Location: daftarPerangkat.php?msg=deleted");
    exit();
}

$query = "SELECT p.*, a.nama_area, a.nama_lokasi, a.nama_gate 
          FROM perangkat p 
          LEFT JOIN area_lokasi a ON p.id_area = a.id_area 
          ORDER BY p.id_perangkat ASC";
$res = mysqli_query($conn, $query);$perangkat_list = [];
while ($row = mysqli_fetch_assoc($res)) {
    $perangkat_list[] =$row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Perangkat - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-with-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-tambah-data { background-color: #004b87; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
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
                        <a href="daftarRole.php">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php" class="sub-active">Perangkat</a>
                    </div>
                </div>
                <a href="logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="header-with-action">
                <header class="top-header" style="margin-bottom: 0;">
                    <p class="welcome-text">Manajemen</p>
                    <h1 class="page-header-title">PERANGKAT</h1>
                </header>
                <a href="tambahPerangkat.php" class="btn-tambah-data"><i class="fa-solid fa-plus"></i> Tambah Perangkat</a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600;">
                    <i class="fa-solid fa-circle-check"></i> Data perangkat berhasil diperbarui di database!
                </div>
            <?php endif; ?>

            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group">
                        <label>Jenis Perangkat</label>
                        <select id="filterJenis">
                            <option value="">Semua jenis</option>
                            <option value="Turnstile">Turnstile</option>
                            <option value="CCTV">CCTV</option>
                            <option value="Barrier Gate">Barrier Gate</option>
                            <option value="Card Reader">Card Reader</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Area</label>
                        <select id="filterArea">
                            <option value="">Semua area</option>
                            <option value="TMII">TMII</option>
                            <option value="Ancol">Ancol</option>
                            <option value="Ragunan">Ragunan</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus">
                            <option value="">Semua status</option>
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
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
                        <input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search nama perangkat, IP, atau gate...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="perangkatTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>ID Perangkat</th>
                                <th>Nama Perangkat</th>
                                <th>Jenis</th>
                                <th>IP Address</th>
                                <th>Area</th>
                                <th>Lokasi & Gate</th>
                                <th>Status</th>
                                <th style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($perangkat_list) === 0): ?>
                                <tr><td colspan="9" style="text-align:center; padding:20px; color:#828282;">Belum ada data perangkat di database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($perangkat_list as $index =>$dev): ?>
                                <tr data-jenis="<?= htmlspecialchars($dev['jenis_perangkat']); ?>"
                                    data-area="<?= htmlspecialchars($dev['nama_area'] ?? ''); ?>"
                                    data-status="<?= htmlspecialchars($dev['status_perangkat']); ?>">
                                    <td><?= $index + 1; ?></td>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($dev['id_perangkat']); ?></td>
                                    <td style="text-align: left; font-weight: 500;"><?= htmlspecialchars($dev['nama_perangkat']); ?></td>
                                    <td><?= htmlspecialchars($dev['jenis_perangkat']); ?></td>
                                    <td><code><?= htmlspecialchars($dev['ip_address']); ?></code></td>
                                    <td><?= htmlspecialchars($dev['nama_area'] ?? '-'); ?></td>
                                    <td style="text-align: left;"><?= htmlspecialchars(($dev['nama_lokasi'] ?? '-') . ' - ' . ($dev['nama_gate'] ?? '-')); ?></td>
                                    <td>
                                        <span class="badge-status <?= $dev['status_perangkat'] === 'Aktif' ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                            <?= htmlspecialchars($dev['status_perangkat']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="editPerangkat.php?id=<?= urlencode($dev['id_perangkat']); ?>" class="btn-icon-action btn-edit" title="Edit Perangkat">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </a>
                                            <a href="daftarPerangkat.php?hapus=<?= urlencode($dev['id_perangkat']); ?>" class="btn-icon-action btn-delete" title="Hapus Perangkat" onclick="return confirm('Hapus perangkat <?= htmlspecialchars($dev['nama_perangkat']); ?>?')">
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
            const j = document.getElementById("filterJenis").value;
            const a = document.getElementById("filterArea").value;
            const s = document.getElementById("filterStatus").value;
            const q = document.getElementById("searchInput").value.toLowerCase();
            allRows.forEach(r => {
                const mJ = j === "" or r.getAttribute("data-jenis") === j;
                const mA = a === "" or r.getAttribute("data-area") === a;
                const mS = s === "" or r.getAttribute("data-status") === s;
                const mQ = r.innerText.toLowerCase().includes(q);
                r.style.display = (mJ && mA && mS && mQ) ? "" : "none";
            });
        }
        function resetFilter() {
            document.getElementById("filterJenis").value = "";
            document.getElementById("filterArea").value = "";
            document.getElementById("filterStatus").value = "";
            document.getElementById("searchInput").value = "";
            applyFilter();
        }
        function changePageSize() {}
    </script>
</body>
</html>