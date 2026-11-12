<?php
session_start();
require 'koneksi.php';

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    $query  = "SELECT u.*, r.nama_role FROM users u LEFT JOIN roles r ON u.id_role = r.id_role WHERE u.email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

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

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak ditemukan di database!";
    }
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
</head>
<body>

    <div class="container">
        <!-- Panel Kiri: Form Login -->
        <div class="login-panel">
            <div class="logo-container">
                <img src="jasamarga-logo.png" alt="Jasamarga Toll Road Operator Logo" class="logo">
            </div>

            <h1 class="title">Login</h1>
            <p class="subtitle">Enter your email and password to access your account.</p>

            <?php if (!empty($general_err)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($general_err); ?></div>
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
                        class="<?php echo !empty($email_err) ? 'input-error' : ''; ?>"
                    >
                    <?php if (!empty($email_err)): ?>
                        <span class="error-msg"><?php echo htmlspecialchars($email_err); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Masukkan Password" 
                        class="<?php echo !empty($password_err) ? 'input-error' : ''; ?>"
                    >
                    <?php if (!empty($password_err)): ?>
                        <span class="error-msg"><?php echo htmlspecialchars($password_err); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-login">LOGIN</button>
            </form>
        </div>

        <!-- Panel Kanan: Biru Solid -->
        <div class="blue-panel"></div>
    </div>

</body>
</html>