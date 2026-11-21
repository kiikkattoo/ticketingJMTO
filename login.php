<?php
session_start();
require_once __DIR__ . '/koneksi.php';

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (strtolower($_SESSION["nama_role"] ?? '') === 'mitra') {
        header("Location: mitra/dashboard.php");
    } else {
        header("Location: admin/dashboard.php");
    }
    exit();
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Prepared statement (aman dari SQL injection)
    $stmt = mysqli_prepare(
        $conn,
        "SELECT u.*, r.nama_role
         FROM users u
         LEFT JOIN roles r ON u.id_role = r.id_role
         WHERE u.email = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['status_user'] === 'Inactive') {
            $error = "Akun Anda berstatus Inactive. Hubungi Super Admin.";
        } elseif ($password === $row['password'] || password_verify($password, $row['password'])) {
            $_SESSION["loggedin"]  = true;
            $_SESSION["id_user"]   = $row['id'];
            $_SESSION["kode_user"] = $row['kode_user'];
            $_SESSION["nama_user"] = $row['nama_user'];
            $_SESSION["email"]     = $row['email'];
            $_SESSION["id_role"]   = $row['id_role'];
            $_SESSION["nama_role"] = $row['nama_role'];
            $_SESSION["id_mitra"]  = $row['id_mitra'];

            if (strtolower($row['nama_role'] ?? '') === 'mitra') {
                header("Location: mitra/dashboard.php");
            } else {
                header("Location: admin/dashboard.php");
            }
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak ditemukan di database!";
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dashboard</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS tambahan untuk ikon mata (tidak perlu edit style.css) -->
    <style>
        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 48px; /* ruang untuk ikon */
            box-sizing: border-box;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: #004a87;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Panel Kiri: Form Login -->
        <div class="login-panel">
            <div class="logo-container">
                <img src="img/jasamarga-logo.png" alt="Jasamarga Toll Road Operator Logo" class="logo">
            </div>

            <h1 class="title">Login</h1>
            <p class="subtitle">Enter your email and password to access your account.</p>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan Email"
                        value="<?php echo htmlspecialchars($email); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan Password"
                        >
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Tampilkan password">
                            <!-- Ikon mata (password tersembunyi) -->
                            <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <!-- Ikon mata dicoret (password terlihat) -->
                            <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">LOGIN</button>
            </form>
        </div>

        <!-- Panel Kanan: Biru Solid -->
        <div class="blue-panel"></div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const toggleBtn     = document.getElementById('togglePassword');
        const iconEye       = document.getElementById('iconEye');
        const iconEyeOff    = document.getElementById('iconEyeOff');

        toggleBtn.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';

            passwordInput.type       = isHidden ? 'text' : 'password';
            iconEye.style.display    = isHidden ? 'none'  : 'block';
            iconEyeOff.style.display = isHidden ? 'block' : 'none';
            toggleBtn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
        });
    </script>

</body>
</html>