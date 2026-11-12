<?php
date_default_timezone_set('Asia/Jakarta');

$host     = "localhost";
$username = "root";
$password = "";
$database = "ticketingjmto";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '+07:00'");
?>