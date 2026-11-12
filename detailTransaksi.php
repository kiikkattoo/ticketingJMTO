<?php
session_start();
require 'koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Ambil Opsi Filter Dinamis dari Database
$opt_mitra = mysqli_query($conn, "SELECT DISTINCT nama_mitra FROM mitra ORDER BY nama_mitra ASC");
$opt_area  = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi ORDER BY nama_area ASC");

// Bangun Kondisi Filter Jika User Memilih Filter
$where_clauses = ["1=1"];
$f_kategori =$_GET['kategori'] ?? '';
$f_mitra    =$_GET['mitra'] ?? '';
$f_area     =$_GET['area'] ?? '';
$f_metode   =$_GET['metode'] ?? '';
$f_status   =$_GET['status'] ?? '';
$f_periode  =$_GET['periode'] ?? '';

if ($f_kategori !== '') {$val = mysqli_real_escape_string($conn,$f_kategori);
    $where_clauses[] = "m.kategori_mitra = '$val'";
}
if ($f_mitra !== '') {$val = mysqli_real_escape_string($conn,$f_mitra);
    $where_clauses[] = "m.nama_mitra = '$val'";
}
if ($f_area !== '') {$val = mysqli_real_escape_string($conn,$f_area);
    $where_clauses[] = "a.nama_area = '$val'";
}
if ($f_metode !== '') {$val = mysqli_real_escape_string($conn,$f_metode);
    $where_clauses[] = "t.penerbit = '$val'";
}
if ($f_status !== '') {$val = mysqli_real_escape_string($conn,$f_status);
    $where_clauses[] = "t.status_transaksi = '$val'";
}
if ($f_periode === 'Hari Ini') {$where_clauses[] = "DATE(t.waktu_transaksi) = CURDATE()";
} elseif ($f_periode === 'Minggu Ini') {$where_clauses[] = "YEARWEEK(t.waktu_transaksi, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($f_periode === 'Bulan Ini') {$where_clauses[] = "MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE())";
}

$where_sql = implode(" AND ", $where_clauses);

// Query Agregasi 6 Card Statistik
$sql_stats = "SELECT 
                COUNT(t.id_transaksi) AS total_transaksi,
                COUNT(CASE WHEN t.status_transaksi = 'Berhasil' THEN 1 END) AS transaksi_berhasil,
                COUNT(CASE WHEN t.status_transaksi = 'Pending' THEN 1 END) AS menunggu_pembayaran,
                COUNT(CASE WHEN t.status_transaksi = 'Gagal' THEN 1 END) AS transaksi_gagal,
                COUNT(CASE WHEN t.status_transaksi = 'Refund' THEN 1 END) AS transaksi_refund,
                COALESCE(SUM(CASE WHEN t.status_transaksi = 'Berhasil' THEN t.nominal ELSE 0 END), 0) AS total_nominal
              FROM transaksi t
              LEFT JOIN area_lokasi a ON t.id_area = a.id_area
              LEFT JOIN mitra m ON a.kode_mitra = m.kode_mitra
              WHERE $where_sql";

