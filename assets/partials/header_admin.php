<?php
/**
 * assets/partials/header_admin.php
 * ------------------------------------------------------------------
 * Topbar standar halaman admin (sticky) - dipakai semua halaman di
 * folder admin/ supaya tampilannya identik.
 *
 * Variabel:
 *   $adminSubtitle (string, wajib)  -> mis. "Bank Soal"
 *   $adminActive   (string, opsional) -> 'dashboard'|'bank_soal'|'token'|'hasil'
 *
 * Pakai setelah head.php:
 *   <?php require __DIR__ . '/../assets/partials/header_admin.php'; ?>
 * ------------------------------------------------------------------
 */

$adminSubtitle = $adminSubtitle ?? '';
$adminActive   = $adminActive ?? '';

$menuAdmin = [
    'dashboard' => ['dashboard.php', 'Dashboard'],
    'bank_soal' => ['bank_soal.php', 'Bank Soal'],
    'token'     => ['token.php', 'Token'],
    'hasil'     => ['hasil.php', 'Hasil'],
];
?>
<header class="exam-header">
    <div class="container exam-header-inner">
        <div class="exam-brand">
            <div class="exam-logo">&#9998;</div>
            <div class="min-w-0">
                <div class="exam-title">Admin Panel</div>
                <div class="exam-user"><?= htmlspecialchars($adminSubtitle, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="exam-actions">
            <nav class="admin-nav">
                <?php foreach ($menuAdmin as $key => $item): ?>
                    <a href="<?= $item[0] ?>"<?= $adminActive === $key ? ' class="active" aria-current="page"' : '' ?>>
                        <?= $item[1] ?>
                    </a>
                <?php endforeach; ?>
                <a href="login.php">Keluar</a>
            </nav>

            <span class="clock-pill" aria-live="off">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span class="clock-text" id="clockText">Memuat waktu...</span>
            </span>

            <?php require __DIR__ . '/theme_toggle.php'; ?>
        </div>
    </div>
</header>
