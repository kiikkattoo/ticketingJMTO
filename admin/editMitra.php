<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$res = mysqli_query($conn, "SELECT * FROM mitra WHERE kode_mitra = '$id' LIMIT 1");
$data = mysqli_fetch_assoc($res);

if (!$data) {
    header("Location: daftarMitra.php");
    exit();
}

$error_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_mitra     = mysqli_real_escape_string($conn, $_POST['nama_mitra']);
    $kategori_mitra = mysqli_real_escape_string($conn, $_POST['kategori_mitra']);
    $nama_pic       = mysqli_real_escape_string($conn, $_POST['nama_pic']);
    $email_pic      = mysqli_real_escape_string($conn, $_POST['email_pic']);
    $telepon_pic    = mysqli_real_escape_string($conn, $_POST['telepon_pic']);
    $masa_kontrak   = mysqli_real_escape_string($conn, $_POST['masa_kontrak']);
    $status_mitra   = mysqli_real_escape_string($conn, $_POST['status_mitra']);
    $status_akun    = mysqli_real_escape_string($conn, $_POST['status_akun']);
    $alamat         = mysqli_real_escape_string($conn, $_POST['alamat']);

    $sql = "UPDATE mitra SET nama_mitra='$nama_mitra', kategori_mitra='$kategori_mitra', nama_pic='$nama_pic', email_pic='$email_pic', telepon_pic='$telepon_pic', masa_kontrak='$masa_kontrak', status_mitra='$status_mitra', status_akun='$status_akun', alamat='$alamat' WHERE kode_mitra='$id'";

    if (mysqli_query($conn, $sql)) {
        header("Location: daftarMitra.php?msg=updated");
        exit();
    } else {
        $error_msg = "Gagal update mitra: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <a href="daftarUser.php">User</a>
                        <a href="daftarRole.php">Role</a>
                        <a href="daftarMitra.php" class="sub-active">Mitra</a>
                        <a href="daftarPerangkat.php">Perangkat</a>
                    </div>
                </div>
                <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <p class="welcome-text">Manajemen / Mitra</p>
                <h1 class="page-header-title">Edit Mitra</h1>
            </header>

            <section class="table-section-card">
                <?php if ($error_msg): ?>
                    <div style="background:#ffe5e5; color:#c82333; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;"><?= $error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>Kode Mitra</label>
                            <input type="text" value="<?= htmlspecialchars($data['kode_mitra']); ?>" readonly style="background:#f4f6f9; color:#7d7d7d; padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Nama Perusahaan / Mitra</label>
                            <input type="text" name="nama_mitra" value="<?= htmlspecialchars($data['nama_mitra']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Kategori Mitra</label>
                            <select name="kategori_mitra" required>
                                <option value="Transportasi" <?= $data['kategori_mitra'] === 'Transportasi' ? 'selected' : ''; ?>>Transportasi</option>
                                <option value="Wisata & Rekreasi" <?= $data['kategori_mitra'] === 'Wisata & Rekreasi' ? 'selected' : ''; ?>>Wisata & Rekreasi</option>
                                <option value="Retail & F&B" <?= $data['kategori_mitra'] === 'Retail & F&B' ? 'selected' : ''; ?>>Retail & F&B</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>Nama PIC</label>
                            <input type="text" name="nama_pic" value="<?= htmlspecialchars($data['nama_pic']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Email PIC</label>
                            <input type="email" name="email_pic" value="<?= htmlspecialchars($data['email_pic']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>No. Telepon</label>
                            <input type="text" name="telepon_pic" value="<?= htmlspecialchars($data['telepon_pic']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                    </div>

                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>Masa Kontrak</label>
                            <input type="text" name="masa_kontrak" value="<?= htmlspecialchars($data['masa_kontrak']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Status Mitra</label>
                            <select name="status_mitra" required>
                                <option value="Aktif" <?= $data['status_mitra'] === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Pending" <?= $data['status_mitra'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Nonaktif" <?= $data['status_mitra'] === 'Nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status Akun</label>
                            <select name="status_akun" required>
                                <option value="Accept" <?= $data['status_akun'] === 'Accept' ? 'selected' : ''; ?>>Accept</option>
                                <option value="Invitation" <?= $data['status_akun'] === 'Invitation' ? 'selected' : ''; ?>>Invitation</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row" style="margin-bottom: 25px;">
                        <div class="filter-group">
                            <label>Alamat Lengkap</label>
                            <input type="text" name="alamat" value="<?= htmlspecialchars($data['alamat']); ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                    </div>

                    <div class="filter-buttons" style="justify-content: flex-end;">
                        <a href="daftarMitra.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Batal</a>
                        <button type="submit" class="btn-filter">Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
    <script>function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }</script>
</body>
</html>