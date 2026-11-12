<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Generate Kode Mitra Otomatis
$res_count = mysqli_query($conn, "SELECT COUNT(*) AS total FROM mitra");
$row_count = mysqli_fetch_assoc($res_count);
$auto_kode = "MTR-" . str_pad(($row_count['total'] ?? 0) + 1, 3, "0", STR_PAD_LEFT);

$error_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Card 1: Data Mitra
    $kode_mitra     = mysqli_real_escape_string($conn, $_POST['kode_mitra']);
    $nama_mitra     = mysqli_real_escape_string($conn, $_POST['nama_mitra']);
    $kategori_mitra = mysqli_real_escape_string($conn, $_POST['kategori_mitra']);
    $alamat         = mysqli_real_escape_string($conn, $_POST['lokasi']);
    $telepon_mitra  = mysqli_real_escape_string($conn, $_POST['telepon_mitra']);
    $email_mitra    = mysqli_real_escape_string($conn, $_POST['email_mitra']);

    // Upload Logo Mitra
    $logo_mitra = "";
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = $kode_mitra . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
            $logo_mitra = $upload_dir . $filename;
        }
    }

    // Card 2: Data PIC
    $nama_pic    = mysqli_real_escape_string($conn, $_POST['nama_pic']);
    $jabatan_pic = mysqli_real_escape_string($conn, $_POST['jabatan_pic']);
    $email_pic   = mysqli_real_escape_string($conn, $_POST['email_pic']);
    $telepon_pic = mysqli_real_escape_string($conn, $_POST['telepon_pic']);

    // Card 3: Data Legal
    $npwp = mysqli_real_escape_string($conn, $_POST['npwp']);
    $nib  = mysqli_real_escape_string($conn, $_POST['nib']);

    $sql = "INSERT INTO mitra (
                kode_mitra, nama_mitra, kategori_mitra, alamat, telepon_mitra, email_mitra, logo_mitra,
                nama_pic, jabatan_pic, email_pic, telepon_pic, npwp, nib, status_mitra, status_akun
            ) VALUES (
                '$kode_mitra', '$nama_mitra', '$kategori_mitra', '$alamat', '$telepon_mitra', '$email_mitra', '$logo_mitra',
                '$nama_pic', '$jabatan_pic', '$email_pic', '$telepon_pic', '$npwp', '$nib', 'Pending', 'Invitation'
            )";

    if (mysqli_query($conn, $sql)) {
        header("Location: daftarMitra.php?msg=added");
        exit();
    } else {
        $error_msg = "Gagal menyimpan data mitra: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Mitra - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #004b87;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .back-link:hover { text-decoration: underline; }
        .form-card-section {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px 28px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            margin-bottom: 22px;
        }
        .form-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 20px 0;
        }
        .form-stack-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }
        .form-stack-group:last-child {
            margin-bottom: 0;
        }
        .form-stack-group label {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-stack-group input[type="text"],
        .form-stack-group input[type="email"],
        .form-stack-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #333333;
            background-color: #ffffff;
        }
        .form-stack-group input[readonly] {
            background-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }
        .file-upload-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-pilih-file {
            background-color: #f3f4f6;
            border: 1px solid #d0d7de;
            padding: 9px 16px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        .btn-pilih-file:hover {
            background-color: #e5e7eb;
        }
        .form-bottom-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
        }
    </style>
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

                <a href="logout.php" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
                </a>
            </nav>
        </aside>

        <!-- Area Konten Utama -->
        <main class="main-content">
            <header class="top-header" style="margin-bottom: 10px;">
                <p class="welcome-text">Manajemen</p>
                <h1 class="page-header-title">Tambah Mitra</h1>
            </header>

            <a href="daftarMitra.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>

            <?php if ($error_msg): ?>
                <div style="background:#ffe5e5; color:#c82333; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px; font-weight:600;">
                    <?= $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- CARD 1: DATA MITRA -->
                <section class="form-card-section">
                    <h2 class="form-card-title">Data Mitra</h2>

                    <div class="form-stack-group">
                        <label>Nama Mitra</label>
                        <input type="text" name="nama_mitra" placeholder="Masukkan nama mitra" required>
                    </div>

                    <div class="form-stack-group">
                        <label>ID Mitra</label>
                        <input type="text" name="kode_mitra" value="<?= $auto_kode; ?>" readonly>
                    </div>

                    <div class="form-stack-group">
                        <label>Kategori Mitra</label>
                        <select name="kategori_mitra" required>
                            <option value="">Pilih kategori mitra</option>
                            <option value="Transportasi">Transportasi</option>
                            <option value="Wisata & Rekreasi">Wisata & Rekreasi</option>
                            <option value="Retail & F&B">Retail & F&B</option>
                        </select>
                    </div>

                    <div class="form-stack-group">
                        <label>Lokasi</label>
                        <input type="text" name="lokasi" placeholder="Masukkan lokasi mitra" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="telepon_mitra" placeholder="Masukkan nomor telepon mitra" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Email</label>
                        <input type="email" name="email_mitra" placeholder="Masukkan email mitra" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Logo</label>
                        <div class="file-upload-box">
                            <input type="file" id="logoInput" name="logo" accept="image/*" style="display:none;" onchange="updateFileName(this)">
                            <button type="button" class="btn-pilih-file" onclick="document.getElementById('logoInput').click()">Pilih Gambar</button>
                            <span id="logoFileName" style="font-size:12px; color:#6b7280;">Belum ada file dipilih</span>
                        </div>
                    </div>
                </section>

                <!-- CARD 2: DATA PIC -->
                <section class="form-card-section">
                    <h2 class="form-card-title">Data PIC</h2>

                    <div class="form-stack-group">
                        <label>Nama PIC</label>
                        <input type="text" name="nama_pic" placeholder="Masukkan nama PIC" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Jabatan</label>
                        <input type="text" name="jabatan_pic" placeholder="Masukkan jabatan PIC" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Email PIC</label>
                        <input type="email" name="email_pic" placeholder="Masukkan email PIC" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="telepon_pic" placeholder="Masukkan nomor telepon PIC" required>
                    </div>
                </section>

                <!-- CARD 3: DATA LEGAL -->
                <section class="form-card-section">
                    <h2 class="form-card-title">Data Legal</h2>

                    <div class="form-stack-group">
                        <label>NPWP</label>
                        <input type="text" name="npwp" placeholder="Masukkan nomor NPWP" required>
                    </div>

                    <div class="form-stack-group">
                        <label>NIB / Izin Usaha</label>
                        <input type="text" name="nib" placeholder="Masukkan nomor NIB / Izin Usaha" required>
                    </div>

                    <div class="form-bottom-actions">
                        <a href="daftarMitra.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Cancel</a>
                        <button type="submit" class="btn-filter">Submit</button>
                    </div>
                </section>
            </form>
        </main>
    </div>

    <script>
        function toggleDropdown(el) {
            el.parentElement.classList.toggle('active');
        }
        function updateFileName(input) {
            const label = document.getElementById('logoFileName');
            label.innerText = input.files.length > 0 ? input.files[0].name : 'Belum ada file dipilih';
        }
    </script>
</body>
</html>