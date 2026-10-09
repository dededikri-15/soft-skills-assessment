<?php
/**
 * assets/partials/header_peserta.php
 * ------------------------------------------------------------------
 * Topbar standar halaman peserta (instruksi, ujian, hasil).
 *
 * Variabel:
 *   $pesertaTitle    (string) -> judul merk, default "Soft Skills Assessment"
 *   $pesertaSubtitle (string) -> baris bawah, mis. "Nama - NIM"
 *   $showClock       (bool)   -> tampilkan jam (default true)
 *   $headerExtra     (string) -> HTML sebelum jam (mis. info paket acak)
 *   $headerRight     (string) -> HTML sesudah jam, sebelum tombol tema (mis. timer)
 *
 * Pakai setelah head.php:
 *   <?php require __DIR__ . '/../assets/partials/header_peserta.php'; ?>
 * ------------------------------------------------------------------
 */

$pesertaTitle    = $pesertaTitle ?? 'Soft Skills Assessment';
$pesertaSubtitle = $pesertaSubtitle ?? '';
$showClock       = $showClock ?? true;
$headerExtra     = $headerExtra ?? '';
$headerRight     = $headerRight ?? '';
?>
<header class="exam-header">
    <div class="container exam-header-inner">
        <div class="exam-brand">
            <div class="exam-logo">&#9998;</div>
            <div class="min-w-0">
                <div class="exam-title"><?= htmlspecialchars($pesertaTitle, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($pesertaSubtitle !== ''): ?>
                    <div class="exam-user"><?= $pesertaSubtitle ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="exam-actions">
            <?= $headerExtra ?>
            <?= $headerRight ?>

            <?php if ($showClock): ?>
                <span class="clock-pill" aria-live="off">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span class="clock-text" id="clockText">Memuat waktu...</span>
                </span>
            <?php endif; ?>

            <?php require __DIR__ . '/theme_toggle.php'; ?>
        </div>
    </div>
</header>
