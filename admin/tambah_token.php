<?php
/**
 * admin/tambah_token.php - Membuat token ujian baru.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, INSERT token_ujian (kode, batas, masa berlaku)
?>
<?php
$pageTitle = 'Tambah Token - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Tambah Token';
$adminActive = 'token';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">FORM TOKEN</div>
            <div class="question-text">
                Form akan berisi: kode token, batas penggunaan,
                tanggal berlaku mulai &amp; sampai, serta keterangan.
            </div>
            <div class="navigation">
                <a class="btn btn-secondary" href="token.php">Batal</a>
            </div>
        </div>
    </main>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
