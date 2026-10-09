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

$pageTitle = 'Login Admin - Soft Skills Assessment';
$bodyClass = 'login-screen';
require __DIR__ . '/../assets/partials/head.php';
?>

    <div class="login-wrapper" style="max-width:560px;grid-template-columns:1fr;">
        <div class="login-form">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="badge !mb-0">Masuk Admin</span>

                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="clock-pill" aria-live="off">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <span class="clock-text" id="clockText">Memuat waktu...</span>
                    </span>

                    <?php $extraToggleClass = 'h-10 w-10'; require __DIR__ . '/../assets/partials/theme_toggle.php'; ?>
                </div>
            </div>

            <h2 style="margin-top:18px;">Login Pengelola</h2>
            <p class="subtitle">Masukkan username dan password admin.</p>

            <form method="post" action="login.php" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required
                           placeholder="Masukkan username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required
                           placeholder="Masukkan password">
                </div>

                <?php if ($error !== ''): ?>
                    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Masuk</button>
            </form>

            <p style="margin-top:18px;font-size:13px;color:var(--c-muted);text-align:center;">
                <a href="../peserta/login.php" style="color:var(--c-brand);font-weight:700;text-decoration:none;">
                    &larr; Kembali ke halaman peserta
                </a>
            </p>
        </div>
    </div>

<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
