<?php
/**
 * assets/partials/head.php
 * ------------------------------------------------------------------
 * Kepala halaman standar untuk SEMUA halaman (admin & peserta):
 * doctype, meta, anti-flash tema, dan stylesheet Tailwind.
 *
 * Variabel yang dipakai:
 *   $pageTitle  (string, wajib) -> judul tab
 *   $extraHead  (string, opsional) -> tag tambahan sebelum </head>
 *   $bodyClass  (string, opsional) -> class tambahan pada <body>
 *
 * Pakai:
 *   <?php $pageTitle = 'Judul'; require __DIR__ . '/../assets/partials/head.php'; ?>
 *   ...
 *   </body>
 *   </html>
 * ------------------------------------------------------------------
 */

$pageTitle = $pageTitle ?? 'Soft Skills Assessment';
$extraHead = $extraHead ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <script>
    /* Terapkan tema tersimpan SEBELUM render supaya tidak berkedip. */
    try {
        var t = localStorage.getItem('theme');
        if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {}
    </script>
    <?php
    /* Versi = waktu ubah file -> browser selalu unduh CSS terbaru
       setelah build ulang Tailwind (mencegah tampilan tanpa gaya). */
    $cssFile = __DIR__ . '/../css/tailwind.css';
    $cssVer  = @filemtime($cssFile) ?: 1;
    ?>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=<?= (int) $cssVer ?>">
    <?= $extraHead ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
