<?php
declare(strict_types=1);

// ── Konfigurasi Database ───────────────────────────────────────────
// Sesuaikan nilai-nilai berikut dengan setup XAMPP / MySQL kamu.
$databaseHost     = 'localhost';
$databaseName     = 'ticketingjmto';   // ganti jika nama database berbeda
$databaseUser     = 'root';
$databasePassword = '';                // default XAMPP kosong
$databasePort     = '3306';

// ── Validasi ───────────────────────────────────────────────────────
foreach ([
    'DB_HOST' => $databaseHost,
    'DB_NAME' => $databaseName,
    'DB_USER' => $databaseUser,
] as $setting => $value) {
    if ($value === '' || $value === false) {
        throw new RuntimeException("Konfigurasi database {$setting} belum diatur.");
    }
}

// ── Koneksi PDO ────────────────────────────────────────────────────
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $databaseHost,
    $databasePort,
    $databaseName
);

return new PDO($dsn, $databaseUser, $databasePassword, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