$res_stats = mysqli_query($conn,$sql_stats);
$stats = mysqli_fetch_assoc($res_stats);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icon Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="dashboard-wrapper">
        <!-- Sidebar Fixed -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
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
                        <a href="detailTransaksi.php" class="sub-active">Detail Transaksi</a>
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
                    </div>
                </div>

                <a href="logout.php" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
                </a>
            </nav>
        </aside>

        <!-- Area Konten Utama -->
        <main class="main-content">
            <header class="top-header">
                <p class="welcome-text">Laporan</p>
                <h1 class="page-header-title">Detail Transaksi</h1>
            </header>

            <!-- Filter Card 6 Input -->
            <section class="filter-card">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Kategori Mitra</label>
                            <select name="kategori" onchange="this.form.submit()">
                                <option value="">Pilih kategori mitra</option>
                                <option value="Transportasi" <?= $f_kategori === 'Transportasi' ? 'selected' : ''; ?>>Transportasi</option>
                                <option value="Wisata & Rekreasi" <?= $f_kategori === 'Wisata & Rekreasi' ? 'selected' : ''; ?>>Wisata & Rekreasi</option>
                                <option value="Retail & F&B" <?= $f_kategori === 'Retail & F&B' ? 'selected' : ''; ?>>Retail & F&B</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Mitra</label>
                            <select name="mitra" onchange="this.form.submit()">
                                <option value="">Pilih mitra</option>
                                <?php while ($m = mysqli_fetch_assoc($opt_mitra)): ?>
                                    <option value="<?= htmlspecialchars($m['nama_mitra']); ?>" <?= $f_mitra ===$m['nama_mitra'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($m['nama_mitra']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Area</label>
                            <select name="area" onchange="this.form.submit()">
                                <option value="">Pilih Area</option>
                                <?php while ($a = mysqli_fetch_assoc($opt_area)): ?>
                                    <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= $f_area ===$a['nama_area'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($a['nama_area']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Metode Pembayaran</label>
                            <select name="metode" onchange="this.form.submit()">
                                <option value="">Pilih metode pembayaran</option>
                                <option value="Bank DKI" <?= $f_metode === 'Bank DKI' ? 'selected' : ''; ?>>Bank DKI</option>
                                <option value="GOERS" <?= $f_metode === 'GOERS' ? 'selected' : ''; ?>>GOERS</option>
                                <option value="Petugas" <?= $f_metode === 'Petugas' ? 'selected' : ''; ?>>Petugas</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status Transaksi</label>
                            <select name="status" onchange="this.form.submit()">
                                <option value="">Pilih status transaksi</option>
                                <option value="Berhasil" <?= $f_status === 'Berhasil' ? 'selected' : ''; ?>>Berhasil</option>
                                <option value="Pending" <?= $f_status === 'Pending' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                                <option value="Gagal" <?= $f_status === 'Gagal' ? 'selected' : ''; ?>>Gagal</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Periode</label>
                            <select name="periode" onchange="this.form.submit()">
                                <option value="">Pilih periode</option>
                                <option value="Hari Ini" <?= $f_periode === 'Hari Ini' ? 'selected' : ''; ?>>Hari Ini</option>
                                <option value="Minggu Ini" <?= $f_periode === 'Minggu Ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                                <option value="Bulan Ini" <?= $f_periode === 'Bulan Ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                            </select>
                        </div>
                    </div>
                </form>
            </section>

            <!-- 6 Card Statistik Terpisah (Grid 3 Kolom x 2 Baris) -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div class="card-info">
                        <span class="card-label">Total Transaksi</span>
                        <h2 class="card-value"><?= number_format($stats['total_transaksi'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="card-info">
                        <span class="card-label">Transaksi Berhasil</span>
                        <h2 class="card-value"><?= number_format($stats['transaksi_berhasil'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="card-info">
                        <span class="card-label">Menunggu Pembayaran</span>
                        <h2 class="card-value"><?= number_format($stats['menunggu_pembayaran'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                    <div class="card-info">
                        <span class="card-label">Transaksi Gagal</span>
                        <h2 class="card-value"><?= number_format($stats['transaksi_gagal'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-arrow-rotate-left"></i></div>
                    <div class="card-info">
                        <span class="card-label">Transaksi Refund</span>
                        <h2 class="card-value"><?= number_format($stats['transaksi_refund'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-wallet"></i></div>
                    <div class="card-info">
                        <span class="card-label">Total Nominal</span>
                        <h2 class="card-value">Rp <?= number_format($stats['total_nominal'], 0, ',', '.'); ?></h2>
                        <span class="card-sub"><i class="fa-solid fa-database"></i> Data database</span>
                    </div>
                </div>
            </section>

            <!-- Section Konten Bawah -->
            <section class="detail-placeholder-grid">
                <div class="placeholder-card"></div>
                <div class="placeholder-card"></div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(element) {
            const menuGroup = element.parentElement;
            menuGroup.classList.toggle('active');
        }
    </script>
</body>
</html>