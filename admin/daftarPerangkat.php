<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Generate ID Perangkat Otomatis (DEV-001, DEV-002, dst.)
$res_cnt = mysqli_query($conn, "SELECT COUNT(*) AS total FROM perangkat");
$row_cnt = mysqli_fetch_assoc($res_cnt);
$auto_id = "DEV-" . str_pad(($row_cnt['total'] ?? 0) + 1, 3, "0", STR_PAD_LEFT);

// Ambil Daftar Lokasi dari Tabel area_lokasi
$opt_lokasi = mysqli_query($conn, "SELECT id_area, nama_area, nama_lokasi, nama_gate FROM area_lokasi ORDER BY nama_area ASC, nama_lokasi ASC");

$success_msg = "";
$error_msg   = "";

// Proses Simpan Perangkat ke Database saat Klik Submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kode_perangkat  = mysqli_real_escape_string($conn, $_POST['kode_perangkat']);
    $id_area         = intval($_POST['id_area']);
    $nama_perangkat  = mysqli_real_escape_string($conn, $_POST['nama_perangkat']);
    $jenis_perangkat = mysqli_real_escape_string($conn, $_POST['jenis_perangkat']);
    $ip_address      = mysqli_real_escape_string($conn, $_POST['ip_address']);
    $status_pilihan  = $_POST['status'] ?? 'Enable';

    // Konversi Enable -> Aktif, Disable -> Nonaktif agar sinkron dengan Dashboard & Monitoring
    $status_db = ($status_pilihan === 'Enable') ? 'Aktif' : 'Nonaktif';

    $sql = "INSERT INTO perangkat (kode_perangkat, id_area, nama_perangkat, jenis_perangkat, ip_address, status_perangkat, last_ping)
            VALUES ('$kode_perangkat', $id_area, '$nama_perangkat', '$jenis_perangkat', '$ip_address', '$status_db', NOW())";

    if (mysqli_query($conn, $sql)) {
        $success_msg = "Perangkat <b>$nama_perangkat</b> ($kode_perangkat) berhasil ditambahkan dan langsung muncul di halaman Monitoring!";
        // Perbarui nomor urut ID otomatis berikutnya
        $res_cnt = mysqli_query($conn, "SELECT COUNT(*) AS total FROM perangkat");
        $row_cnt = mysqli_fetch_assoc($res_cnt);
        $auto_id = "DEV-" . str_pad(($row_cnt['total'] ?? 0) + 1, 3, "0", STR_PAD_LEFT);
    } else {
        $error_msg = "Gagal menyimpan perangkat: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Perangkat - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .perangkat-form-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 36px 32px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            margin-top: 10px;
        }
        .form-stack-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }
        .form-stack-group label {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-stack-group input[type="text"],
        .form-stack-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #333333;
            background-color: #ffffff;
            box-sizing: border-box;
        }
        .form-stack-group input[readonly] {
            background-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }
        .status-radio-group {
            display: flex;
            align-items: center;
            gap: 60px;
            margin-top: 4px;
        }
        .status-radio-option {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
        }
        .status-radio-option input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border: 1.8px solid #1e293b;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .status-radio-option input[type="radio"]:checked {
            background-color: #004b87;
            border-color: #004b87;
        }
        .status-radio-option input[type="radio"]:checked::after {
            content: "✓";
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
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
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php" class="sub-active">Perangkat</a>
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
                <h1 class="page-header-title">Tambah Perangkat</h1>
            </header>

            <?php if ($success_msg): ?>
                <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; border:1px solid #b7eb8f;">
                    <i class="fa-solid fa-circle-check"></i> <?= $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div style="background:#ffe5e5; color:#c82333; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; border:1px solid #f5c6cb;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="formPerangkat">
                <section class="perangkat-form-card">
                    <div class="form-stack-group">
                        <label>ID</label>
                        <input type="text" name="kode_perangkat" value="<?= $auto_id; ?>" readonly>
                    </div>

                    <div class="form-stack-group">
                        <label>Lokasi</label>
                        <select name="id_area" required>
                            <option value="">Masukkan lokasi</option>
                            <?php while ($lok = mysqli_fetch_assoc($opt_lokasi)): ?>
                                <option value="<?= $lok['id_area']; ?>">
                                    <?= htmlspecialchars($lok['nama_area'] . ' - ' . $lok['nama_lokasi'] . ' (' . $lok['nama_gate'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-stack-group">
                        <label>Nama Perangkat</label>
                        <input type="text" name="nama_perangkat" placeholder="Masukkan nama" required>
                    </div>

                    <div class="form-stack-group">
                        <label>Jenis Perangkat</label>
                        <select name="jenis_perangkat" required>
                            <option value="">Masukkan jenis perangkat</option>
                            <option value="Turnstile">Turnstile</option>
                            <option value="CCTV">CCTV</option>
                            <option value="Barrier Gate">Barrier Gate</option>
                            <option value="Card Reader">Card Reader</option>
                        </select>
                    </div>

                    <div class="form-stack-group">
                        <label>IP Address</label>
                        <input type="text" name="ip_address" placeholder="Masukkan IP Address" required>
                    </div>

                    <div class="form-stack-group" style="margin-bottom: 0;">
                        <label>Status</label>
                        <div class="status-radio-group">
                            <label class="status-radio-option">
                                <input type="radio" name="status" value="Enable" checked>
                                <span>Enable</span>
                            </label>
                            <label class="status-radio-option">
                                <input type="radio" name="status" value="Disable">
                                <span>Disable</span>
                            </label>
                        </div>
                    </div>
                </section>

                <div class="form-bottom-actions">
                    <button type="reset" class="btn-reset">Cancel</button>
                    <button type="submit" class="btn-filter">Submit</button>
                </div>
            </form>
        </main>
    </div>

    <script>
        function toggleDropdown(el) {
            el.parentElement.classList.toggle('active');
        }
    </script>
</body>
</html>