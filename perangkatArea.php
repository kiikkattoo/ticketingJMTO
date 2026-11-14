<?php
session_start();

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit();
}

// Fungsi Helper SVG Tajam Presisi Sesuai Desain Figma
function getDeviceSvg($type) {
    switch ($type) {
        case 'turnstile':
            // SVG Turnstile Tripod Gate (Tiang + 3 Palang)
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 4h5v16H5z" fill="currentColor" fill-opacity="0.2"/>
                        <rect x="5" y="4" width="5" height="16" rx="1"/>
                        <line x1="10" y1="9" x2="20" y2="9"/>
                        <line x1="10" y1="9" x2="17" y2="16"/>
                        <line x1="10" y1="9" x2="12" y2="18"/>
                    </svg>';
        case 'cctv':
            // SVG CCTV Camera Bullet Presisi
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 8l14-4 3 7-14 4z" fill="currentColor" fill-opacity="0.2"/>
                        <path d="M2 8l14-4 3 7-14 4z"/>
                        <path d="M17 7l4-1v4l-3 1"/>
                        <path d="M7 13v5h5"/>
                    </svg>';
        case 'barrier':
            // SVG Barrier Gate Palang Tol
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="5" height="9" rx="1" fill="currentColor" fill-opacity="0.2"/>
                        <rect x="3" y="11" width="5" height="9" rx="1"/>
                        <line x1="8" y1="13" x2="21" y2="6"/>
                        <line x1="12" y1="11" x2="14" y2="10"/>
                        <line x1="16" y1="8.8" x2="18" y2="7.7"/>
                    </svg>';
        default:
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                    </svg>';
    }
}

