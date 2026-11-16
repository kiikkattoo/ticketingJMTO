<?php
session_start();
require_once dirname(__FILE__) . '/../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

$kode_mitra = mysqli_real_escape_string($conn, trim($_GET['id'] ?? $_POST['id_mitra'] ?? ''));
if (empty($kode_mitra)) {
    header("Location: daftarMitra.php");
    exit();
}

$success_msg = "";
$error_msg   = "";

// Ambil data mitra saat ini
$q_mitra = mysqli_query($conn, "SELECT * FROM mitra WHERE kode_mitra = '$kode_mitra' LIMIT 1");
if (!$q_mitra || mysqli_num_rows($q_mitra) === 0) {
    header("Location: daftarMitra.php");
    exit();
}
$mitra = mysqli_fetch_assoc($q_mitra);

// Proses Simpan Perubahan (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_mitra     = mysqli_real_escape_string($conn, trim($_POST["nama_mitra"] ?? ""));
    $kategori_mitra = mysqli_real_escape_string($conn, trim($_POST["kategori_mitra"] ?? ""));
    $lokasi         = mysqli_real_escape_string($conn, trim($_POST["lokasi"] ?? ""));
    $no_telp        = mysqli_real_escape_string($conn, trim($_POST["no_telp"] ?? ""));
    $email          = mysqli_real_escape_string($conn, trim($_POST["email"] ?? ""));
    $masa_kontrak   = mysqli_real_escape_string($conn, trim($_POST["masa_kontrak"] ?? ""));
    $status_mitra   = mysqli_real_escape_string($conn, trim($_POST["status_mitra"] ?? "Aktif"));
    $status_akun    = mysqli_real_escape_string($conn, trim($_POST["status_akun"] ?? "Invitation"));

    // Data PIC & Legal
    $nama_pic = mysqli_real_escape_string($conn, trim($_POST["nama_pic"] ?? ""));
    $jabatan  = mysqli_real_escape_string($conn, trim($_POST["jabatan"] ?? ""));
    $email_pic = mysqli_real_escape_string($conn, trim($_POST["email_pic"] ?? ""));
    $telp_pic  = mysqli_real_escape_string($conn, trim($_POST["telp_pic"] ?? ""));
    $npwp      = mysqli_real_escape_string($conn, trim($_POST["npwp"] ?? ""));
    $nib       = mysqli_real_escape_string($conn, trim($_POST["nib"] ?? ""));

    if (empty($nama_mitra)) {
        $error_msg = "Nama Mitra wajib diisi!";
    } else {
        // Handle Upload Logo Baru jika ada
        $logo_filename = $mitra['logo_mitra'] ?? NULL;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/logo_mitra/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_logo = $kode_mitra . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_logo)) {
                $logo_filename = $new_logo;
            }
        }

        // Deteksi kolom yang tersedia di tabel mitra
        $existing_cols = [];
        $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM mitra");
        if ($res_cols) {
            while ($col = mysqli_fetch_assoc($res_cols)) {
                $existing_cols[] = $col['Field'];
            }
        }

        $kandidat_update = [
            'nama_mitra'     => $nama_mitra,
            'kategori_mitra' => $kategori_mitra,
            'alamat'         => $lokasi,
            'telepon_mitra'  => $no_telp,
            'email_mitra'    => $email,
            'logo_mitra'     => $logo_filename,
            'masa_kontrak'   => $masa_kontrak,
            'status_mitra'   => $status_mitra,
            'status_akun'    => $status_akun,
            'nama_pic'       => $nama_pic,
            'jabatan_pic'    => $jabatan,
            'email_pic'      => $email_pic,
            'telepon_pic'    => $telp_pic,
            'npwp'           => $npwp,
            'nib'            => $nib,
        ];

        $set_clauses = [];
        foreach ($kandidat_update as $kolom => $nilai) {
            if (in_array($kolom, $existing_cols)) {
                if ($nilai === NULL) {
                    $set_clauses[] = "`$kolom` = NULL";
                } else {
                    $set_clauses[] = "`$kolom` = '$nilai'";
                }
            }
        }

        $sql_update = "UPDATE mitra SET " . implode(", ", $set_clauses) . " WHERE kode_mitra = '$kode_mitra'";
        if (mysqli_query($conn, $sql_update)) {
            // Sinkronkan nama, email PIC, & status ke tabel users jika terhubung
            $email_user_sync = !empty($email_pic) ? $email_pic : $email;
            $status_user_sync = ($status_akun === 'Accept') ? 'Active' : 'Inactive';
            $sql_sync_user = "UPDATE users 
                              SET nama_user = '$nama_mitra', 
                                  email = '$email_user_sync',
                                  status_user = '$status_user_sync'
                              WHERE id_mitra = '$kode_mitra'";
            mysqli_query($conn, $sql_sync_user);

            $success_msg = "Perubahan data mitra <b>" . htmlspecialchars($nama_mitra) . "</b> ($kode_mitra) berhasil disimpan!";
            // Refresh data mitra terbaru
            $q_mitra = mysqli_query($conn, "SELECT * FROM mitra WHERE kode_mitra = '$kode_mitra' LIMIT 1");
            $mitra = mysqli_fetch_assoc($q_mitra);
        } else {
            $error_msg = "Gagal memperbarui data mitra: " . mysqli_error($conn);
        }
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
    <link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <i class="fa-solid fa-border-all icon"></i> Dashboard
            </a>
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
            <div class="menu-group active">
                <div class="menu-header" onclick="toggleDropdown(this)">
                    <i class="fa-solid fa-briefcase icon"></i> Manajemen
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </div>
                <div class="submenu">
                    <a href="daftarUser.php">User</a>
                    <a href="daftarRole.php">Role</a>
                    <a href="daftarMitra.php" class="sub-active">Mitra</a>
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
        <header class="top-header">
            <p class="welcome-text">Manajemen</p>
            <h1 class="page-header-title">Edit Mitra</h1>
        </header>

        <div class="back-link-container">
            <a href="daftarMitra.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error" style="max-width: 100%; margin-bottom: 20px;"><?= $error_msg; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success" style="max-width: 100%; margin-bottom: 20px;"><?= $success_msg; ?></div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . urlencode($kode_mitra); ?>" method="POST" enctype="multipart/form-data">
            <!-- 1. DATA MITRA -->
            <section class="form-card-container">
                <h3 class="form-section-title">Data Mitra</h3>

                <div class="form-group-custom">
                    <label for="nama_mitra">Nama Mitra</label>
                    <input type="text" id="nama_mitra" name="nama_mitra" value="<?= htmlspecialchars($mitra['nama_mitra'] ?? ''); ?>" placeholder="Masukkan nama mitra" required autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="id_mitra">ID Mitra</label>
                    <input type="text" id="id_mitra" name="id_mitra" value="<?= htmlspecialchars($mitra['kode_mitra'] ?? ''); ?>" readonly class="input-readonly">
                </div>

                <div class="form-group-custom">
                    <label for="kategori_mitra">Kategori Mitra</label>
                    <div class="select-wrapper">
                        <select id="kategori_mitra" name="kategori_mitra" required>
                            <?php $kat = $mitra['kategori_mitra'] ?? ''; ?>
                            <option value="Transportasi" <?= $kat === 'Transportasi' ? 'selected' : ''; ?>>Transportasi</option>
                            <option value="Wisata & Rekreasi" <?= $kat === 'Wisata & Rekreasi' ? 'selected' : ''; ?>>Wisata & Rekreasi</option>
                            <option value="Retail & F&B" <?= $kat === 'Retail & F&B' ? 'selected' : ''; ?>>Retail & F&B</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="lokasi">Lokasi</label>
                    <input type="text" id="lokasi" name="lokasi" value="<?= htmlspecialchars($mitra['alamat'] ?? ''); ?>" placeholder="Masukkan lokasi" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="no_telp">Nomor Telepon</label>
                    <input type="text" id="no_telp" name="no_telp" value="<?= htmlspecialchars($mitra['telepon_mitra'] ?? ''); ?>" placeholder="Masukkan nomor telepon" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($mitra['email_mitra'] ?? ''); ?>" placeholder="Masukkan email" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="masa_kontrak">Masa Kontrak</label>
                    <input type="text" id="masa_kontrak" name="masa_kontrak" value="<?= htmlspecialchars($mitra['masa_kontrak'] ?? ''); ?>" placeholder="Contoh: 12/01/2025 - 12/01/2026" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="status_mitra">Status Mitra</label>
                    <div class="select-wrapper">
                        <select id="status_mitra" name="status_mitra">
                            <?php $st_mitra = $mitra['status_mitra'] ?? 'Aktif'; ?>
                            <option value="Aktif" <?= $st_mitra === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="Pending" <?= $st_mitra === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Nonaktif" <?= $st_mitra === 'Nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="status_akun">Status Akun</label>
                    <div class="select-wrapper">
                        <select id="status_akun" name="status_akun">
                            <?php $st_akun = $mitra['status_akun'] ?? 'Invitation'; ?>
                            <option value="Accept" <?= $st_akun === 'Accept' ? 'selected' : ''; ?>>Accept</option>
                            <option value="Invitation" <?= $st_akun === 'Invitation' ? 'selected' : ''; ?>>Invitation</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="logo">Logo</label>
                    <div class="file-upload-box">
                        <input type="file" id="logo" name="logo" class="file-input" accept="image/*" onchange="document.getElementById('logoFileName').textContent = this.files[0] ? this.files[0].name : ''">
                        <button type="button" class="btn-upload-fake" onclick="document.getElementById('logo').click()">Pilih Gambar</button>
                        <span id="logoFileName" style="font-size: 12px; color: #64748b; margin-left: 10px;">
                            <?= !empty($mitra['logo_mitra']) ? htmlspecialchars($mitra['logo_mitra']) : ''; ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- 2. DATA PIC -->
            <section class="form-card-container">
                <h3 class="form-section-title">Data PIC</h3>

                <div class="form-group-custom">
                    <label for="nama_pic">Nama PIC</label>
                    <input type="text" id="nama_pic" name="nama_pic" value="<?= htmlspecialchars($mitra['nama_pic'] ?? ''); ?>" placeholder="Masukkan nama PIC" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="jabatan">Jabatan</label>
                    <input type="text" id="jabatan" name="jabatan" value="<?= htmlspecialchars($mitra['jabatan_pic'] ?? ''); ?>" placeholder="Masukkan jabatan" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="email_pic">Email PIC</label>
                    <input type="email" id="email_pic" name="email_pic" value="<?= htmlspecialchars($mitra['email_pic'] ?? ''); ?>" placeholder="Masukkan email PIC" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="telp_pic">Nomor Telepon</label>
                    <input type="text" id="telp_pic" name="telp_pic" value="<?= htmlspecialchars($mitra['telepon_pic'] ?? ''); ?>" placeholder="Masukkan nomor telepon PIC" autocomplete="off">
                </div>
            </section>

            <!-- 3. DATA LEGAL -->
            <section class="form-card-container">
                <h3 class="form-section-title">Data Legal</h3>

                <div class="form-group-custom">
                    <label for="npwp">NPWP</label>
                    <input type="text" id="npwp" name="npwp" value="<?= htmlspecialchars($mitra['npwp'] ?? ''); ?>" placeholder="Masukkan NPWP" autocomplete="off">
                </div>

                <div class="form-group-custom">
                    <label for="nib">NIB / Izin Usaha</label>
                    <input type="text" id="nib" name="nib" value="<?= htmlspecialchars($mitra['nib'] ?? ''); ?>" placeholder="Masukkan NIB / Izin Usaha" autocomplete="off">
                </div>
            </section>

            <!-- Tombol Action -->
            <div class="form-footer-buttons" style="margin-top: 25px; margin-bottom: 40px;">
                <a href="daftarMitra.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Simpan Perubahan</button>
            </div>
        </form>
    </main>
</div>

<script>
function toggleDropdown(element) {
    element.parentElement.classList.toggle('active');
}
</script>
</body>
</html>