<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$res = mysqli_query($conn, "SELECT * FROM roles WHERE id_role = '$id' LIMIT 1");
$data = mysqli_fetch_assoc($res);

if (!$data) {
    header("Location: daftarRole.php");
    exit();
}

$akses_list = explode(',', $data['hak_akses']);
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_role = mysqli_real_escape_string($conn, $_POST['nama_role']);
    $hak_akses = isset($_POST['akses']) ? mysqli_real_escape_string($conn, implode(',', $_POST['akses'])) : '';

    $sql = "UPDATE roles SET nama_role='$nama_role', hak_akses='$hak_akses' WHERE id_role='$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: daftarRole.php?msg=updated");
        exit();
    } else {
        $error_msg = "Gagal update role: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role - JMTO Ticketing</title>
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
                <h1 class="page-header-title">Edit Role</h1>
            </header>

            <section class="table-section-card">
                <?php if ($error_msg): ?>
                    <div style="background:#ffe5e5; color:#c82333; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;"><?= $error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>Kode Role</label>
                            <input type="text" value="<?= htmlspecialchars($data['id_role']); ?>" readonly style="background:#f4f6f9; color:#7d7d7d; padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Nama Role</label>
                            <input type="text" name="nama_role" value="<?= htmlspecialchars($data['nama_role']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:#333; margin-bottom:10px;">Hak Akses Modul</label>
                        <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:13px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="dashboard" <?= in_array('dashboard', $akses_list) ? 'checked' : ''; ?>> Dashboard</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="laporan" <?= in_array('laporan', $akses_list) ? 'checked' : ''; ?>> Laporan Transaksi</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="monitoring" <?= in_array('monitoring', $akses_list) ? 'checked' : ''; ?>> Monitoring CCTV & Area</label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="akses[]" value="manajemen" <?= in_array('manajemen', $akses_list) ? 'checked' : ''; ?>> Manajemen Data</label>
                        </div>
                    </div>

                    <div class="filter-buttons" style="justify-content: flex-end;">
                        <a href="daftarRole.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Batal</a>
                        <button type="submit" class="btn-filter">Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
    <script>function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }</script>
</body>
</html>