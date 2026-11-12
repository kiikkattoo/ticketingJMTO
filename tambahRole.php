<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Generate Kode Role Otomatis
$res_count = mysqli_query($conn, "SELECT COUNT(*) AS total FROM roles");
$row_count = mysqli_fetch_assoc($res_count);
$auto_kode = "ROL-" . str_pad(($row_count['total'] ?? 0) + 1, 3, "0", STR_PAD_LEFT);

$error_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_role   = mysqli_real_escape_string($conn, $_POST['id_role']);$nama_role = mysqli_real_escape_string($conn,$_POST['nama_role']);
    $hak_akses = isset($_POST['akses']) ? mysqli_real_escape_string($conn, implode(',',$_POST['akses'])) : '';

    $sql = "INSERT INTO roles (id_role, nama_role, hak_akses) VALUES ('$id_role', '$nama_role', '$hak_akses')";
    if (mysqli_query($conn,$sql)) {
        header("Location: daftarRole.php?msg=added");
        exit();
    } else {
        $error_msg = "Gagal menyimpan role: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Role - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <header class="top-header">
                <p class="welcome-text">Manajemen / Role</p>
                <h1 class="page-header-title">Tambah Role</h1>
            </header>

            <section class="table-section-card">
                <?php if ($error_msg): ?>
                    <div style="background:#ffe5e5; color:#c82333; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;"><?= $error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>Kode Role</label>
                            <input type="text" name="id_role" value="<?= $auto_kode; ?>" readonly style="background:#f4f6f9; color:#7d7d7d; padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Nama Role</label>
                            <input type="text" name="nama_role" placeholder="Contoh: Supervisor Gate" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:#333; margin-bottom:10px;">Hak Akses Modul</label>
                        <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:13px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="dashboard" checked> Dashboard</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="laporan"> Laporan Transaksi</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="monitoring"> Monitoring CCTV & Area</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="manajemen"> Manajemen Data</label>
                        </div>
                    </div>

                    <div class="filter-buttons" style="justify-content: flex-end;">
                        <a href="daftarRole.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Batal</a>
                        <button type="submit" class="btn-filter">Simpan Role</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
    <script>function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }</script>
</body>
</html>