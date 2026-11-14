<?php
session_start();
require_once dirname(__DIR__) . '/koneksi.php';

if (!isset($_SESSION["loggedin"]) or $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ambil Opsi Area untuk Dropdown Filter
$opt_area = mysqli_query($conn, "SELECT DISTINCT nama_area FROM area_lokasi ORDER BY nama_area ASC");

// Filter GET (Periode & Area)
$f_periode = $_GET['periode'] ?? '';
$f_area    = $_GET['area'] ?? '';

$where_clauses = ["t.status_transaksi = 'Berhasil'"];
if ($f_area !== '') {
    $val_area = mysqli_real_escape_string($conn, $f_area);
    $where_clauses[] = "a.nama_area = '$val_area'";
}
if ($f_periode === 'Hari Ini') {
    $where_clauses[] = "DATE(t.waktu_transaksi) = CURDATE()";
} elseif ($f_periode === 'Minggu Ini') {
    $where_clauses[] = "YEARWEEK(t.waktu_transaksi, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($f_periode === 'Bulan Ini') {
    $where_clauses[] = "MONTH(t.waktu_transaksi) = MONTH(CURDATE()) AND YEAR(t.waktu_transaksi) = YEAR(CURDATE())";
}
$where_sql = implode(" AND ", $where_clauses);

// 1. Query KPI Card 1 & Card 2 (Total Tiket & Total Pendapatan)
$sql_kpi = "SELECT COUNT(t.id_transaksi) AS total_tiket, COALESCE(SUM(t.nominal), 0) AS total_pendapatan
            FROM transaksi t
            LEFT JOIN area_lokasi a ON t.id_area = a.id_area
            WHERE $where_sql";
$kpi = mysqli_fetch_assoc(mysqli_query($conn, $sql_kpi));

// 2. Query KPI Card 3 (Rincian per Penerbit)
$sql_penerbit = "SELECT t.penerbit, COUNT(t.id_transaksi) AS jml
                 FROM transaksi t
                 LEFT JOIN area_lokasi a ON t.id_area = a.id_area
                 WHERE $where_sql
                 GROUP BY t.penerbit
                 ORDER BY jml DESC";
$res_penerbit = mysqli_query($conn, $sql_penerbit);
$penerbit_list = [];
while ($p = mysqli_fetch_assoc($res_penerbit)) {
    $penerbit_list[] = $p;
}

// 3. Query Tabel Rekap Asli (Lokasi, Gate, Penerbit, Tiket Kategori, Jenis Tiket, Tipe Tiket, Jumlah, Nominal)
$query_rekap = "SELECT 
                    a.nama_area,
                    a.nama_lokasi,
                    a.nama_gate,
                    t.penerbit,
                    t.tiket_kategori,
                    t.jenis_tiket,
                    t.tipe_tiket,
                    COUNT(t.id_transaksi) AS jumlah_transaksi,
                    SUM(t.nominal) AS total_nominal
                FROM transaksi t
                LEFT JOIN area_lokasi a ON t.id_area = a.id_area
                WHERE $where_sql
                GROUP BY a.nama_area, a.nama_lokasi, a.nama_gate, t.penerbit, t.tiket_kategori, t.jenis_tiket, t.tipe_tiket
                ORDER BY a.nama_area ASC, a.nama_lokasi ASC";
$res_rekap = mysqli_query($conn, $query_rekap);

$rekap_list = [];
while ($row = mysqli_fetch_assoc($res_rekap)) {
    $rekap_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Transaksi - JMTO Ticketing</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 3 Blue Summary Cards Sesuai Desain Figma */
        .rekap-blue-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 992px) {
            .rekap-blue-grid { grid-template-columns: 1fr; }
        }
        .rekap-blue-card {
            background-color: #004b87;
            color: #ffffff;
            border-radius: 16px;
            padding: 24px 26px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 14px rgba(0, 75, 135, 0.16);
        }
        .rekap-blue-icon {
            font-size: 38px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
        }
        .rekap-blue-info {
            flex: 1;
        }
        .rekap-blue-label {
            font-size: 12px;
            font-weight: 500;
            color: #e2e8f0;
            display: block;
            margin-bottom: 4px;
        }
        .rekap-blue-value {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }
        .rekap-blue-trend {
            font-size: 11px;
            color: #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .penerbit-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 14px;
            margin-top: 8px;
        }
        .penerbit-mini-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .penerbit-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background-color: #ffffff;
            color: #004b87;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .penerbit-mini-text span {
            display: block;
            font-size: 10px;
            color: #cbd5e1;
            line-height: 1.1;
        }
        .penerbit-mini-text strong {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }
        .btn-excel-download { background-color: #1e7e34; color: #ffffff; border: none; padding: 10px 18px; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .toolbar-right-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .total-row { background-color: #f8fafc; font-weight: 700; color: #004b87; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-logo"><img src="../img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo-img"></div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-border-all icon"></i> Dashboard</a>
                <div class="menu-group active">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-regular fa-file-lines icon"></i> Laporan <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="detailTransaksi.php">Detail Transaksi</a>
                        <a href="daftarTransaksi.php">Daftar Transaksi</a>
                        <a href="rekapTransaksi.php" class="sub-active">Rekap Transaksi</a>
                    </div>
                </div>
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-desktop icon"></i> Monitoring <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="cctv.php">CCTV</a>
                        <a href="monitoringArea.php">Perangkat Area</a>
                    </div>
                </div>
                <div class="menu-group">
                    <div class="menu-header" onclick="toggleDropdown(this)"><i class="fa-solid fa-briefcase icon"></i> Manajemen <i class="fa-solid fa-chevron-down arrow"></i></div>
                    <div class="submenu">
                        <a href="daftarUser.php">User</a>
                        <a href="daftarRole.php">Role</a>
                        <a href="daftarMitra.php">Mitra</a>
                        <a href="daftarPerangkat.php">Perangkat</a>
                    </div>
                </div>
                <a href="../logout.php" class="menu-item logout"><i class="fa-solid fa-right-from-bracket icon"></i> Keluar</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <p class="welcome-text">Laporan</p>
                <h1 class="page-header-title">Rekap Transaksi</h1>
            </header>

            <!-- Filter Card: Periode & Area -->
            <section class="filter-card">
                <form method="GET" action="">
                    <div class="filter-row align-center">
                        <div class="filter-group">
                            <label>Periode</label>
                            <select name="periode">
                                <option value="">Semua periode</option>
                                <option value="Hari Ini" <?= $f_periode === 'Hari Ini' ? 'selected' : ''; ?>>Hari Ini</option>
                                <option value="Minggu Ini" <?= $f_periode === 'Minggu Ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                                <option value="Bulan Ini" <?= $f_periode === 'Bulan Ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Area</label>
                            <select name="area">
                                <option value="">Semua area</option>
                                <?php while ($a = mysqli_fetch_assoc($opt_area)): ?>
                                    <option value="<?= htmlspecialchars($a['nama_area']); ?>" <?= $f_area === $a['nama_area'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($a['nama_area']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <a href="rekapTransaksi.php" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
                            <button type="submit" class="btn-filter">Filter</button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- 3 Card Biru Sesuai Desain Figma -->
            <section class="rekap-blue-grid">
                <!-- Card 1: Total Tiket -->
                <div class="rekap-blue-card">
                    <div class="rekap-blue-icon">
                        <i class="fa-solid fa-ticket" style="transform: rotate(-35deg);"></i>
                    </div>
                    <div class="rekap-blue-info">
                        <span class="rekap-blue-label">Total Tiket</span>
                        <h2 class="rekap-blue-value"><?= number_format($kpi['total_tiket'], 0, ',', '.'); ?></h2>
                        <span class="rekap-blue-trend"><i class="fa-solid fa-caret-up"></i> Data real-time database</span>
                    </div>
                </div>

                <!-- Card 2: Total Pendapatan -->
                <div class="rekap-blue-card">
                    <div class="rekap-blue-icon">
                        <i class="fa-solid fa-money-bill-trend-up"></i>
                    </div>
                    <div class="rekap-blue-info">
                        <span class="rekap-blue-label">Total Pendapatan</span>
                        <h2 class="rekap-blue-value">Rp <?= number_format($kpi['total_pendapatan'], 0, ',', '.'); ?></h2>
                        <span class="rekap-blue-trend"><i class="fa-solid fa-caret-up"></i> Data real-time database</span>
                    </div>
                </div>

                <!-- Card 3: Penerbit -->
                <div class="rekap-blue-card">
                    <div class="rekap-blue-icon">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div class="rekap-blue-info">
                        <span class="rekap-blue-label">Penerbit</span>
                        <div class="penerbit-mini-grid">
                            <?php if (count($penerbit_list) === 0): ?>
                                <div class="penerbit-mini-text"><strong>-</strong></div>
                            <?php else: ?>
                                <?php foreach ($penerbit_list as $pen): ?>
                                    <div class="penerbit-mini-item">
                                        <div class="penerbit-avatar"><?= strtoupper(substr($pen['penerbit'], 0, 2)); ?></div>
                                        <div class="penerbit-mini-text">
                                            <span><?= htmlspecialchars($pen['penerbit']); ?></span>
                                            <strong><?= number_format($pen['jml'], 0, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tabel Rekap Transaksi Asli -->
            <section class="table-section-card">
                <div class="table-toolbar">
                    <div class="page-size-box">
                        <select id="rowsPerPage" onchange="changePageSize()">
                            <option value="10" selected>10/page</option>
                            <option value="25">25/page</option>
                        </select>
                    </div>
                    <div class="toolbar-right-group">
                        <div class="search-box">
                            <input type="text" id="searchInput" onkeyup="applyFilter()" placeholder="Search lokasi, gate, penerbit...">
                        </div>
                        <button type="button" class="btn-excel-download" onclick="exportToExcel()">
                            <i class="fa-solid fa-file-excel"></i> Download Excel
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="rekapTable">
                        <thead>
                            <tr>
                                <th style="width: 4%;">No</th>
                                <th>Lokasi</th>
                                <th>Gate</th>
                                <th>Penerbit</th>
                                <th>Tiket Kategori</th>
                                <th>Jenis Tiket</th>
                                <th>Tipe Tiket</th>
                                <th>Jumlah</th>
                                <th>Nominal</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($rekap_list) === 0): ?>
                                <tr><td colspan="9" style="text-align:center; padding:20px; color:#828282;">Belum ada data transaksi di database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rekap_list as $index => $row): ?>
                                <tr data-area="<?= htmlspecialchars($row['nama_area'] ?? ''); ?>"
                                    data-penerbit="<?= htmlspecialchars($row['penerbit']); ?>"
                                    data-jumlah="<?= $row['jumlah_transaksi']; ?>"
                                    data-nominal="<?= $row['total_nominal']; ?>">
                                    <td><?= $index + 1; ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_lokasi'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($row['nama_gate'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($row['penerbit']); ?></td>
                                    <td><?= htmlspecialchars($row['tiket_kategori']); ?></td>
                                    <td><?= htmlspecialchars($row['jenis_tiket']); ?></td>
                                    <td><?= htmlspecialchars($row['tipe_tiket']); ?></td>
                                    <td style="font-weight: 600;"><?= number_format($row['jumlah_transaksi'], 0, ',', '.'); ?></td>
                                    <td style="font-weight: 600;">Rp <?= number_format($row['total_nominal'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="7" style="text-align: right; padding-right: 16px;">TOTAL KESELURUHAN</td>
                                <td id="grandTotalJumlah">0</td>
                                <td id="grandTotalNominal">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(el) { el.parentElement.classList.toggle('active'); }
        const allRows = Array.from(document.getElementById("tableBody").getElementsByTagName("tr"));
        let filteredRows = [...allRows];

        function formatNumber(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }

        function applyFilter() {
            const q = document.getElementById("searchInput").value.toLowerCase();

            filteredRows = allRows.filter(r => {
                if (r.getElementsByTagName("td").length <= 1) return true;
                const mQ = r.innerText.toLowerCase().includes(q);
                r.style.display = mQ ? "" : "none";
                return mQ;
            });
            updateTotals();
        }

        function updateTotals() {
            let sumJml = 0, sumNom = 0;
            filteredRows.forEach(r => {
                sumJml += parseInt(r.getAttribute("data-jumlah")) || 0;
                sumNom += parseInt(r.getAttribute("data-nominal")) || 0;
            });
            document.getElementById("grandTotalJumlah").innerText = formatNumber(sumJml);
            document.getElementById("grandTotalNominal").innerText = "Rp " + formatNumber(sumNom);
        }

        function changePageSize() {}
        function exportToExcel() { window.print(); }
        updateTotals();
    </script>
</body>
</html>