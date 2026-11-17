<?php
session_start();
require_once '../koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Generate ID User berurutan 3 digit berdasarkan kode_user terakhir (USR-008, dst.)
$q_last_usr = mysqli_query($conn, "SELECT kode_user FROM users WHERE kode_user LIKE 'USR-%' ORDER BY kode_user DESC LIMIT 1");
$num_usr = 0;
if ($q_last_usr && mysqli_num_rows($q_last_usr) > 0) {
    $last_usr = mysqli_fetch_assoc($q_last_usr)['kode_user'];
    $num_usr = (int)preg_replace('/[^0-9]/', '', $last_usr);
}
$auto_kode = "USR-" . str_pad($num_usr + 1, 3, "0", STR_PAD_LEFT);

// Ambil opsi Role dari tabel roles
$res_roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY id_role ASC");
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kode_user   = mysqli_real_escape_string($conn, $_POST['kode_user']);
    $nama_user   = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $email       = mysqli_real_escape_string($conn, $_POST['email']);
    $id_role     = mysqli_real_escape_string($conn, $_POST['id_role']);
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $status_user = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "INSERT INTO users (kode_user, nama_user, email, id_role, password, status_user)
            VALUES ('$kode_user', '$nama_user', '$email', '$id_role', '$password', '$status_user')";

    if (mysqli_query($conn, $sql)) {
        header("Location: daftarUser.php?msg=added");
        exit();
    } else {
        $error_msg = "Gagal menyimpan ke database: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah User - JMTO Ticketing</title>
<link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.back-link { display: inline-flex; align-items: center; gap: 8px; color: #004687; text-decoration: none; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
.form-stack-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.form-stack-group label { font-size: 13px; font-weight: 600; color: #1e293b; }
.form-stack-group input, .form-stack-group select { width: 100%; padding: 12px 14px; border: 1px solid #d0d7de; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; }
.form-stack-group input[readonly] { background-color: #e5e7eb; color: #6b7280; }
.radio-status-group { display: flex; align-items: center; gap: 24px; margin-top: 4px; }
.radio-status-item { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
.radio-status-item input[type="radio"] { accent-color: #004b87; width: 16px; height: 16px; }
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
<a href="daftarVenue.php">Venue</a>
</div>
</div>
<a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
</nav>
</aside>

<main class="main-content">
<header class="top-header" style="margin-bottom: 10px;">
<p class="welcome-text">Manajemen</p>
<h1 class="page-header-title">Tambah User</h1>
</header>
<a href="daftarUser.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali</a>

<section class="table-section-card">
<?php if ($error_msg): ?>
<div style="background: #ffe5e5; color: #c82333; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;"><?= $error_msg; ?></div>
<?php endif; ?>

<form method="POST" action="">
<div class="form-stack-group">
<label>ID</label>
<input type="text" name="kode_user" value="<?= $auto_kode; ?>" readonly>
</div>
<div class="form-stack-group">
<label>Nama User</label>
<input type="text" name="nama_user" placeholder="Masukkan nama user" required>
</div>
<div class="form-stack-group">
<label>Email</label>
<input type="email" name="email" placeholder="Masukkan email" required>
</div>
<div class="form-stack-group">
<label>Role</label>
<select name="id_role" required>
<option value="">Pilih role</option>
<?php while ($r = mysqli_fetch_assoc($res_roles)): ?>
<option value="<?= htmlspecialchars($r['id_role']); ?>"><?= htmlspecialchars($r['nama_role']); ?></option>
<?php endwhile; ?>
</select>
</div>
<div class="form-stack-group">
<label>Password</label>
<input type="password" name="password" placeholder="Masukkan password" required>
</div>
<div class="form-stack-group" style="margin-bottom: 26px;">
<label>Status</label>
<div class="radio-status-group">
<label class="radio-status-item"><input type="radio" name="status" value="Active" checked> Active</label>
<label class="radio-status-item"><input type="radio" name="status" value="Inactive"> Inactive</label>
</div>
</div>
<div class="filter-buttons" style="justify-content: flex-end;">
<a href="daftarUser.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Batal</a>
<button type="submit" class="btn-filter">Simpan</button>
</div>
</form>
</section>
</main>
</div>
<script>function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }</script>
</body>
</html>