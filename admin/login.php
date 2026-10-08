<?php
/**
 * =====================================================================
 * admin/login.php
 * ---------------------------------------------------------------------
 * Login pengelola/pengawas ujian.
 * Password diverifikasi dengan password_verify() terhadap
 * kolom password_hash di tabel pengguna_admin.
 *
 * STATUS: kerangka dasar
 * =====================================================================
 */

session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: prepared statement SELECT * FROM pengguna_admin WHERE username = ?
    // TODO: password_verify($_POST['password'], $row['password_hash'])
    // TODO: set session admin -> header('Location: dashboard.php')
    $error = 'Login admin belum aktif. Kerangka file ini menyusul di tahap berikutnya.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Soft Skills Assessment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-screen">
    <div class="login-wrapper" style="max-width:520px;grid-template-columns:1fr;">
        <div class="login-form">
            <h2>Login Pengelola</h2>
            <p class="subtitle">Masukkan username dan password admin.</p>

            <form method="post" action="login.php" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>
