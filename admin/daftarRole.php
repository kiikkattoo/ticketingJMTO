<?php
session_start();

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit();
}

// Data Dummy Role Sesuai Desain UI
$role_list = [
    ["id" => 1, "role" => "Admin", "jumlah_user" => 3],
    ["id" => 2, "role" => "Approver", "jumlah_user" => 2],
    ["id" => 3, "role" => "Manajemen", "jumlah_user" => 4],
    ["id" => 4, "role" => "Keuangan", "jumlah_user" => 5],
    ["id" => 5, "role" => "Mitra", "jumlah_user" => 27]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Role - JMTO Ticketing</title>
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
                        <a href="#">Mitra</a>
                        <a href="#">Perangkat</a>
                    </div>
                </div>

                <a href="../logout.php" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket icon"></i> Keluar
                </a>
            </nav>
        </aside>

        <!-- Area Konten Utama -->
        <main class="main-content">
            <header class="top-header-with-btn">
                <div>
                    <p class="welcome-text">Manajemen</p>
                    <h1 class="page-header-title">Daftar Role</h1>
                </div>
                <a href="tambahRole.php" class="btn-add-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-plus"></i> Tambah Role
                </a>
            </header>

            <!-- Filter Card -->
            <section class="filter-card">
                <div class="filter-row align-center">
                    <div class="filter-group flex-2">
                        <label>Role</label>
                        <select id="filterRole">
                            <option value="">Pilih kategori mitra</option>
                            <option value="Admin">Admin</option>
                            <option value="Approver">Approver</option>
                            <option value="Manajemen">Manajemen</option>
                            <option value="Keuangan">Keuangan</option>
                            <option value="Mitra">Mitra</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="btn-reset" onclick="resetFilter()">Reset</button>
                        <button type="button" class="btn-filter" onclick="applyFilter()">Filter</button>
                    </div>
                </div>
            </section>

            <!-- Section Tabel Daftar Role -->
            <section class="table-section-card">
                <!-- Toolbar Page Size -->
                <div class="table-toolbar">
                    <div class="page-size-box">
                        <select id="rowsPerPage" onchange="changePageSize()">
                            <option value="5">5/page</option>
                            <option value="10" selected>10/page</option>
                            <option value="25">25/page</option>
                        </select>
                    </div>
                </div>

                <!-- Tabel Data Role -->
                <div class="table-responsive">
                    <table class="custom-table" id="roleTable">
                        <thead>
                            <tr>
                                <th style="width: 10%;">No</th>
                                <th style="width: 35%;">Role</th>
                                <th style="width: 35%;">Jumlah User</th>
                                <th style="width: 20%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($role_list as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($row['role']); ?></td>
                                <td><?= htmlspecialchars($row['jumlah_user']); ?> users</td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action edit" title="Edit Role" onclick="editRole(<?= $row['id']; ?>, '<?= htmlspecialchars($row['role']); ?>')">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" title="Hapus Role" onclick="deleteRole(<?= $row['id']; ?>, '<?= htmlspecialchars($row['role']); ?>')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div class="pagination-container" id="paginationControls">
                    <button class="page-btn" onclick="prevPage()"><i class="fa-solid fa-chevron-left"></i></button>
                    <span id="pageNumbers"></span>
                    <button class="page-btn" onclick="nextPage()"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('active');
        }

        let currentPage = 1;
        let rowsPerPage = 10;
        const tbody = document.getElementById("tableBody");
        const allRows = Array.from(tbody.getElementsByTagName("tr"));
        let filteredRows = [...allRows];

        function applyFilter() {
            const roleVal = document.getElementById("filterRole").value.toLowerCase();
            filteredRows = allRows.filter(row => {
                const roleText = row.getElementsByTagName("td")[1].innerText.toLowerCase();
                return roleVal === "" || roleText === roleVal;
            });
            currentPage = 1;
            renderTable();
        }

        function resetFilter() {
            document.getElementById("filterRole").value = "";
            filteredRows = [...allRows];
            currentPage = 1;
            renderTable();
        }

        function changePageSize() {
            rowsPerPage = parseInt(document.getElementById("rowsPerPage").value);
            currentPage = 1;
            renderTable();
        }

        function renderTable() {
            allRows.forEach(row => row.style.display = "none");
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;
            const pageRows = filteredRows.slice(startIndex, endIndex);

            pageRows.forEach((row, idx) => {
                row.style.display = "";
                row.getElementsByTagName("td")[0].innerText = startIndex + idx + 1;
            });

            renderPagination();
        }

        function renderPagination() {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            const pageNumbers = document.getElementById("pageNumbers");
            pageNumbers.innerHTML = "";

            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement("button");
                btn.innerText = i;
                btn.className = "page-num" + (i === currentPage ? " active" : "");
                btn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                pageNumbers.appendChild(btn);
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        }

        function nextPage() {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        }


        function editRole(id, name) {
            window.location.href = "editRole.php?id=" + id;
}

        function deleteRole(id, name) {
            if (confirm("Apakah Anda yakin ingin menghapus role '" + name + "'?")) {
                alert("Role " + name + " berhasil dihapus!");
            }
        }

        renderTable();
    </script>
</body>
</html>