// Simulasi Data Monitoring Perangkat per Gate (Diambil dari Daftar Perangkat)
$gate_list = [
    [
        "gate_id" => "G-001",
        "gate_nama" => "Gate 001",
        "area" => "Area 1",
        "lokasi" => "Gerbang Tol Cililitan",
        "perangkat" => [
            ["nama" => "Turnstile 01", "ip" => "172.16.98.11", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "Turnstile 02", "ip" => "172.16.98.12", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "Turnstile 03", "ip" => "172.16.98.13", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "CCTV 01", "ip" => "172.16.98.21", "status" => "ok", "last_seen" => "-", "type" => "cctv"]
        ]
    ],
    [
        "gate_id" => "G-002",
        "gate_nama" => "Gate 002",
        "area" => "Area 1",
        "lokasi" => "Gerbang Tol Cililitan",
        "perangkat" => [
            ["nama" => "Turnstile 01", "ip" => "172.16.98.14", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "Turnstile 02", "ip" => "172.16.98.15", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "Turnstile 03", "ip" => "172.16.98.16", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "CCTV 01", "ip" => "172.16.98.22", "status" => "ok", "last_seen" => "-", "type" => "cctv"]
        ]
    ],
    [
        "gate_id" => "G-003",
        "gate_nama" => "Gate 003",
        "area" => "Area 2",
        "lokasi" => "Gerbang Tol Cengkareng",
        "perangkat" => [
            ["nama" => "Turnstile 01", "ip" => "172.16.98.31", "status" => "error", "last_seen" => "2026-11-03 13:55:02", "type" => "turnstile"],
            ["nama" => "Barrier Gate 01", "ip" => "172.16.98.32", "status" => "ok", "last_seen" => "-", "type" => "barrier"],
            ["nama" => "CCTV 01", "ip" => "172.16.98.33", "status" => "ok", "last_seen" => "-", "type" => "cctv"]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Perangkat Area - JMTO Ticketing</title>
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
                <img src="img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img">
            </div>

            <nav class="sidebar-menu">
                <a href="admin/dashboard.php" class="menu-item">
                    <i class="fa-solid fa-border-all icon"></i> Dashboard
                </a>
                
                <!-- Menu Dropdown: Laporan -->
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-regular fa-file-lines icon"></i> Laporan 
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="admin/detailTransaksi.php">Detail Transaksi</a>
                        <a href="admin/daftarTransaksi.php">Daftar Transaksi</a>
                        <a href="admin/rekapTransaksi.php">Rekap Transaksi</a>
                    </div>
                </div>

                <!-- Menu Dropdown: Monitoring (Aktif) -->
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-desktop icon"></i> Monitoring 
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="admin/cctv.php">CCTV</a>
                        <a href="admin/monitoringArea.php" class="sub-active">Perangkat Area</a>
                    </div>
                </div>

                <!-- Menu Dropdown: Manajemen -->
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-briefcase icon"></i> Manajemen 
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="admin/daftarUser.php">User</a>
                        <a href="admin/daftarRole.php">Role</a>
                        <a href="admin/daftarMitra.php">Mitra</a>
                        <a href="admin/daftarPerangkat.php">Perangkat</a>
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
                <p class="welcome-text">Monitoring Perangkat</p>
                <h1 class="page-header-title">AREA</h1>
            </header>

            <!-- Filter Card 3 Dropdown (AREA, LOKASI, GATE) -->
            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group">
                        <label>AREA</label>
                        <select id="filterArea">
                            <option value="">Pilih area</option>
                            <option value="Area 1">Area 1</option>
                            <option value="Area 2">Area 2</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>LOKASI</label>
                        <select id="filterLokasi">
                            <option value="">Pilih lokasi</option>
                            <option value="Gerbang Tol Cililitan">Gerbang Tol Cililitan</option>
                            <option value="Gerbang Tol Cengkareng">Gerbang Tol Cengkareng</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>GATE</label>
                        <select id="filterGate">
                            <option value="">Pilih gate</option>
                            <option value="Gate 001">Gate 001</option>
                            <option value="Gate 002">Gate 002</option>
                            <option value="Gate 003">Gate 003</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="btn-reset" onclick="resetAreaFilter()">Reset</button>
                        <button type="button" class="btn-filter" onclick="applyAreaFilter()">Filter</button>
                    </div>
                </div>
            </section>

            <!-- Main Container Daftar Gate -->
            <section class="table-section-card" style="min-height: 420px;">
                <div class="table-toolbar" style="margin-bottom: 25px;">
                    <h3 class="section-subtitle" style="margin-bottom: 0; font-size: 20px;">Daftar Gate</h3>
                    <div class="search-box">
                        <input type="text" id="areaSearchInput" onkeyup="searchGate()" placeholder="Search">
                    </div>
                </div>

                <!-- Grid Card Per Gate (Kosong/Tersembunyi secara default sebelum klik Filter) -->
                <div class="gate-grid-container" id="gateGrid" style="display: none;">
                    <?php foreach ($gate_list as $gate): ?>
                        <div class="gate-card-box" 
                             data-area="<?= htmlspecialchars($gate['area']); ?>" 
                             data-lokasi="<?= htmlspecialchars($gate['lokasi']); ?>" 
                             data-gate="<?= htmlspecialchars($gate['gate_nama']); ?>">
                            
                            <!-- Header Card Gate -->
                            <div class="gate-card-header">
                                <div class="gate-title-badge">
                                    <!-- SVG Ikon Gate Tajam -->
                                    <span class="gate-svg-wrapper">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 21V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/>
                                            <path d="M9 21v-7a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v7"/>
                                            <line x1="2" y1="21" x2="22" y2="21"/>
                                        </svg>
                                    </span>
                                    <span><?= htmlspecialchars($gate['gate_nama']); ?></span>
                                </div>
                            </div>

                            <!-- Input Cari Perangkat di Dalam Card -->
                            <div class="gate-inner-search">
                                <input type="text" placeholder="Cari Perangkat" onkeyup="searchInnerDevice(this)">
                            </div>

                            <!-- List Perangkat di Dalam Gate -->
                            <div class="device-monitoring-list">
                                <?php foreach ($gate['perangkat'] as $dev): ?>
                                    <div class="device-status-item <?= $dev['status'] === 'error' ? 'status-red' : 'status-green'; ?>" data-devname="<?= strtolower(htmlspecialchars($dev['nama'])); ?>">
                                        <div class="device-item-icon">
                                            <?= getDeviceSvg($dev['type']); ?>
                                        </div>
                                        <div class="device-item-details">
                                            <h4 class="dev-name"><?= htmlspecialchars($dev['nama']); ?></h4>
                                            <p class="dev-ip"><?= htmlspecialchars($dev['ip']); ?></p>
                                            <?php if ($dev['status'] === 'error'): ?>
                                                <p class="dev-last-seen">Last seen <?= htmlspecialchars($dev['last_seen']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('active');
        }

        const gateGrid = document.getElementById("gateGrid");

        function applyAreaFilter() {
            gateGrid.style.display = "grid";

            const areaVal = document.getElementById("filterArea").value;
            const lokasiVal = document.getElementById("filterLokasi").value;
            const gateVal = document.getElementById("filterGate").value;

            const cards = document.querySelectorAll(".gate-card-box");
            cards.forEach(card => {
                const cArea = card.getAttribute("data-area");
                const cLokasi = card.getAttribute("data-lokasi");
                const cGate = card.getAttribute("data-gate");

                const matchArea = areaVal === "" || cArea === areaVal;
                const matchLokasi = lokasiVal === "" || cLokasi === lokasiVal;
                const matchGate = gateVal === "" || cGate === gateVal;

                if (matchArea && matchLokasi && matchGate) {
                    card.style.display = "block";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function resetAreaFilter() {
            document.getElementById("filterArea").value = "";
            document.getElementById("filterLokasi").value = "";
            document.getElementById("filterGate").value = "";
            document.getElementById("areaSearchInput").value = "";

            gateGrid.style.display = "none";
        }

        function searchGate() {
            const query = document.getElementById("areaSearchInput").value.toLowerCase().trim();

            if (query.length > 0) {
                gateGrid.style.display = "grid";
            } else {
                gateGrid.style.display = "none";
                return;
            }

            const cards = document.querySelectorAll(".gate-card-box");
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                if (text.includes(query)) {
                    card.style.display = "block";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function searchInnerDevice(inputElem) {
            const query = inputElem.value.toLowerCase().trim();
            const cardBox = inputElem.closest(".gate-card-box");
            const items = cardBox.querySelectorAll(".device-status-item");

            items.forEach(item => {
                const devName = item.getAttribute("data-devname");
                if (devName.includes(query)) {
                    item.style.display = "flex";
                } else {
                    item.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>