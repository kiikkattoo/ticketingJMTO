<?php
// Pastikan sesi mitra aktif tersedia
$kode_mitra_aktif = mysqli_real_escape_string($conn, $_SESSION['id_mitra'] ?? '');

$tema_mitra = [
    'nama_tema'    => 'Portal Mitra JMTO',
    'logo_portal'  => '../img/jasamarga-logo.png',
    'warna_primer' => '#004687',
    'warna_aksen'  => '#EAB308',
    'warna_bg'     => '#F8FAFC',
    'show_revenue' => 1,
    'multi_tiket'  => 0
];

if ($kode_mitra_aktif !== '') {
    $sql_tema = "SELECT tv.*, m.logo_mitra, m.nama_mitra 
                 FROM mitra m
                 LEFT JOIN tema_venue tv ON m.kode_mitra = tv.kode_mitra
                 WHERE m.kode_mitra = '$kode_mitra_aktif'
                 LIMIT 1";
    $res_tema = mysqli_query($conn, $sql_tema);
    if ($res_tema && $row_t = mysqli_fetch_assoc($res_tema)) {
        if (!empty($row_t['nama_tema']))    $tema_mitra['nama_tema']    = $row_t['nama_tema'];
        if (!empty($row_t['warna_primer'])) $tema_mitra['warna_primer'] = $row_t['warna_primer'];
        if (!empty($row_t['warna_aksen']))  $tema_mitra['warna_aksen']  = $row_t['warna_aksen'];
        if (!empty($row_t['warna_bg']))     $tema_mitra['warna_bg']     = $row_t['warna_bg'];
        if (isset($row_t['show_revenue']))  $tema_mitra['show_revenue'] = (int)$row_t['show_revenue'];
        if (isset($row_t['multi_tiket']))   $tema_mitra['multi_tiket']  = (int)$row_t['multi_tiket'];

        // Perbaikan Path Logo
        if (!empty($row_t['logo_portal'])) {
            $logo_db = $row_t['logo_portal'];
            $tema_mitra['logo_portal'] = (strpos($logo_db, '../') === 0) ? $logo_db : '../' . $logo_db;
        } elseif (!empty($row_t['logo_mitra'])) {
            $logo_db = $row_t['logo_mitra'];
            $tema_mitra['logo_portal'] = (strpos($logo_db, '../') === 0) ? $logo_db : '../' . $logo_db;
        }
    }
}
?>
<style>
    :root {
        --mitra-primer: <?= htmlspecialchars($tema_mitra['warna_primer']); ?>;
        --mitra-aksen: <?= htmlspecialchars($tema_mitra['warna_aksen']); ?>;
        --mitra-bg: <?= htmlspecialchars($tema_mitra['warna_bg']); ?>;
    }

    /* Terapkan warna background dan tema secara menyeluruh ke semua halaman mitra */
    body, .main-content {
        background-color: var(--mitra-bg) !important;
    }

    /* Paksa semua card statistik (KPI) mengikuti warna primer dari database */
    .stat-card, 
    .stats-grid .stat-card {
        background-color: var(--mitra-primer) !important;
    }

    /* Sidebar aktif, tombol filter, dan header tabel */
    .sidebar-menu .menu-item.active,
    .sidebar-menu .submenu a.active-sub,
    .btn-filter {
        background-color: var(--mitra-primer) !important;
        border-color: var(--mitra-primer) !important;
        color: #ffffff !important;
    }
</style>