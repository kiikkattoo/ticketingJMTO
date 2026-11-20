<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$phpmailerBase = __DIR__ . '/PHPMailer/src/';
if (file_exists($phpmailerBase . 'PHPMailer.php')) {
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'PHPMailer.php';
    require_once $phpmailerBase . 'SMTP.php';
}

function kirimInvitationMitra($emailTujuan, $namaPenerima, $namaMitra, $kodeMitra, $token)
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return [
            'status' => false,
            'pesan'  => 'Library PHPMailer tidak ditemukan.'
        ];
    }

    $localConfigPath = __DIR__ . '/config.login.php';
    if (file_exists($localConfigPath)) {
        require_once $localConfigPath;
    }

    $gmail_pengirim = $gmail_pengirim ?? '';
    $app_password   = $app_password ?? '';

    if (empty($gmail_pengirim) || empty($app_password)) {
        return [
            'status' => false,
            'pesan'  => 'Konfigurasi email pengirim belum diatur.'
        ];
    }

    $baseUrl = defined('BASE_URL') && !empty(BASE_URL)
        ? rtrim(BASE_URL, '/') . '/'
        : 'http://localhost/ticketingjmto/';

    $linkAktivasi = $baseUrl . 'aktivasi_mitra.php?token=' . urlencode($token);

    $safeNamaPenerima = htmlspecialchars($namaPenerima, ENT_QUOTES, 'UTF-8');
    $safeNamaMitra    = htmlspecialchars($namaMitra, ENT_QUOTES, 'UTF-8');
    $safeKodeMitra    = htmlspecialchars($kodeMitra, ENT_QUOTES, 'UTF-8');
    $safeLinkAktivasi = htmlspecialchars($linkAktivasi, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_pengirim;
        $mail->Password   = $app_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($gmail_pengirim, 'Admin JMTO Ticketing');
        $mail->addAddress($emailTujuan, $namaPenerima);

        $mail->isHTML(true);
        $mail->Subject = 'Undangan Aktivasi Akun Portal Mitra JMTO Ticketing';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 26px;'>
            <h2 style='color: #004687; margin-top: 0;'>Selamat Bergabung, {$safeNamaMitra}!</h2>
            <p style='font-size: 14px; color: #334155; line-height: 1.6;'>
                Halo <b>{$safeNamaPenerima}</b>,<br>
                Admin PT Jasa Marga Tollroad Operator telah mendaftarkan instansi Anda (<b>{$safeNamaMitra}</b> / ID: <b>{$safeKodeMitra}</b>) ke dalam sistem <b>JMTO Ticketing</b>.
            </p>
            <p style='font-size: 14px; color: #334155; line-height: 1.6;'>
                Silakan klik tombol di bawah ini untuk menerima undangan dan mengatur password akun portal Anda:
            </p>
            <div style='text-align: center; margin: 28px 0;'>
                <a href='{$safeLinkAktivasi}' style='background: #004687; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;'>
                    Terima Undangan &amp; Aktifkan Akun
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b; line-height: 1.5;'>
                Atau salin tautan berikut ke browser Anda:<br>
                <a href='{$safeLinkAktivasi}' style='color: #004687; word-break: break-all;'>{$safeLinkAktivasi}</a>
            </p>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 11px; color: #94a3b8; margin-bottom: 0;'>Tautan berlaku selama 24 jam.</p>
        </div>";

        $mail->send();

        return [
            'status' => true,
            'pesan'  => 'Undangan berhasil dikirim ke ' . $emailTujuan
        ];
    } catch (Exception $e) {
        error_log('PHPMailer Error (kirimInvitationMitra): ' . $mail->ErrorInfo);

        return [
            'status' => false,
            'pesan'  => 'Gagal mengirim email undangan. Silakan periksa konfigurasi SMTP atau coba lagi.'
        ];
    }
}