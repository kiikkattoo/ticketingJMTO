<?php
session_start();
require 'koneksi.php';

$token = $_GET['token'] ?? '';
$error = "";
$success = "";
$user_data = null;

if (empty($token)) {
    $error = "Token aktivasi tidak ditemukan pada tautan.";
} else {
    $safe_token = mysqli_real_escape_string($conn, $token);
    $q = mysqli_query($conn, "SELECT * FROM users WHERE activation_token = '$safe_token' AND token_expires > NOW() LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $user_data = mysqli_fetch_assoc($q);
    } else {
        $error = "Link aktivasi tidak valid atau sudah kedaluwarsa (melebihi 24 jam).";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_data) {
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    if (strlen($pass1) < 6) {
        $error = "Password minimal harus 6 karakter.";
    } elseif ($pass1 !== $pass2) {
        $error = "Konfirmasi password tidak cocok dengan password baru.";
    } else {
        $hashed = password_hash($pass1, PASSWORD_DEFAULT);
        $uid = (int) $user_data['id'];

        // 1. Eksekusi query update ke tabel users
        $update = mysqli_query($conn, "UPDATE users SET 
                    password = '$hashed', 
                    status_user = 'Active', 
                    activation_token = NULL, 
                    token_expires = NULL 
                  WHERE id = $uid");

        if ($update) {
            // Ambil id_mitra yang terhubung
            $id_mitra_user = $user_data['id_mitra'];

            // 2. Sinkronkan status di tabel mitra menggunakan kode_mitra
            mysqli_query($conn, "UPDATE mitra SET status_mitra = 'Aktif' WHERE kode_mitra = '$id_mitra_user'");

            // SET SESSION LOGIN OTOMATIS
            $_SESSION["loggedin"] = true;
            $_SESSION["kode_user"] = $user_data['kode_user'];
            $_SESSION["nama_user"] = $user_data['nama_user'];
            $_SESSION["nama_role"] = 'mitra'; // Sesuaikan dengan role di database

            // REDIRECT OTOMATIS KE DASHBOARD MITRA
            header("Location: mitra/dashboard.php");
            exit();
        } else {
            $error = "Terjadi kesalahan sistem saat menyimpan password: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi Akun Mitra JMTO Ticketing</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            padding: 36px;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
        }
        .logo {
            height: 44px;
            margin-bottom: 20px;
        }
        h2 {
            color: #004687;
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 700;
        }
        p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: #004687;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #004687;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            margin-top: 6px;
            transition: background 0.2s;
        }
        button:hover {
            background: #003366;
        }
        .alert-err {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-ok {
            background: #dcfce7;
            color: #15803d;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="card">
    <img src="img/jasamarga-logo.png" alt="Logo Jasamarga" class="logo">
    <h2>Aktivasi Akun Mitra</h2>

    <?php if (!empty($error)): ?>
        <div class="alert-err"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-ok"><?= htmlspecialchars($success); ?></div>
        <a href="login.php" style="text-decoration: none;">
            <button type="button">Lanjut ke Halaman Login</button>
        </a>
    <?php elseif ($user_data): ?>
        <p>
            Halo <b><?= htmlspecialchars($user_data['nama_user']); ?></b> (<?= htmlspecialchars($user_data['email']); ?>), <br>
            Silakan buat password baru untuk menyetujui undangan dan mengaktifkan akun Portal Mitra Anda.
        </p>
        <form method="POST">
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="password" placeholder="Minimal 6 karakter" required>
            </div>
            <div class="form-group">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" placeholder="Ulangi password baru" required>
            </div>
            <button type="submit">Setujui & Aktifkan Akun</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>