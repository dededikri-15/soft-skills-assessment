<?php
/**
 * admin/token.php - Daftar token ujian + status pemakaian.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT token_ujian, tampilkan status/batas/jumlah dipakai
?>
<?php
$pageTitle = 'Token Ujian - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Token Ujian';
$adminActive = 'token';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">TOKEN UJIAN</div>
            <div class="question-text">
                Tabel token akan menampilkan: kode token, status
                (aktif/nonaktif), batas penggunaan, jumlah dipakai,
                dan masa berlaku.
            </div>
            <div class="navigation">
                <a class="btn btn-primary" href="tambah_token.php">+ Tambah Token</a>
            </div>
        </div>
    </main>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
