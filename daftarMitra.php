<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Hapus Mitra dari Database
if (isset($_GET['hapus'])) {
    $hapus_kode = mysqli_real_escape_string($conn, $_GET['hapus']);
    mysqli_query($conn, "DELETE FROM mitra WHERE kode_mitra = '$hapus_kode'");
    header("Location: daftarMitra.php?msg=deleted");
    exit();
}

$res_mitra = mysqli_query($conn, "SELECT * FROM mitra ORDER BY kode_mitra ASC");
$mitra_list = [];
while ($row = mysqli_fetch_assoc($res_mitra)) {
    $mitra_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-with-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-tambah-mitra { background-color: #004b87; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-aktif, .badge-accept { background-color: #e6f7ed; color: #1e7e34; }
        .badge-pending, .badge-invitation { background-color: #fff4e5; color: #d97706; }
        .badge-nonaktif { background-color: #ffe5e5; color: #c82333; }
        .action-btn-group { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-icon-action { width: 30px; height: 30px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-resend { background-color: #e6f7ed; color: #1e7e34; }
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
                        <a href="daftarMitra.php" class="sub-active">Mitra</a>
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
                    <h1 class="page-header-title">MITRA</h1>
                </header>
                <a href="tambahMitra.php" class="btn-tambah-mitra"><i class="fa-solid fa-plus"></i> Tambah Mitra</a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600;">
                    <i class="fa-solid fa-circle-check"></i> Data mitra berhasil diperbarui di database!
                </div>
            <?php endif; ?>

            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group">
                        <label>Kategori Mitra</label>
                        <select id="filterKategori">
                            <option value="">Semua kategori</option>
                            <option value="Transportasi">Transportasi</option>
                            <option value="Wisata & Rekreasi">Wisata & Rekreasi</option>
                            <option value="Retail & F&B">Retail & F&B</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status Akun</label>
                        <select id="filterStatusAkun">
                            <option value="">Semua status</option>
                            <option value="Accept">Accept</option>
                            <option value="Invitation">Invitation</option>
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
                        <input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search mitra, kode, atau status...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="mitraTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Kode</th>
                                <th>Nama Mitra</th>
                                <th>Kategori</th>
                                <th>Masa Kontrak</th>
                                <th>Status Mitra</th>
                                <th>Status Akun</th>
                                <th style="width: 14%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($mitra_list) === 0): ?>
                                <tr><td colspan="8" style="text-align:center; padding:20px; color:#828282;">Belum ada data mitra di database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mitra_list as $index => $mitra): ?>
                                <tr data-kategori="<?= htmlspecialchars($mitra['kategori_mitra']); ?>"
                                    data-akun="<?= htmlspecialchars($mitra['status_akun']); ?>">
                                    <td><?= $index + 1; ?></td>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($mitra['kode_mitra']); ?></td>
                                    <td style="text-align: left; font-weight: 500;"><?= htmlspecialchars($mitra['nama_mitra']); ?></td>
                                    <td><?= htmlspecialchars($mitra['kategori_mitra']); ?></td>
                                    <td><?= htmlspecialchars($mitra['masa_kontrak'] ?: '-'); ?></td>
                                    <td>
                                        <?php
                                            $badgeMitraClass = 'badge-aktif';
                                            if ($mitra['status_mitra'] === 'Pending') $badgeMitraClass = 'badge-pending';
                                            elseif ($mitra['status_mitra'] === 'Nonaktif') $badgeMitraClass = 'badge-nonaktif';
                                        ?>
                                        <span class="badge-status <?= $badgeMitraClass; ?>"><?= htmlspecialchars($mitra['status_mitra']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($mitra['status_akun'] === 'Accept'): ?>
                                            <span class="badge-status badge-accept"><i class="fa-solid fa-circle-check"></i> Accept</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-invitation"><i class="fa-regular fa-envelope"></i> Invitation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <?php if ($mitra['status_akun'] === 'Invitation'): ?>
                                                <button type="button" class="btn-icon-action btn-resend" title="Kirim Ulang Undangan" onclick="alert('Undangan dikirim ulang ke <?= htmlspecialchars($mitra['email_pic']); ?>')">
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="editMitra.php?id=<?= urlencode($mitra['kode_mitra']); ?>" class="btn-icon-action btn-edit" title="Edit Mitra">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </a>
                                            <a href="daftarMitra.php?hapus=<?= urlencode($mitra['kode_mitra']); ?>" class="btn-icon-action btn-delete" title="Hapus Mitra" onclick="return confirm('Hapus mitra <?= htmlspecialchars($mitra['nama_mitra']); ?> dari database?')">
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
            const kat = document.getElementById("filterKategori").value;
            const akun = document.getElementById("filterStatusAkun").value;
            const q = document.getElementById("searchInput").value.toLowerCase();
            allRows.forEach(r => {
                const matchKat = kat === "" || r.getAttribute("data-kategori") === kat;
                const matchAkun = akun === "" || r.getAttribute("data-akun") === akun;
                const matchQ = r.innerText.toLowerCase().includes(q);
                r.style.display = (matchKat && matchAkun && matchQ) ? "" : "none";
            });
        }
        function resetFilter() {
            document.getElementById("filterKategori").value = "";
            document.getElementById("filterStatusAkun").value = "";
            document.getElementById("searchInput").value = "";
            applyFilter();
        }
        function changePageSize() {}
    </script>
</body>
</html>