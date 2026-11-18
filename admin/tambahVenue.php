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

// Ambil daftar mitra untuk dropdown
$mitra_list = [];
$res_mitra = mysqli_query($conn, "SELECT kode_mitra, nama_mitra, logo_mitra FROM mitra ORDER BY nama_mitra ASC");
if ($res_mitra) {
    while ($m = mysqli_fetch_assoc($res_mitra)) {
        $mitra_list[] = $m;
    }
}

// Default data form
$data_form = [
    'kode_mitra'   => '',
    'nama_tema'    => '',
    'logo_portal'  => '',
    'warna_primer' => '#004687',
    'warna_aksen'  => '#EAB308',
    'warna_bg'     => '#F8FAFC',
    'show_revenue' => 1,
    'multi_tiket'  => 0
];

// Jika Mode Edit, tarik data konfigurasi
if ($is_edit) {
    $res_edit = mysqli_query($conn, "SELECT * FROM tema_venue WHERE id_tema = $id_edit LIMIT 1");
    if ($res_edit && mysqli_num_rows($res_edit) > 0) {
        $data_form = mysqli_fetch_assoc($res_edit);
    } else {
        header("Location: daftarVenue.php");
        exit();
    }
}

// Proses Simpan Form
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
        if ($logo_final === '') {
            foreach ($mitra_list as $m) {
                if ($m['kode_mitra'] === $kode_mitra && !empty($m['logo_mitra'])) {
                    $logo_final = $m['logo_mitra'];
                    break;
                }
            }
        }

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

        if ($is_edit) {
            $sql_save = "UPDATE tema_venue SET
                            kode_mitra   = '$kode_mitra',
                            nama_tema    = '$nama_tema',
                            logo_portal  = '$logo_esc',
                            warna_primer = '$warna_primer',
                            warna_aksen  = '$warna_aksen',
                            warna_bg     = '$warna_bg'
                         WHERE id_tema = $id_edit";
        } else {
            $sql_save = "INSERT INTO tema_venue
                            (kode_mitra, nama_tema, logo_portal, warna_primer, warna_aksen, warna_bg, show_revenue, multi_tiket)
                         VALUES
                            ('$kode_mitra', '$nama_tema', '$logo_esc', '$warna_primer', '$warna_aksen', '$warna_bg', 1, 0)
                         ON DUPLICATE KEY UPDATE
                            nama_tema    = VALUES(nama_tema),
                            logo_portal  = VALUES(logo_portal),
                            warna_primer = VALUES(warna_primer),
                            warna_aksen  = VALUES(warna_aksen),
                            warna_bg     = VALUES(warna_bg)";
        }

        if (mysqli_query($conn, $sql_save)) {
            header("Location: daftarVenue.php?status=sukses");
            exit();
        } else {
            $pesan_error = 'Gagal menyimpan data: ' . mysqli_error($conn);
        }
    }
}

