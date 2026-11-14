<?php
require 'koneksi.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load library PHPMailer dari folder manual PHPMailer/src/ atau Composer
if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

$notif = '';
$tipe_notif = '';

// Ambil daftar Mitra & Role Mitra otomatis dari database
$opt_mitra = mysqli_query($conn, "SELECT kode_mitra, nama_mitra FROM mitra ORDER BY nama_mitra ASC");
$q_role    = mysqli_query($conn, "SELECT id_role FROM roles WHERE LOWER(nama_role) = 'mitra' LIMIT 1");
$id_role_mitra = ($q_role && mysqli_num_rows($q_role) > 0) ? mysqli_fetch_assoc($q_role)['id_role'] : 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // Aksi 1: Kirim Email Undangan Simulasi
    if ($aksi === 'kirim') {
        $email_tujuan = mysqli_real_escape_string($conn, trim($_POST['email_tujuan']));
        $nama_user    = mysqli_real_escape_string($conn, trim($_POST['nama_user']));
        $kode_mitra   = mysqli_real_escape_string($conn, trim($_POST['kode_mitra']));
        $gmail_sender = trim($_POST['gmail_sender']);
        $app_password = str_replace(' ', '', trim($_POST['app_password']));

        $token     = bin2hex(random_bytes(32));
        $expires   = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $kode_user = 'SIM-' . rand(100, 999);

        // Hapus data simulasi lama dengan email yang sama agar tidak duplikat
        mysqli_query($conn, "DELETE FROM users WHERE email = '$email_tujuan' AND kode_user LIKE 'SIM-%'");

        // Insert akun Mitra berstatus Inactive + Token
        $sql_ins = "INSERT INTO users (kode_user, nama_user, email, password, id_role, id_mitra, status_user, activation_token, token_expires)
                    VALUES ('$kode_user', '$nama_user', '$email_tujuan', '', '$id_role_mitra', '$kode_mitra', 'Inactive', '$token', '$expires')";

        if (mysqli_query($conn, $sql_ins)) {
            $linkAktivasi = "http://localhost/ticketingjmto/aktivasi_mitra.php?token=" . urlencode($token);

            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $tipe_notif = 'err';
                $notif = "Folder <code>PHPMailer/src/</code> tidak ditemukan. Pastikan folder <code>src</code> sudah diletakkan di dalam <code>ticketingJMTO/PHPMailer/</code>.";
            } else {
                $mail = new PHPMailer(true);
                try {
                    // Konfigurasi SMTP Gmail
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $gmail_sender;
                    $mail->Password   = $app_password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Pengirim & Penerima
                    $mail->setFrom($gmail_sender, 'Simulasi JMTO Ticketing');
                    $mail->addAddress($email_tujuan, $nama_user);

                    // Isi Email HTML
                    $mail->isHTML(true);
                    $mail->Subject = '[SIMULASI] Aktivasi Akun Portal Mitra - JMTO Ticketing';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; max-width: 560px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px;'>
                            <h2 style='color: #004687; margin-top: 0;'>Undangan Portal Mitra JMTO</h2>
                            <p style='font-size: 14px; color: #334155; line-height: 1.6;'>
                                Halo <b>{$nama_user}</b> (Kode Mitra: <b>{$kode_mitra}</b>),<br>
                                Anda telah diundang untuk mengakses Portal Mitra JMTO Ticketing. Klik tombol di bawah ini untuk menyetujui undangan dan mengatur password akun Anda:
                            </p>
                            <div style='text-align: center; margin: 28px 0;'>
                                <a href='{$linkAktivasi}' style='background: #004687; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px;'>
                                    Terima Undangan & Buat Password
                                </a>
                            </div>
                            <p style='font-size: 12px; color: #64748b;'>
                                Atau salin tautan ini ke browser Anda:<br>
                                <a href='{$linkAktivasi}'>{$linkAktivasi}</a>
                            </p>
                        </div>";

                    $mail->send();
                    $tipe_notif = 'ok';
                    $notif = "Email undangan berhasil dikirim ke <b>{$email_tujuan}</b>! Silakan cek Inbox atau folder Spam email kamu.";
                } catch (Exception $e) {
                    $tipe_notif = 'err';
                    $notif = "Gagal kirim email: " . $mail->ErrorInfo;
                }
            }
        } else {
            $tipe_notif = 'err';
            $notif = "Gagal menyimpan ke database: " . mysqli_error($conn);
        }
    }

    // Aksi 2: Bersihkan Semua Data User Hasil Simulasi (SIM-xxx)
    if ($aksi === 'bersihkan') {
        mysqli_query($conn, "DELETE FROM users WHERE kode_user LIKE 'SIM-%'");
        $tipe_notif = 'ok';
        $notif = "Semua akun user hasil simulasi (kode SIM-xxx) berhasil dihapus dari database.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulasi Undangan Email Mitra</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .box { background: #fff; padding: 30px; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); width: 100%; max-width: 480px; }
        h2 { color: #004687; margin: 0 0 6px; font-size: 19px; }
        .sub { color: #64748b; font-size: 12px; margin-bottom: 20px; }
        .group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px; }
        input, select { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 13px; box-sizing: border-box; }
        .btn-send { width: 100%; padding: 11px; background: #004687; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; margin-top: 6px; }
        .btn-send:hover { background: #003566; }
        .btn-clean { width: 100%; padding: 9px; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; margin-top: 12px; font-size: 12px; }
        .alert-ok { background: #dcfce7; color: #15803d; padding: 12px; border-radius: 8px; font-size: 12px; margin-bottom: 16px; }
        .alert-err { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 12px; margin-bottom: 16px; }
        hr { border: none; border-top: 1px dashed #cbd5e1; margin: 18px 0; }
    </style>
</head>
<body>
<div class="box">
    <h2>Simulasi Undangan Email Mitra</h2>
    <p class="sub">File pengujian terpisah. Hapus file ini jika simulasi selesai.</p>

    <?php if ($notif): ?>
        <div class="<?= $tipe_notif === 'ok' ? 'alert-ok' : 'alert-err'; ?>"><?= $notif; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="aksi" value="kirim">
        <div class="group">
            <label>Gmail Pengirim (SMTP)</label>
            <input type="email" name="gmail_sender" placeholder="contoh: emailkamu@gmail.com" required>
        </div>
        <div class="group">
            <label>App Password Gmail (16 Digit)</label>
            <input type="password" name="app_password" placeholder="xxxx xxxx xxxx xxxx" required>
        </div>
        <hr>
        <div class="group">
            <label>Pilih Instansi Mitra</label>
            <select name="kode_mitra" required>
                <?php if ($opt_mitra): while ($m = mysqli_fetch_assoc($opt_mitra)): ?>
                    <option value="<?= htmlspecialchars($m['kode_mitra']); ?>">
                        <?= htmlspecialchars($m['kode_mitra'] . ' - ' . $m['nama_mitra']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="group">
            <label>Nama PIC Mitra</label>
            <input type="text" name="nama_user" value="PIC Simulasi Mitra" required>
        </div>
        <div class="group">
            <label>Email Penerima Undangan (Email Kamu)</label>
            <input type="email" name="email_tujuan" placeholder="Masukkan email tujuan tes" required>
        </div>
        <button type="submit" class="btn-send">Kirim Link Aktivasi ke Email</button>
    </form>

    <form method="POST" onsubmit="return confirm('Hapus semua user simulasi (SIM-xxx) dari database?');">
        <input type="hidden" name="aksi" value="bersihkan">
        <button type="submit" class="btn-clean">Bersihkan Data User Simulasi dari Database</button>
    </form>
</div>
</body>
</html>