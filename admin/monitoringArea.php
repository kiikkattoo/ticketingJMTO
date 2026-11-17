<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Fungsi Helper SVG Tajam Presisi Sesuai Desain Figma
function getDeviceSvg($type) {
    switch (strtolower($type)) {
        case 'turnstile':
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 4h5v16H5z" fill="currentColor" fill-opacity="0.2"/>
                <rect x="5" y="4" width="5" height="16" rx="1"/>
                <line x1="10" y1="9" x2="20" y2="9"/>
                <line x1="10" y1="9" x2="17" y2="16"/>
                <line x1="10" y1="9" x2="12" y2="18"/>
            </svg>';
        case 'cctv':
            return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 8l14-4 3 7-14 4z" fill="currentColor" fill-opacity="0.2"/>
                <path d="M2 8l14-4 3 7-14 4z"/>
                <path d="M17 7l4-1v4l-3 1"/>
                <path d="M7 13v5h5"/>
            </svg>';
        case 'barrier':
        case 'barrier gate':
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

// Ambil Data Area/Gate beserta Perangkatnya dari Database
$query_gate = "SELECT a.id_area, a.nama_area, a.nama_lokasi, a.nama_gate,
                      p.id_perangkat, p.nama_perangkat, p.jenis_perangkat, p.ip_address, p.status_perangkat, p.last_ping
               FROM area_lokasi a
               LEFT JOIN perangkat p ON a.id_area = p.id_area
               ORDER BY a.nama_area ASC, a.nama_gate ASC, p.id_perangkat ASC";
$res_gate = mysqli_query($conn, $query_gate);

$gate_list = [];
$opt_area = [];
$opt_lokasi = [];
$opt_gate = [];

while ($row = mysqli_fetch_assoc($res_gate)) {
    $id_area = $row['id_area'];

    if (!in_array($row['nama_area'], $opt_area)) $opt_area[] = $row['nama_area'];
    if (!in_array($row['nama_lokasi'], $opt_lokasi)) $opt_lokasi[] = $row['nama_lokasi'];
    if (!in_array($row['nama_gate'], $opt_gate)) $opt_gate[] = $row['nama_gate'];

    if (!isset($gate_list[$id_area])) {
        $gate_list[$id_area] = [
            "gate_id"   => "G-" . str_pad($id_area, 3, "0", STR_PAD_LEFT),
            "gate_nama" => $row['nama_gate'],
            "area"      => $row['nama_area'],
            "lokasi"    => $row['nama_lokasi'],
            "perangkat" => []
        ];
    }

    if (!empty($row['id_perangkat'])) {
        $is_ok = ($row['status_perangkat'] === 'Aktif');
        $gate_list[$id_area]['perangkat'][] = [
            "nama"      => $row['nama_perangkat'],
            "ip"        => $row['ip_address'],
            "status"    => $is_ok ? "ok" : "error",
            "last_seen" => $is_ok ? "-" : ($row['last_ping'] ?? date('Y-m-d H:i:s')),
            "type"      => strtolower($row['jenis_perangkat'])
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Perangkat Area - JMTO Ticketing</title>
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

                <!-- Menu Dropdown: Monitoring (Aktif) -->
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)">
                        <i class="fa-solid fa-desktop icon"></i> Monitoring
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </div>
                    <div class="submenu">
                        <a href="cctv.php">CCTV</a>
                        <a href="monitoringArea.php" class="sub-active">Perangkat Area</a>
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
                            <?php foreach ($opt_area as $ar): ?>
                                <option value="<?= htmlspecialchars($ar); ?>"><?= htmlspecialchars($ar); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>LOKASI</label>
                        <select id="filterLokasi">
                            <option value="">Pilih lokasi</option>
                            <?php foreach ($opt_lokasi as $lok): ?>
                                <option value="<?= htmlspecialchars($lok); ?>"><?= htmlspecialchars($lok); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>GATE</label>
                        <select id="filterGate">
                            <option value="">Pilih gate</option>
                            <?php foreach ($opt_gate as $gt): ?>
                                <option value="<?= htmlspecialchars($gt); ?>"><?= htmlspecialchars($gt); ?></option>
                            <?php endforeach; ?>
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
                                <?php if (count($gate['perangkat']) === 0): ?>
                                    <p style="font-size:12px; color:#94a3b8; text-align:center; padding:10px 0;">Belum ada perangkat terdaftar di gate ini.</p>
                                <?php else: ?>
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
                                <?php endif; ?>
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