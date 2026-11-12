<?php
session_start();
require 'koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Ambil Data Perangkat CCTV dari Tabel perangkat + area_lokasi
$query_cctv = "SELECT p.*, a.nama_area, a.nama_lokasi, a.nama_gate 
               FROM perangkat p 
               LEFT JOIN area_lokasi a ON p.id_area = a.id_area 
               WHERE p.jenis_perangkat = 'CCTV' 
               ORDER BY p.id_perangkat ASC";
$res_cctv = mysqli_query($conn, $query_cctv);

$cctv_list = [];
$area_options = [];
$lokasi_options = [];
$total_aktif = 0;
$total_mati  = 0;

while ($row = mysqli_fetch_assoc($res_cctv)) {
    if ($row['status_perangkat'] === 'Aktif') {
        $total_aktif++;
    } else {
        $total_mati++;
    }

    if (!empty($row['nama_area']) && !in_array($row['nama_area'], $area_options)) {
        $area_options[] = $row['nama_area'];
    }
    if (!empty($row['nama_lokasi']) && !in_array($row['nama_lokasi'], $lokasi_options)) {
        $lokasi_options[] = $row['nama_lokasi'];
    }

    $cctv_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring CCTV - JMTO Ticketing</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icon Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

                <!-- Menu Dropdown: Monitoring (Aktif) -->
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-desktop icon"></i> Monitoring
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="cctv.php" class="sub-active">CCTV</a>
                        <a href="monitoringArea.php">Perangkat Area</a>
                    </div>
                </div>

                <!-- Menu Dropdown: Manajemen -->
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-briefcase icon"></i> Manajemen
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="daftarUser.php">User</a>
                        <a href="daftarRole.php">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
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
            <header class="top-header">
                <p class="welcome-text">Monitoring CCTV</p>
                <h1 class="page-header-title">CCTV</h1>
            </header>

            <!-- Filter Card (Area & Lokasi) -->
            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group">
                        <label>Area</label>
                        <select id="filterArea">
                            <option value="">Pilih area</option>
                            <?php foreach ($area_options as $ar): ?>
                                <option value="<?= htmlspecialchars($ar); ?>"><?= htmlspecialchars($ar); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Lokasi</label>
                        <select id="filterLokasi">
                            <option value="">Pilih lokasi</option>
                            <?php foreach ($lokasi_options as $lok): ?>
                                <option value="<?= htmlspecialchars($lok); ?>"><?= htmlspecialchars($lok); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="btn-reset" onclick="resetCctvFilter()">Reset</button>
                        <button type="button" class="btn-filter" onclick="applyCctvFilter()">Filter</button>
                    </div>
                </div>
            </section>

            <!-- Stat Cards Monitoring CCTV (2 Cards) -->
            <section class="cctv-stats-grid">
                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-video"></i></div>
                    <div class="card-info">
                        <span class="card-label">CCTV Aktif</span>
                        <h2 class="card-value"><?= $total_aktif; ?></h2>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="card-icon"><i class="fa-solid fa-video-slash"></i></div>
                    <div class="card-info">
                        <span class="card-label">CCTV Mati</span>
                        <h2 class="card-value"><?= $total_mati; ?></h2>
                    </div>
                </div>
            </section>

            <!-- Container CCTV Feeds (Tersembunyi secara default) -->
            <section class="cctv-main-card">
                <div class="cctv-header-bar">
                    <h2 class="cctv-card-title">CCTV</h2>
                    <div class="search-box cctv-search">
                        <input type="text" id="cctvSearchInput" onkeyup="searchCctv()" placeholder="Search">
                    </div>
                </div>

                <!-- Grid Tampilan Feed Video CCTV -->
                <div class="cctv-grid" id="cctvGrid" style="display: none;">
                    <?php foreach ($cctv_list as $cctv): ?>
                        <div class="cctv-item-card"
                             data-area="<?= htmlspecialchars($cctv['nama_area'] ?? ''); ?>"
                             data-lokasi="<?= htmlspecialchars($cctv['nama_lokasi'] ?? ''); ?>"
                             data-nama="<?= htmlspecialchars(strtolower($cctv['nama_perangkat'])); ?>">
                            <div class="cctv-badge-title">
                                <span><?= htmlspecialchars($cctv['nama_perangkat']); ?></span>
                            </div>
                            <div class="cctv-video-container">
                                <img src="https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?w=500&auto=format&fit=crop&q=60" alt="<?= htmlspecialchars($cctv['nama_perangkat']); ?>" class="cctv-stream-img">
                                <span class="cctv-live-tag"><?= $cctv['status_perangkat'] === 'Aktif' ? 'LIVE' : 'OFFLINE'; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Placeholder Pesan Sebelum Filter Klik -->
                <div class="cctv-empty-placeholder" id="cctvPlaceholder">
                    <i class="fa-solid fa-filter"></i>
                    <p>Silakan pilih <b>Area</b> & <b>Lokasi</b> lalu klik <b>Filter</b> untuk menampilkan tayangan CCTV.</p>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('active');
        }

        const cctvGrid = document.getElementById("cctvGrid");
        const cctvPlaceholder = document.getElementById("cctvPlaceholder");

        // Fungsi Tampilkan Gambar saat Klik Filter
        function applyCctvFilter() {
            cctvGrid.style.display = "grid";
            cctvPlaceholder.style.display = "none";

            const areaVal = document.getElementById("filterArea").value;
            const lokasiVal = document.getElementById("filterLokasi").value;
            const items = document.querySelectorAll(".cctv-item-card");

            items.forEach(item => {
                const itemArea = item.getAttribute("data-area");
                const itemLokasi = item.getAttribute("data-lokasi");

                const matchArea = areaVal === "" || itemArea === areaVal;
                const matchLokasi = lokasiVal === "" || itemLokasi === lokasiVal;

                if (matchArea && matchLokasi) {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });
        }

        // Reset Filter
        function resetCctvFilter() {
            document.getElementById("filterArea").value = "";
            document.getElementById("filterLokasi").value = "";
            document.getElementById("cctvSearchInput").value = "";
            cctvGrid.style.display = "none";
            cctvPlaceholder.style.display = "flex";
        }

        // Live Search Input CCTV
        function searchCctv() {
            const query = document.getElementById("cctvSearchInput").value.toLowerCase().trim();
            if (query.length > 0) {
                cctvGrid.style.display = "grid";
                cctvPlaceholder.style.display = "none";
            }
            const items = document.querySelectorAll(".cctv-item-card");
            items.forEach(item => {
                const nama = item.getAttribute("data-nama");
                if (nama.includes(query)) {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>