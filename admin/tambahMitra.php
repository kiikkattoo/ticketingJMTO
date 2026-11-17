<?php
session_start();
require_once '../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Generate ID Mitra berurutan 3 digit (MTR-006, dst.)
$q_last_mtr = mysqli_query($conn, "SELECT kode_mitra FROM mitra WHERE kode_mitra LIKE 'MTR-%' ORDER BY kode_mitra DESC LIMIT 1");
$num_mtr = 0;
if ($q_last_mtr && mysqli_num_rows($q_last_mtr) > 0) {
    $last_mtr = mysqli_fetch_assoc($q_last_mtr)['kode_mitra'];
    $num_mtr = (int)preg_replace('/[^0-9]/', '', $last_mtr);
}
$auto_id = "MTR-" . str_pad($num_mtr + 1, 3, '0', STR_PAD_LEFT);

$success_msg = "";
$error_msg   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_mitra     = mysqli_real_escape_string($conn, trim($_POST["nama_mitra"] ?? ""));
    $kode_mitra     = mysqli_real_escape_string($conn, trim($_POST["id_mitra"] ?? $auto_id));
    $kategori_mitra = mysqli_real_escape_string($conn, trim($_POST["kategori_mitra"] ?? ""));
    $lokasi         = mysqli_real_escape_string($conn, trim($_POST["lokasi"] ?? ""));
    $no_telp        = mysqli_real_escape_string($conn, trim($_POST["no_telp"] ?? ""));
    $email          = mysqli_real_escape_string($conn, trim($_POST["email"] ?? ""));

    // Data PIC & Legal
    $nama_pic  = mysqli_real_escape_string($conn, trim($_POST["nama_pic"] ?? ""));
    $jabatan   = mysqli_real_escape_string($conn, trim($_POST["jabatan"] ?? ""));
    $email_pic = mysqli_real_escape_string($conn, trim($_POST["email_pic"] ?? ""));
    $telp_pic  = mysqli_real_escape_string($conn, trim($_POST["telp_pic"] ?? ""));
    $npwp      = mysqli_real_escape_string($conn, trim($_POST["npwp"] ?? ""));
    $nib       = mysqli_real_escape_string($conn, trim($_POST["nib"] ?? ""));

    // Prioritas Email PIC, fallback Email Mitra
    $email_tujuan  = !empty($email_pic) ? $email_pic : $email;
    $nama_penerima = !empty($nama_pic) ? $nama_pic : $nama_mitra;

    if (empty($nama_mitra)) {
        $error_msg = "Nama Mitra wajib diisi!";
    } elseif (empty($email_tujuan)) {
        $error_msg = "Email Mitra atau Email PIC wajib diisi untuk pengiriman undangan aktivasi!";
    } else {
        // 1. Cek apakah email sudah terdaftar di tabel users
        $cek_email = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email_tujuan' LIMIT 1");
        if ($cek_email && mysqli_num_rows($cek_email) > 0) {
            $error_msg = "Gagal: Email <b>" . htmlspecialchars($email_tujuan) . "</b> sudah terdaftar pada akun pengguna lain!";
        } else {
            // Handle Upload Logo jika ada
            $logo_filename = NULL;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "../uploads/logo_mitra/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo_filename = $kode_mitra . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename);
            }

            // Deteksi kolom yang tersedia di tabel mitra
            $existing_cols = [];
            $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM mitra");
            if ($res_cols) {
                while ($col = mysqli_fetch_assoc($res_cols)) {
                    $existing_cols[] = $col['Field'];
                }
            }

            // Pemetaan kolom yang sinkron dengan struktur database mitra
            $kandidat_data = [
                'kode_mitra'     => $kode_mitra,
                'nama_mitra'     => $nama_mitra,
                'kategori_mitra' => $kategori_mitra,
                'alamat'         => $lokasi,
                'telepon_mitra'  => $no_telp,
                'email_mitra'    => $email,
                'logo_mitra'     => $logo_filename,
                'nama_pic'       => $nama_pic,
                'jabatan_pic'    => $jabatan,
                'email_pic'      => $email_pic,
                'telepon_pic'    => $telp_pic,
                'npwp'           => $npwp,
                'nib'            => $nib,
                'status_mitra'   => 'Pending'
            ];

            $cols_to_insert = [];
            $vals_to_insert = [];
            foreach ($kandidat_data as $kolom => $nilai) {
                if (in_array($kolom, $existing_cols) && $nilai !== NULL) {
                    $cols_to_insert[] = "`$kolom`";
                    $vals_to_insert[] = "'$nilai'";
                }
            }

            $sql_mitra = "INSERT INTO mitra (" . implode(", ", $cols_to_insert) . ") VALUES (" . implode(", ", $vals_to_insert) . ")";

            if (mysqli_query($conn, $sql_mitra)) {
                // 2. Ambil ID Role Mitra
                $q_role = mysqli_query($conn, "SELECT id_role FROM roles WHERE LOWER(nama_role) = 'mitra' LIMIT 1");
                $id_role_mitra = ($q_role && mysqli_num_rows($q_role) > 0) ? mysqli_fetch_assoc($q_role)['id_role'] : 'ROL-004';

                // 3. Generate kode_user berurutan 3 digit (USR-008, dst.)
                $q_last_usr = mysqli_query($conn, "SELECT kode_user FROM users WHERE kode_user LIKE 'USR-%' ORDER BY kode_user DESC LIMIT 1");
                $num_usr = 0;
                if ($q_last_usr && mysqli_num_rows($q_last_usr) > 0) {
                    $last_usr = mysqli_fetch_assoc($q_last_usr)['kode_user'];
                    $num_usr = (int)preg_replace('/[^0-9]/', '', $last_usr);
                }
                $kode_user_baru = "USR-" . str_pad($num_usr + 1, 3, '0', STR_PAD_LEFT);

                $token_aktivasi = bin2hex(random_bytes(32));
                $token_expired  = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $sql_user = "INSERT INTO users (kode_user, nama_user, email, password, id_role, id_mitra, status_user, activation_token, token_expires)
                             VALUES ('$kode_user_baru', '$nama_mitra', '$email_tujuan', '', '$id_role_mitra', '$kode_mitra', 'Inactive', '$token_aktivasi', '$token_expired')";

                if (mysqli_query($conn, $sql_user)) {
                    // 4. Kirim Email Undangan Aktivasi
                    $info_email = "";
                    if (file_exists('../mailer_helper.php')) {
                        require_once '../mailer_helper.php';
                        $kirim = kirimInvitationMitra($email_tujuan, $nama_penerima, $nama_mitra, $kode_mitra, $token_aktivasi);
                        if ($kirim['status']) {
                            $info_email = " & " . $kirim['pesan'];
                        } else {
                            $info_email = " (Catatan email: " . $kirim['pesan'] . ")";
                        }
                    }
                    $success_msg = "Mitra <b>" . htmlspecialchars($nama_mitra) . "</b> ($kode_mitra) & Akun User ($kode_user_baru) berhasil ditambahkan" . $info_email;

                    // Perbarui nomor otomatis berikutnya untuk tampilan form
                    $auto_id = "MTR-" . str_pad($num_mtr + 2, 3, '0', STR_PAD_LEFT);
                } else {
                    $error_msg = "Data mitra tersimpan, namun gagal membuat akun user: " . mysqli_error($conn);
                }
            } else {
                $error_msg = "Gagal menyimpan data mitra: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Mitra - JMTO Ticketing</title>
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
<a href="daftarVenue.php">Venue</a>
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
<h1 class="page-header-title">Tambah Mitra</h1>
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

<form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
<!-- 1. DATA MITRA -->
<section class="form-card-container">
<h3 class="form-section-title">Data Mitra</h3>
<div class="form-group-custom">
<label for="nama_mitra">Nama Mitra</label>
<input type="text" id="nama_mitra" name="nama_mitra" placeholder="Masukkan nama mitra" required autocomplete="off">
</div>
<div class="form-group-custom">
<label for="id_mitra">ID Mitra</label>
<input type="text" id="id_mitra" name="id_mitra" value="<?= $auto_id; ?>" readonly class="input-readonly">
</div>
<div class="form-group-custom">
<label for="kategori_mitra">Kategori Mitra</label>
<div class="select-wrapper">
<select id="kategori_mitra" name="kategori_mitra" required>
<option value="" disabled selected hidden>Pilih kategori mitra</option>
<option value="Transportasi">Transportasi</option>
<option value="Wisata & Rekreasi">Wisata & Rekreasi</option>
<option value="Retail & F&B">Retail & F&B</option>
</select>
</div>
</div>
<div class="form-group-custom">
<label for="lokasi">Lokasi</label>
<input type="text" id="lokasi" name="lokasi" placeholder="Masukkan lokasi" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="no_telp">Nomor Telepon</label>
<input type="text" id="no_telp" name="no_telp" placeholder="Masukkan nomor telepon" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="email">Email</label>
<input type="email" id="email" name="email" placeholder="Masukkan email" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="logo">Logo</label>
<div class="file-upload-box">
<input type="file" id="logo" name="logo" class="file-input" accept="image/*">
<button type="button" class="btn-upload-fake" onclick="document.getElementById('logo').click()">Pilih Gambar</button>
</div>
</div>
</section>

<!-- 2. DATA PIC -->
<section class="form-card-container">
<h3 class="form-section-title">Data PIC</h3>
<div class="form-group-custom">
<label for="nama_pic">Nama PIC</label>
<input type="text" id="nama_pic" name="nama_pic" placeholder="Masukkan nama PIC" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="jabatan">Jabatan</label>
<input type="text" id="jabatan" name="jabatan" placeholder="Masukkan jabatan" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="email_pic">Email PIC</label>
<input type="email" id="email_pic" name="email_pic" placeholder="Masukkan email PIC" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="telp_pic">Nomor Telepon</label>
<input type="text" id="telp_pic" name="telp_pic" placeholder="Masukkan nomor telepon PIC" autocomplete="off">
</div>
</section>

<!-- 3. DATA LEGAL -->
<section class="form-card-container">
<h3 class="form-section-title">Data Legal</h3>
<div class="form-group-custom">
<label for="npwp">NPWP</label>
<input type="text" id="npwp" name="npwp" placeholder="Masukkan NPWP" autocomplete="off">
</div>
<div class="form-group-custom">
<label for="nib">NIB / Izin Usaha</label>
<input type="text" id="nib" name="nib" placeholder="Masukkan NIB / Izin Usaha" autocomplete="off">
</div>
</section>

<!-- Tombol Action -->
<div class="form-footer-buttons" style="margin-top: 25px; margin-bottom: 40px;">
<a href="daftarMitra.php" class="btn-cancel">Cancel</a>
<button type="submit" class="btn-submit">Submit</button>
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