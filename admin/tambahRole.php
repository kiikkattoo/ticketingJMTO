<?php
session_start();

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Generasi ID Otomatis (Contoh ID Otomatis: ROLE-006)
$auto_id = "ROLE-00" . rand(6, 99); 
$success_msg = "";
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role_name = trim($_POST["role"] ?? "");
    
    if (empty($role_name)) {
        $error_msg = "Nama Role tidak boleh kosong!";
    } else {
        // Logika simpan ke Database bisa ditambahkan di sini
        $success_msg = "Role '" . htmlspecialchars($role_name) . "' berhasil ditambahkan!";
        // Redirect kembali ke daftarRole.php setelah berhasil (opsional)
        // header("Location: daftarRole.php");
        // exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Role - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icon Font Awesome -->
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
                
                <!-- Menu Dropdown: Laporan -->
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

                <!-- Menu Dropdown: Monitoring -->
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

                <!-- Menu Dropdown: Manajemen (Aktif & Terbuka) -->
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-briefcase icon"></i> Manajemen 
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="daftarUser.php">User</a>
                        <a href="daftarRole.php" class="sub-active">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
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
                <h1 class="page-header-title">Tambah Role</h1>
            </header>

            <!-- Link Kembali -->
            <div class="back-link-container">
                <a href="daftarRole.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-error" style="max-width: 100%; margin-bottom: 20px;"><?= $error_msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert-success"><?= $success_msg; ?></div>
            <?php endif; ?>

            <!-- Form Tambah Role -->
            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <section class="form-card-container">
                    <div class="form-group-custom">
                        <label for="role_id">ID</label>
                        <input type="text" id="role_id" name="role_id" value="<?= $auto_id; ?>" readonly class="input-readonly">
                    </div>

                    <div class="form-group-custom">
                        <label for="role">Role</label>
                        <input type="text" id="role" name="role" placeholder="Masukkan Role" required autocomplete="off">
                    </div>
                </section>

                <!-- Tombol Action (Cancel & Submit) -->
                <div class="form-footer-buttons">
                    <a href="daftarRole.php" class="btn-cancel">Cancel</a>
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