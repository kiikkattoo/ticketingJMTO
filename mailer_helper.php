<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

function kirimInvitationMitra($emailTujuan, $namaPenerima, $namaMitra, $kodeMitra, $token) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return ['status' => false, 'pesan' => 'Library PHPMailer tidak ditemukan.'];
    }

    // --- ISI GMAIL PENGIRIM & APP PASSWORD 16 DIGIT DI SINI ---
    $gmail_pengirim = 'raynaadevii@gmail.com'; // Ganti dengan email akun R kamu
    $app_password   = 'pjjmwjmijkzizehw';           // Ganti dengan 16 digit App Password (tanpa spasi)
    // ---------------------------------------------------------

    $mail = new PHPMailer(true);
    $linkAktivasi = "http://localhost/ticketingjmto/aktivasi_mitra.php?token=" . urlencode($token);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_pengirim;
        $mail->Password   = $app_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($gmail_pengirim, 'Admin JMTO Ticketing');
        $mail->addAddress($emailTujuan, $namaPenerima);

        $mail->isHTML(true);
        $mail->Subject = 'Undangan Aktivasi Akun Portal Mitra - JMTO Ticketing';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 580px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 26px;'>
                <h2 style='color: #004687; margin-top: 0;'>Selamat Bergabung, {$namaMitra}!</h2>
                <p style='font-size: 14px; color: #334155; line-height: 1.6;'>
                    Halo <b>{$namaPenerima}</b>,<br>
                    Admin PT Jasa Marga Tollroad Operator telah mendaftarkan instansi Anda (<b>{$namaMitra}</b> / ID: <b>{$kodeMitra}</b>) ke dalam sistem <b>JMTO Ticketing</b>.
                </p>
                <p style='font-size: 14px; color: #334155;'>
                    Silakan klik tombol di bawah ini untuk menerima undangan dan mengatur password akun portal Anda:
                </p>
                <div style='text-align: center; margin: 28px 0;'>
                    <a href='{$linkAktivasi}' style='background: #004687; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px;'>
                        Terima Undangan & Aktifkan Akun
                    </a>
                </div>
                <p style='font-size: 12px; color: #64748b;'>
                    Atau salin tautan berikut ke browser Anda:<br>
                    <a href='{$linkAktivasi}'>{$linkAktivasi}</a>
                </p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 11px; color: #94a3b8;'>Tautan berlaku selama 24 jam.</p>
            </div>";

        $mail->send();
        return ['status' => true, 'pesan' => 'Undangan berhasil dikirim ke ' . $emailTujuan];
    } catch (Exception $e) {
        return ['status' => false, 'pesan' => 'Gagal kirim email: ' . $mail->ErrorInfo];
    }
}