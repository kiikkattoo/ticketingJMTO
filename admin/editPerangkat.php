<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Simulasi Data Perangkat dari Database
$perangkat_db = [
    "DEV-001" => ["nama" => "Turnstile 01", "jenis" => "Turnstile", "ip" => "172.16.98.11", "area" => "TMII", "lokasi" => "[01] - KG A", "gate" => "Gate 001", "status" => "Aktif"],
    "DEV-002" => ["nama" => "Kereta Gantung - KG A - CCTV 001", "jenis" => "CCTV", "ip" => "172.16.98.21", "area" => "TMII", "lokasi" => "[01] - KG A", "gate" => "Gate 001", "status" => "Aktif"],
    "DEV-003" => ["nama" => "Barrier Gate 01", "jenis" => "Barrier Gate", "ip" => "172.16.98.32", "area" => "Ancol", "lokasi" => "[02] - PINTU TIMUR", "gate" => "Gate 002", "status" => "Nonaktif"]
];

$id = $_GET['id'] ?? 'DEV-001';
$data = $perangkat_db[$id] ?? $perangkat_db['DEV-001'];

$success_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data['nama'] = htmlspecialchars($_POST['nama_perangkat']);
    $data['jenis'] = htmlspecialchars($_POST['jenis_perangkat']);
    $data['ip'] = htmlspecialchars($_POST['ip_address']);
    $data['area'] = htmlspecialchars($_POST['area']);
    $data['lokasi'] = htmlspecialchars($_POST['lokasi']);
    $data['gate'] = htmlspecialchars($_POST['gate']);
    $data['status'] = htmlspecialchars($_POST['status']);
    $success_msg = "Data Perangkat berhasil diperbarui!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Perangkat - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
            </div>
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
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php" class="sub-active">Perangkat</a>
                        <a href="daftarVenue.php">Venue</a>
                    </div>
                </div>
                <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <p class="welcome-text">Manajemen / Perangkat</p>
                <h1 class="page-header-title">Edit Perangkat</h1>
            </header>

            <section class="table-section-card">
                <?php if ($success_msg): ?>
                    <div style="background:#e6f7ed; color:#1e7e34; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px; font-weight:600;">
                        <i class="fa-solid fa-circle-check"></i> <?= $success_msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>ID Perangkat</label>
                            <input type="text" value="<?= htmlspecialchars($id); ?>" readonly style="background:#f4f6f9; color:#7d7d7d; padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Nama Perangkat</label>
                            <input type="text" name="nama_perangkat" value="<?= $data['nama']; ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Jenis Perangkat</label>
                            <select name="jenis_perangkat" required>
                                <option value="Turnstile" <?= $data['jenis'] === 'Turnstile' ? 'selected' : ''; ?>>Turnstile</option>
                                <option value="CCTV" <?= $data['jenis'] === 'CCTV' ? 'selected' : ''; ?>>CCTV</option>
                                <option value="Barrier Gate" <?= $data['jenis'] === 'Barrier Gate' ? 'selected' : ''; ?>>Barrier Gate</option>
                                <option value="Card Reader" <?= $data['jenis'] === 'Card Reader' ? 'selected' : ''; ?>>Card Reader</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row" style="margin-bottom: 18px;">
                        <div class="filter-group">
                            <label>IP Address</label>
                            <input type="text" name="ip_address" value="<?= $data['ip']; ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Area</label>
                            <select name="area" required>
                                <option value="TMII" <?= $data['area'] === 'TMII' ? 'selected' : ''; ?>>TMII</option>
                                <option value="Ancol" <?= $data['area'] === 'Ancol' ? 'selected' : ''; ?>>Ancol</option>
                                <option value="Ragunan" <?= $data['area'] === 'Ragunan' ? 'selected' : ''; ?>>Ragunan</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Lokasi</label>
                            <input type="text" name="lokasi" value="<?= $data['lokasi']; ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                    </div>

                    <div class="filter-row" style="margin-bottom: 25px;">
                        <div class="filter-group">
                            <label>Gate</label>
                            <input type="text" name="gate" value="<?= $data['gate']; ?>" required style="padding:10px 14px; border:1px solid #d0d7de; border-radius:6px;">
                        </div>
                        <div class="filter-group">
                            <label>Status Perangkat</label>
                            <select name="status" required>
                                <option value="Aktif" <?= $data['status'] === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Nonaktif" <?= $data['status'] === 'Nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-buttons" style="justify-content: flex-end;">
                        <a href="daftarPerangkat.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Batal</a>
                        <button type="submit" class="btn-filter">Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
    <script>
        function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }
    </script>
</body>
</html>