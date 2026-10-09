<?php
/**
 * =====================================================================
 * admin/dashboard.php
 * ---------------------------------------------------------------------
 * Ringkasan: jumlah peserta, soal, token, dan hasil ujian.
 *
 * STATUS: kerangka dasar
 * =====================================================================
 */

session_start();
// TODO: if (!isset($_SESSION['admin'])) header('Location: login.php');
?>
<?php
$pageTitle = 'Dashboard Admin - Soft Skills Assessment';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Ringkasan & Statistik';
$adminActive = 'dashboard';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

    <main class="exam-container">
        <div class="info-grid" style="margin-bottom:24px;">
            <div class="info-box"><strong>--</strong> Peserta</div>
            <div class="info-box"><strong>--</strong> Soal</div>
            <div class="info-box"><strong>--</strong> Token</div>
            <div class="info-box"><strong>--</strong> Hasil Ujian</div>
        </div>

        <div class="question-card">
            <div class="question-type">RINGKASAN</div>
            <div class="question-text">
                Dashboard ini akan menampilkan statistik dari database
                <strong>softskill</strong> setelah koneksi &amp; tabel aktif.
            </div>
        </div>
    </main>

<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
