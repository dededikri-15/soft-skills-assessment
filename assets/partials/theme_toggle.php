<?php
/**
 * assets/partials/theme_toggle.php
 * ------------------------------------------------------------------
 * Tombol ganti tema terang / gelap (id="themeToggle") - sekali pakai.
 * Id-nya wajib unik di satu halaman; dipetakan oleh assets/js/ui.js.
 *
 * Opsional: $toggleLabel  -> tampilkan teks di samping ikon (default: ikon saja)
 * ------------------------------------------------------------------
 */

$toggleLabel = $toggleLabel ?? '';
$extraToggleClass = $extraToggleClass ?? '';
?>
<button type="button" id="themeToggle"
        aria-label="Ganti tema terang / gelap" aria-pressed="false"
        title="Mode terang / gelap"
        class="header-btn header-btn-icon <?= htmlspecialchars($extraToggleClass, ENT_QUOTES, 'UTF-8') ?>">
    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>
    <svg class="icon-moon hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
    </svg>
    <?php if ($toggleLabel !== ''): ?>
        <span class="btn-label"><?= htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
</button>
