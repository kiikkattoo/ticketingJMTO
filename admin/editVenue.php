<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once '../koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

$id_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id_edit > 0;
$pesan_error = '';

if (!$is_edit) {
    header("Location: daftarVenue.php");
    exit();
}

// Ambil daftar mitra untuk dropdown
$mitra_list = [];
$res_mitra = mysqli_query($conn, "SELECT kode_mitra, nama_mitra, logo_mitra FROM mitra ORDER BY nama_mitra ASC");
if ($res_mitra) {
    while ($m = mysqli_fetch_assoc($res_mitra)) {
        $mitra_list[] = $m;
    }
}

// Tarik data konfigurasi venue yang mau diedit
$data_form = [
    'kode_mitra'   => '',
    'nama_tema'    => '',
    'logo_portal'  => '',
    'warna_primer' => '#004687',
    'warna_aksen'  => '#EAB308',
    'warna_bg'     => '#F8FAFC'
];

$res_edit = mysqli_query($conn, "SELECT * FROM tema_venue WHERE id_tema = $id_edit LIMIT 1");
if ($res_edit && mysqli_num_rows($res_edit) > 0) {
    $data_form = mysqli_fetch_assoc($res_edit);
} else {
    header("Location: daftarVenue.php");
    exit();
}

// Proses Update Data ke Database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_mitra   = mysqli_real_escape_string($conn, trim($_POST['kode_mitra'] ?? ''));
    $nama_tema    = mysqli_real_escape_string($conn, trim($_POST['nama_tema'] ?? ''));
    $warna_primer = mysqli_real_escape_string($conn, trim($_POST['warna_primer'] ?? '#004687'));
    $warna_aksen  = mysqli_real_escape_string($conn, trim($_POST['warna_aksen'] ?? '#EAB308'));
    $warna_bg     = mysqli_real_escape_string($conn, trim($_POST['warna_bg'] ?? '#F8FAFC'));

    if ($kode_mitra === '' || $nama_tema === '') {
        $pesan_error = 'Nama Mitra dan Nama Venue wajib diisi.';
    } else {
        $logo_final = $_POST['logo_existing'] ?? '';
        
        // Handle upload logo baru jika ada
        if (!empty($_FILES['logo_portal']['name']) && $_FILES['logo_portal']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../img/venue/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo_portal']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])) {
                $new_name = 'logo_' . strtolower($kode_mitra) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo_portal']['tmp_name'], $upload_dir . $new_name)) {
                    $logo_final = 'img/venue/' . $new_name;
                }
            }
        }

        $logo_esc = mysqli_real_escape_string($conn, $logo_final);

        $sql_update = "UPDATE tema_venue SET
                        kode_mitra   = '$kode_mitra',
                        nama_tema    = '$nama_tema',
                        logo_portal  = '$logo_esc',
                        warna_primer = '$warna_primer',
                        warna_aksen  = '$warna_aksen',
                        warna_bg     = '$warna_bg'
                     WHERE id_tema = $id_edit";

        if (mysqli_query($conn, $sql_update)) {
            header("Location: daftarVenue.php?status=updated");
            exit();
        } else {
            $pesan_error = 'Gagal memperbarui data: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .venue-form-container {
            max-width: 1300px;
            margin: 0 auto;
        }
        .top-select-wrap {
            margin-bottom: 24px;
        }
        .top-select-wrap label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .top-select-wrap select {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
        }

        /* Layout 2 Kolom Seimbang */
        .venue-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .venue-two-col { grid-template-columns: 1fr; }
        }

        .left-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .ui-card-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 24px 26px;
        }
        .ui-card-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 18px 0;
        }

        .form-field {
            margin-bottom: 16px;
        }
        .form-field:last-child { margin-bottom: 0; }
        .form-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-field input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #1e293b;
        }
        .form-field input[type="text"]:focus {
            outline: none;
            border-color: #004687;
        }

        .upload-btn-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-pilih-gambar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 18px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-pilih-gambar:hover { background: #e2e8f0; }
        .file-name-hint {
            font-size: 11px;
            color: #64748b;
        }

        .picker-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .picker-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            padding: 5px 8px;
            background: #ffffff;
        }
        .picker-pill input[type="color"] {
            width: 22px;
            height: 22px;
            border: none;
            padding: 0;
            background: transparent;
            cursor: pointer;
        }
        .picker-pill span {
            font-size: 11px;
            color: #64748b;
        }

        /* ===== KOTAK KANAN: PREVIEW PORTAL MITRA ===== */
        .preview-card-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 24px 26px;
            position: sticky;
            top: 20px;
        }
        .mini-portal {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 150px 1fr;
            min-height: 440px;
        }
        .mini-sidebar {
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 16px 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .mini-logo-box {
            width: 48px;
            height: 48px;
            margin: 0 auto 14px auto;
            border: 1px dashed #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            background: #fff;
            overflow: hidden;
        }
        .mini-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .mini-nav {
            font-size: 11px;
            padding: 8px 10px;
            border-radius: 6px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .mini-nav.active {
            background: #004687;
            color: #ffffff;
            font-weight: 600;
        }

        .mini-body {
            padding: 18px;
            background: #f8fafc;
            transition: background-color 0.2s;
        }
        .mini-sub { font-size: 10px; color: #64748b; margin: 0; }
        .mini-heading { font-size: 14px; font-weight: 700; color: #0f172a; margin: 2px 0 12px 0; }

        .mini-kpi-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .mini-kpi-card {
            background: #004687;
            color: #ffffff;
            border-radius: 9px;
            padding: 11px 12px;
        }
        .mini-kpi-card small { font-size: 10px; opacity: 0.9; display: block; }
        .mini-kpi-card strong { font-size: 14px; font-weight: 700; display: block; margin-top: 2px; }

        .mini-chart-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 44px;
            margin-top: 8px;
        }
        .mini-bar {
            flex: 1;
            background: #EAB308;
            border-radius: 4px 4px 0 0;
        }

        /* Tombol Aksi Bawah */
        .bottom-action-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
        }
        .btn-cancel-outline {
            padding: 10px 26px;
            border: 1px solid #94a3b8;
            background: #ffffff;
            color: #1e293b;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-submit-primary {
            padding: 10px 28px;
            border: none;
            background: #004687;
            color: #ffffff;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit-primary:hover { background: #003366; }

        .alert-err {
            background: #fee2e2;
            color: #991b1b;
            padding: 11px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
            <div class="menu-group">
                <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
                <div class="submenu">
                    <a href="detailTransaksi.php">Analisis Transaksi</a>
                    <a href="daftarTransaksi.php">Daftar Transaksi</a>
                    <a href="rekapTransaksi.php">Rekap Transaksi</a>
                </div>
            </div>
            <div class="menu-group">
                <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-desktop icon"></i> Monitoring <i class="fa-solid fa-chevron-down arrow"></i></div>
                <div class="submenu"><a href="cctv.php">CCTV</a></div>
            </div>
            <div class="menu-group active">
                <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-briefcase icon"></i> Manajemen <i class="fa-solid fa-chevron-down arrow"></i></div>
                <div class="submenu">
                    <a href="daftarUser.php">User</a>
                    <a href="daftarRole.php">Role</a>
                    <a href="daftarMitra.php">Mitra</a>
                    <a href="daftarPerangkat.php">Perangkat</a>
                    <a href="daftarVenue.php" class="active-sub">Venue</a>
                </div>
            </div>
            <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <p class="welcome-text">Manajemen</p>
            <h1 class="page-header-title">Edit Venue</h1>
        </header>

        <?php if ($pesan_error !== ''): ?>
            <div class="alert-err"><?= htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="venue-form-container">
            <input type="hidden" name="logo_existing" value="<?= htmlspecialchars($data_form['logo_portal']); ?>">

            <div class="top-select-wrap">
                <label for="selectMitra">Nama Mitra</label>
                <select name="kode_mitra" id="selectMitra" required onchange="onMitraSelect()">
                    <option value="">Pilih nama mitra</option>
                    <?php foreach ($mitra_list as $m): ?>
                        <option value="<?= htmlspecialchars($m['kode_mitra']); ?>"
                                data-nama="<?= htmlspecialchars($m['nama_mitra']); ?>"
                                data-logo="<?= !empty($m['logo_mitra']) ? '../' . htmlspecialchars($m['logo_mitra']) : ''; ?>"
                                <?= ($data_form['kode_mitra'] === $m['kode_mitra']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($m['nama_mitra']); ?> (<?= htmlspecialchars($m['kode_mitra']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grid 2 Kolom Seimbang -->
            <div class="venue-two-col">
                <!-- KOLOM KIRI (Form Input) -->
                <div class="left-stack">
                    <div class="ui-card-box">
                        <h3 class="ui-card-title">Identitas Visual Portal:</h3>

                        <div class="form-field">
                            <label for="inputNamaVenue">Nama Venue</label>
                            <input type="text" name="nama_tema" id="inputNamaVenue" required
                                   value="<?= htmlspecialchars($data_form['nama_tema']); ?>"
                                   oninput="refreshPreview()">
                        </div>

                        <div class="form-field">
                            <label>Upload Logo Sidebar</label>
                            <div class="upload-btn-wrap">
                                <label for="inputLogoFile" class="btn-pilih-gambar">Pilih Gambar</label>
                                <input type="file" name="logo_portal" id="inputLogoFile" accept="image/*" style="display:none;" onchange="onLogoPicked(this)">
                                <span class="file-name-hint" id="logoFileName">
                                    <?= !empty($data_form['logo_portal']) ? basename($data_form['logo_portal']) : 'Default mengikuti logo mitra'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Color Picker</label>
                            <div class="picker-grid">
                                <div class="picker-pill">
                                    <input type="color" name="warna_primer" id="colorPrimer" value="<?= htmlspecialchars($data_form['warna_primer']); ?>" oninput="refreshPreview()">
                                    <span>Warna Primer</span>
                                </div>
                                <div class="picker-pill">
                                    <input type="color" name="warna_aksen" id="colorAksen" value="<?= htmlspecialchars($data_form['warna_aksen']); ?>" oninput="refreshPreview()">
                                    <span>Warna Aksen</span>
                                </div>
                                <div class="picker-pill">
                                    <input type="color" name="warna_bg" id="colorBg" value="<?= htmlspecialchars($data_form['warna_bg']); ?>" oninput="refreshPreview()">
                                    <span>Latar</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KOLOM KANAN (Live Preview Portal Mitra) -->
                <div class="preview-card-box">
                    <h3 class="ui-card-title">Preview Portal Mitra</h3>

                    <div class="mini-portal">
                        <div class="mini-sidebar">
                            <div class="mini-logo-box">
                                <img id="prevLogoImg"
                                     src="<?= !empty($data_form['logo_portal']) ? '../' . htmlspecialchars($data_form['logo_portal']) : '../img/jasamarga-logo.png'; ?>"
                                     alt="Logo Venue">
                            </div>
                            <div class="mini-nav active" id="prevNavActive"><i class="fa-solid fa-border-all"></i> Dashboard</div>
                            <div class="mini-nav"><i class="fa-regular fa-file-lines"></i> Laporan</div>
                            <div class="mini-nav"><i class="fa-solid fa-video"></i> CCTV</div>
                        </div>

                        <div class="mini-body" id="prevPortalBody" style="background: <?= htmlspecialchars($data_form['warna_bg']); ?>;">
                            <p class="mini-sub" id="prevMitraGreeting">Selamat Datang, Mitra!</p>
                            <h4 class="mini-heading" id="prevPortalTitle"><?= htmlspecialchars($data_form['nama_tema']); ?></h4>

                            <div class="mini-kpi-row">
                                <div class="mini-kpi-card prev-primer">
                                    <small>Total Transaksi</small>
                                    <strong>1.248</strong>
                                </div>
                                <div class="mini-kpi-card prev-primer">
                                    <small>Pendapatan</small>
                                    <strong>Rp 48.720.000</strong>
                                </div>
                            </div>

                            <div class="mini-chart-panel">
                                <div style="font-size:11px;font-weight:600;color:#334155;">Traffic Pengunjung</div>
                                <div class="mini-bars">
                                    <div class="mini-bar prev-aksen" style="height:45%;"></div>
                                    <div class="mini-bar prev-aksen" style="height:80%;"></div>
                                    <div class="mini-bar prev-aksen" style="height:100%;"></div>
                                    <div class="mini-bar prev-aksen" style="height:60%;"></div>
                                    <div class="mini-bar prev-aksen" style="height:85%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Cancel & Submit di Bawah Kanan -->
            <div class="bottom-action-row">
                <a href="daftarVenue.php" class="btn-cancel-outline">Cancel</a>
                <button type="submit" class="btn-submit-primary">Update Changes</button>
            </div>
        </form>
    </main>
</div>

<script>
function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }

function onMitraSelect() {
    const sel = document.getElementById('selectMitra');
    const opt = sel.options[sel.selectedIndex];
    const nama = opt.getAttribute('data-nama') || '';
    const logo = opt.getAttribute('data-logo') || '';

    const namaVenueInp = document.getElementById('inputNamaVenue');
    if (nama && namaVenueInp.value === '') {
        namaVenueInp.value = 'Portal ' + nama;
    }

    document.getElementById('prevLogoImg').src = logo ? logo : '../img/jasamarga-logo.png';
    document.getElementById('prevMitraGreeting').innerText = nama ? ('Selamat Datang, ' + nama + '!') : 'Selamat Datang, Mitra!';
    refreshPreview();
}

function onLogoPicked(input) {
    if (input.files && input.files[0]) {
        document.getElementById('logoFileName').innerText = input.files[0].name;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('prevLogoImg').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function refreshPreview() {
    const primer = document.getElementById('colorPrimer').value;
    const aksen  = document.getElementById('colorAksen').value;
    const bg     = document.getElementById('colorBg').value;
    const judul  = document.getElementById('inputNamaVenue').value;

    document.getElementById('prevPortalTitle').innerText = judul || 'Dashboard Mitra';
    document.getElementById('prevPortalBody').style.backgroundColor = bg;
    document.getElementById('prevNavActive').style.backgroundColor = primer;
    document.querySelectorAll('.prev-primer').forEach(el => el.style.backgroundColor = primer);
    document.querySelectorAll('.prev-aksen').forEach(el => el.style.backgroundColor = aksen);
}

refreshPreview();
</script>
</body>
</html>