// Sumber gambar logo awal (untuk thumbnail upload & preview)
$logo_awal = !empty($data_form['logo_portal']) ? '../' . htmlspecialchars($data_form['logo_portal']) : '../img/jasamarga-logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Tambah'; ?> Venue - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --vf-ink: #0f172a;
            --vf-text: #334155;
            --vf-muted: #64748b;
            --vf-line: #e5eaf1;
            --vf-line-strong: #cbd5e1;
            --vf-brand: #004687;
            --vf-brand-dark: #003366;
            --vf-soft: #f1f5f9;
        }

        .venue-form-container {
            max-width: 1300px;
            margin: 0 auto;
            padding-bottom: 24px;
        }

        /* ===== Kartu dasar ===== */
        .vf-card {
            background: #ffffff;
            border: 1px solid var(--vf-line);
            border-radius: 18px;
            padding: 24px 26px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
        }
        .vf-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
                .vf-card-head h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--vf-ink);
            line-height: 1.25;
        }
        .vf-card-head p {
            margin: 2px 0 0 0;
            font-size: 11.5px;
            color: var(--vf-muted);
        }

        /* ===== Kartu pilih mitra ===== */
        .select-card { margin-bottom: 24px; }
        .select-wrap { position: relative; }
        .select-wrap select {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 16px;
            border: 1px solid var(--vf-line-strong);
            border-radius: 12px;
            background: #f8fafc;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: var(--vf-text);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .select-wrap select:hover { background: #ffffff; }
        .select-wrap select:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--vf-brand);
            box-shadow: 0 0 0 4px rgba(0, 70, 135, 0.10);
        }
        .select-wrap .select-chevron {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--vf-muted);
            font-size: 12px;
            pointer-events: none;
        }

        /* ===== Layout 2 kolom ===== */
        .venue-two-col {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
            gap: 28px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .venue-two-col { grid-template-columns: 1fr; }
        }
        .left-stack { display: flex; flex-direction: column; gap: 20px; }

        /* ===== Field ===== */
        .form-field { margin-bottom: 22px; }
        .form-field:last-child { margin-bottom: 0; }
        .form-field > label.field-label,
        .form-field .field-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--vf-ink);
            margin-bottom: 8px;
        }
        .field-help {
            font-size: 11px;
            color: var(--vf-muted);
            margin: 7px 0 0 0;
        }
        .input-icon-wrap { position: relative; }
        .form-field input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            height: 44px;
            padding: 0 14px;
            border: 1px solid var(--vf-line-strong);
            border-radius: 10px;
            background: #f8fafc;
            font-family: 'Poppins', sans-serif;
            font-size: 12.5px;
            color: var(--vf-ink);
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-field input[type="text"]::placeholder { color: #94a3b8; }
        .form-field input[type="text"]:hover { background: #ffffff; }
        .form-field input[type="text"]:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--vf-brand);
            box-shadow: 0 0 0 4px rgba(0, 70, 135, 0.10);
        }

        /* ===== Upload logo ===== */
        .dropzone {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border: 1.5px dashed var(--vf-line-strong);
            border-radius: 12px;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .dropzone:hover { border-color: var(--vf-brand); background: #f1f6fc; }
        .dropzone-thumb {
            flex: 0 0 60px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid var(--vf-line);
            padding: 4px;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dropzone-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .dropzone-info { flex: 1; min-width: 0; }
        .dropzone-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--vf-brand);
        }
        .file-name-hint {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            color: var(--vf-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dropzone-types { font-size: 10.5px; color: #94a3b8; margin-top: 2px; display: block; }

        /* ===== Color picker ===== */
        .picker-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 640px) { .picker-grid { grid-template-columns: 1fr; } }
        .picker-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--vf-line);
            border-radius: 12px;
            padding: 10px 12px;
            background: #ffffff;
            transition: border-color .2s, box-shadow .2s;
        }
        .picker-pill:hover { border-color: var(--vf-line-strong); box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06); }
        .swatch {
            position: relative;
            flex: 0 0 38px;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.12);
            cursor: pointer;
        }
        .swatch input[type="color"] {
            position: absolute;
            top: -6px; left: -6px;
            width: 60px; height: 60px;
            border: none;
            padding: 0;
            background: transparent;
            cursor: pointer;
        }
        .picker-text { display: flex; flex-direction: column; min-width: 0; }
        .picker-text .p-name { font-size: 11.5px; font-weight: 600; color: var(--vf-ink); }
        .picker-text .p-hex {
            font-size: 10.5px;
            color: var(--vf-muted);
            letter-spacing: 0.4px;
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        }

        .contrast-hint {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 11.5px;
            line-height: 1.45;
        }
        .contrast-hint.ok   { background: #ecfdf5; color: #047857; }
        .contrast-hint.warn { background: #fffbeb; color: #b45309; }

        /* ===== Kotak kanan: preview ===== */
        .preview-card-box { position: sticky; top: 20px; padding-bottom: 22px; }
        .live-tag {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 99px;
            background: #ecfdf5;
            color: #047857;
            font-size: 10.5px;
            font-weight: 600;
        }
        .live-tag::before {
            content: "";
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #10b981;
            animation: livePulse 1.6s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .45; transform: scale(.8); }
        }

        .browser-frame {
            border: 1px solid var(--vf-line-strong);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.10);
            background: #ffffff;
        }
        .browser-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 9px 12px;
            background: #f1f5f9;
            border-bottom: 1px solid var(--vf-line);
        }
        .browser-bar .dot { width: 9px; height: 9px; border-radius: 50%; }
        .browser-bar .dot:nth-child(1) { background: #fb7185; }
        .browser-bar .dot:nth-child(2) { background: #fbbf24; }
        .browser-bar .dot:nth-child(3) { background: #34d399; }
        .browser-url {
            margin-left: 10px;
            flex: 1;
            background: #ffffff;
            border-radius: 99px;
            padding: 3px 12px;
            font-size: 10px;
            color: var(--vf-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-portal {
            --primer: #004687;
            --aksen:  #EAB308;
            --bg:     #F8FAFC;
            display: grid;
            grid-template-columns: 148px 1fr;
            min-height: 420px;
        }
        .mini-sidebar {
            background: #ffffff;
            border-right: 1px solid var(--vf-line);
            padding: 18px 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .mini-logo-box {
            width: 50px;
            height: 50px;
            margin: 0 auto 16px auto;
            border: 1px dashed var(--vf-line-strong);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            background: #fff;
            overflow: hidden;
            box-sizing: border-box;
        }
        .mini-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .mini-nav {
            font-size: 11px;
            padding: 9px 10px;
            border-radius: 8px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mini-nav i { width: 13px; text-align: center; font-size: 11px; }
        .mini-nav.active {
            background: var(--primer);
            color: #ffffff;
            font-weight: 600;
            transition: background-color .2s;
        }
        .mini-nav .chev { margin-left: auto; font-size: 8px; opacity: .6; width: auto; }

        .mini-body {
            padding: 20px;
            background: var(--bg);
            transition: background-color .2s;
        }
        .mini-sub { font-size: 10px; color: var(--vf-muted); margin: 0; }
        .mini-heading { font-size: 15px; font-weight: 700; color: var(--vf-ink); margin: 2px 0 14px 0; }

        .mini-kpi-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .mini-kpi-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--primer) 0%, var(--primer) 55%, rgba(255,255,255,0.16) 160%);
            background-color: var(--primer);
            color: #ffffff;
            border-radius: 12px;
            padding: 13px 12px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.14);
            transition: background-color .2s;
        }
        .mini-kpi-card::after {
            content: "";
            position: absolute;
            right: -16px; top: -16px;
            width: 54px; height: 54px;
            border-radius: 50%;
            background: rgba(255,255,255,0.10);
        }
        .mini-kpi-ic {
            flex: 0 0 28px;
            width: 28px; height: 28px;
            border-radius: 8px;
            background: rgba(255,255,255,0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .mini-kpi-card small { font-size: 9.5px; opacity: 0.9; display: block; text-transform: uppercase; letter-spacing: .4px; }
        .mini-kpi-card strong { font-size: 13px; font-weight: 700; display: block; margin-top: 1px; white-space: nowrap; }

        .mini-chart-panel {
            background: #ffffff;
            border: 1px solid var(--vf-line);
            border-radius: 12px;
            padding: 12px 14px 14px;
        }
        .mini-chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            color: var(--vf-text);
        }
        .mini-toggle {
            display: inline-flex;
            gap: 2px;
            padding: 2px;
            background: var(--vf-soft);
            border-radius: 6px;
        }
        .mini-toggle span { font-size: 8.5px; padding: 3px 7px; border-radius: 4px; color: var(--vf-muted); font-weight: 500; }
        .mini-toggle span.on { background: var(--primer); color: #ffffff; font-weight: 600; transition: background-color .2s; }
        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 70px;
            margin-top: 12px;
            padding-top: 4px;
            border-bottom: 1px solid var(--vf-line);
        }
        .mini-bar {
            flex: 1;
            background: var(--aksen);
            border-radius: 4px 4px 0 0;
            transition: background-color .2s;
        }
        .mini-bar.top { filter: brightness(0.78); }

        /* ===== Tombol aksi ===== */
        .bottom-action-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
        }
        .btn-cancel-outline,
        .btn-submit-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 44px;
            padding: 0 26px;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform .15s, box-shadow .2s, background .2s;
        }
        .btn-cancel-outline { border: 1px solid var(--vf-line-strong); background: #ffffff; color: var(--vf-text); }
        .btn-cancel-outline:hover { background: var(--vf-soft); }
        .btn-submit-primary { border: none; background: var(--vf-brand); color: #ffffff; box-shadow: 0 6px 14px rgba(0, 70, 135, 0.25); }
        .btn-submit-primary:hover { background: var(--vf-brand-dark); transform: translateY(-1px); box-shadow: 0 10px 20px rgba(0, 70, 135, 0.32); }

        .alert-err {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
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
            <h1 class="page-header-title"><?= $is_edit ? 'Edit' : 'Tambah'; ?> Venue</h1>
        </header>

        <?php if ($pesan_error !== ''): ?>
            <div class="alert-err"><?= htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="venue-form-container">
            <input type="hidden" name="logo_existing" value="<?= htmlspecialchars($data_form['logo_portal']); ?>">

            <!-- Langkah 1: pilih mitra -->
            <section class="vf-card select-card">
                <div class="vf-card-head">
                    <div>
                        <h3>Nama Mitra</h3>
                        <p>Pilih mitra yang tampilan portalnya ingin diatur.</p>
                    </div>
                </div>
                <div class="select-wrap">
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
                    <i class="fa-solid fa-chevron-down select-chevron"></i>
                </div>
            </section>

            <!-- Grid 2 Kolom -->
            <div class="venue-two-col">
                <!-- KOLOM KIRI (Form Input) -->
                <div class="left-stack">
                    <section class="vf-card">
                        <div class="vf-card-head">
                            <div>
                                <h3>Identitas Visual Portal</h3>
                                <p>Nama, logo, dan warna yang dipakai di portal mitra.</p>
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="inputNamaVenue" class="field-label">Nama Venue</label>
                            <div class="input-icon-wrap">
                                <input type="text" name="nama_tema" id="inputNamaVenue" required
                                       placeholder="Masukkan nama tema / venue (cth: Portal TMII)"
                                       value="<?= htmlspecialchars($data_form['nama_tema']); ?>"
                                       oninput="refreshPreview()">
                            </div>
                        </div>

                        <div class="form-field">
                            <span class="field-label">Upload Logo Sidebar</span>
                            <label for="inputLogoFile" class="dropzone">
                                <span class="dropzone-thumb"><img id="logoThumb" src="<?= $logo_awal; ?>" alt="Logo"></span>
                                <span class="dropzone-info">
                                    <span class="dropzone-title">Pilih Gambar</span>
                                    <span class="file-name-hint" id="logoFileName"><?= !empty($data_form['logo_portal']) ? htmlspecialchars(basename($data_form['logo_portal'])) : 'Default mengikuti logo mitra'; ?></span>
                                    <span class="dropzone-types">PNG, JPG, WEBP, atau SVG</span>
                                </span>
                            </label>
                            <input type="file" name="logo_portal" id="inputLogoFile" accept="image/*" style="display:none;" onchange="onLogoPicked(this)">
                        </div>

                        <div class="form-field">
                            <span class="field-label">Color Picker</span>
                            <div class="picker-grid">
                                <div class="picker-pill">
                                    <span class="swatch" id="swatchPrimer" style="background: <?= htmlspecialchars($data_form['warna_primer']); ?>;">
                                        <input type="color" name="warna_primer" id="colorPrimer" value="<?= htmlspecialchars($data_form['warna_primer']); ?>" oninput="refreshPreview()">
                                    </span>
                                    <span class="picker-text"><span class="p-name">Warna Primer</span><span class="p-hex" id="hexPrimer"></span></span>
                                </div>
                                <div class="picker-pill">
                                    <span class="swatch" id="swatchAksen" style="background: <?= htmlspecialchars($data_form['warna_aksen']); ?>;">
                                        <input type="color" name="warna_aksen" id="colorAksen" value="<?= htmlspecialchars($data_form['warna_aksen']); ?>" oninput="refreshPreview()">
                                    </span>
                                    <span class="picker-text"><span class="p-name">Warna Aksen</span><span class="p-hex" id="hexAksen"></span></span>
                                </div>
                                <div class="picker-pill">
                                    <span class="swatch" id="swatchBg" style="background: <?= htmlspecialchars($data_form['warna_bg']); ?>;">
                                        <input type="color" name="warna_bg" id="colorBg" value="<?= htmlspecialchars($data_form['warna_bg']); ?>" oninput="refreshPreview()">
                                    </span>
                                    <span class="picker-text"><span class="p-name">Latar</span><span class="p-hex" id="hexBg"></span></span>
                                </div>
                            </div>
                            <div class="contrast-hint ok" id="contrastHint"></div>
                        </div>
                    </section>
                </div>

                <!-- KOLOM KANAN (Live Preview Portal Mitra) -->
                <section class="vf-card preview-card-box">
                    <div class="vf-card-head">
                        <div>
                            <h3>Preview Portal Mitra</h3>
                            <p>Tampilan akan berubah saat kamu mengisi form.</p>
                        </div>
                        <span class="live-tag">Live</span>
                    </div>

                    <div class="browser-frame">
                        <div class="browser-bar">
                            <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                            <span class="browser-url">localhost/ticketingjmto/mitra/dashboard.php</span>
                        </div>

                        <div class="mini-portal" id="miniPortal"
                             style="--primer: <?= htmlspecialchars($data_form['warna_primer']); ?>; --aksen: <?= htmlspecialchars($data_form['warna_aksen']); ?>; --bg: <?= htmlspecialchars($data_form['warna_bg']); ?>;">
                            <div class="mini-sidebar">
                                <div class="mini-logo-box">
                                    <img id="prevLogoImg" src="<?= $logo_awal; ?>" alt="Logo Venue">
                                </div>
                                <div class="mini-nav active"><i class="fa-solid fa-border-all"></i> Dashboard</div>
                                <div class="mini-nav"><i class="fa-regular fa-file-lines"></i> Laporan <i class="fa-solid fa-chevron-down chev"></i></div>
                                <div class="mini-nav"><i class="fa-solid fa-desktop"></i> Monitoring <i class="fa-solid fa-chevron-down chev"></i></div>
                            </div>

                            <div class="mini-body" id="prevPortalBody">
                                <p class="mini-sub" id="prevMitraGreeting">Selamat Datang, Mitra!</p>
                                <h4 class="mini-heading" id="prevPortalTitle"><?= $data_form['nama_tema'] !== '' ? htmlspecialchars($data_form['nama_tema']) : 'Dashboard Mitra'; ?></h4>

                                <div class="mini-kpi-row">
                                    <div class="mini-kpi-card">
                                        <span class="mini-kpi-ic"><i class="fa-solid fa-ticket"></i></span>
                                        <div><small>Total Transaksi</small><strong>1.248</strong></div>
                                    </div>
                                    <div class="mini-kpi-card">
                                        <span class="mini-kpi-ic"><i class="fa-solid fa-money-bill-wave"></i></span>
                                        <div><small>Pendapatan</small><strong>Rp 48.720.000</strong></div>
                                    </div>
                                </div>

                                <div class="mini-chart-panel">
                                    <div class="mini-chart-head">
                                        <span>Traffic Pengunjung</span>
                                        <span class="mini-toggle"><span class="on">Harian</span><span>Bulanan</span></span>
                                    </div>
                                    <div class="mini-bars">
                                        <div class="mini-bar" style="height:38%;"></div>
                                        <div class="mini-bar" style="height:70%;"></div>
                                        <div class="mini-bar top" style="height:100%;"></div>
                                        <div class="mini-bar" style="height:55%;"></div>
                                        <div class="mini-bar" style="height:82%;"></div>
                                        <div class="mini-bar" style="height:46%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Tombol Cancel & Submit -->
            <div class="bottom-action-row">
                <a href="daftarVenue.php" class="btn-cancel-outline">Cancel</a>
                <button type="submit" class="btn-submit-primary">Submit</button>
            </div>
        </form>
    </main>
</div>

<script>
function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }

const DEFAULT_LOGO = '../img/jasamarga-logo.png';

function setLogo(src) {
    document.getElementById('prevLogoImg').src = src;
    document.getElementById('logoThumb').src   = src;
}

function updateGreeting() {
    const sel  = document.getElementById('selectMitra');
    const opt  = sel.options[sel.selectedIndex];
    const nama = opt ? (opt.getAttribute('data-nama') || '') : '';
    document.getElementById('prevMitraGreeting').innerText = nama ? ('Selamat Datang, ' + nama + '!') : 'Selamat Datang, Mitra!';
}

function onMitraSelect() {
    const sel  = document.getElementById('selectMitra');
    const opt  = sel.options[sel.selectedIndex];
    const nama = opt.getAttribute('data-nama') || '';
    const logo = opt.getAttribute('data-logo') || '';

    const namaVenueInp = document.getElementById('inputNamaVenue');
    if (nama && namaVenueInp.value === '') {
        namaVenueInp.value = 'Portal ' + nama;
    }

    setLogo(logo ? logo : DEFAULT_LOGO);
    updateGreeting();
    refreshPreview();
}

function onLogoPicked(input) {
    if (input.files && input.files[0]) {
        document.getElementById('logoFileName').innerText = input.files[0].name;
        const reader = new FileReader();
        reader.onload = e => setLogo(e.target.result);
        reader.readAsDataURL(input.files[0]);
    }
}

// Kontras warna primer terhadap teks putih (rumus WCAG)
function contrastWithWhite(hex) {
    const n = parseInt(hex.replace('#', ''), 16);
    const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(v => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    const lum = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    return 1.05 / (lum + 0.05);
}

function refreshPreview() {
    const primer = document.getElementById('colorPrimer').value;
    const aksen  = document.getElementById('colorAksen').value;
    const bg     = document.getElementById('colorBg').value;
    const judul  = document.getElementById('inputNamaVenue').value;

    const portal = document.getElementById('miniPortal');
    portal.style.setProperty('--primer', primer);
    portal.style.setProperty('--aksen', aksen);
    portal.style.setProperty('--bg', bg);

    document.getElementById('prevPortalTitle').innerText = judul || 'Dashboard Mitra';

    document.getElementById('swatchPrimer').style.background = primer;
    document.getElementById('swatchAksen').style.background  = aksen;
    document.getElementById('swatchBg').style.background     = bg;
    document.getElementById('hexPrimer').innerText = primer.toUpperCase();
    document.getElementById('hexAksen').innerText  = aksen.toUpperCase();
    document.getElementById('hexBg').innerText     = bg.toUpperCase();

    const hint = document.getElementById('contrastHint');
    if (contrastWithWhite(primer) < 3) {
        hint.className = 'contrast-hint warn';
        hint.textContent = 'Warna primer terlalu terang, sehingga teks putih di kartu dan menu bisa sulit dibaca. Coba pilih warna yang lebih gelap.';
    } else {
        hint.className = 'contrast-hint ok';
        hint.textContent = 'Kontras warna primer dengan teks putih sudah baik.';
    }
}

updateGreeting();
refreshPreview();
</script>
</body>
</html>