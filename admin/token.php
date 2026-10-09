<?php
/**
 * admin/token.php - Daftar token ujian + pintu form tambah token.
 *   token.php           -> daftar token (kerangka dasar)
 *   token.php?tambah=1   -> form token baru (kerangka dasar)
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT token_ujian, INSERT token_ujian (kode, batas, masa berlaku)

$mode = isset($_GET['tambah']) ? 'tambah' : 'daftar';
?>
<?php
$pageTitle = $mode === 'tambah' ? 'Tambah Token - Admin' : 'Token Ujian - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = $mode === 'tambah' ? 'Tambah Token' : 'Token Ujian';
$adminActive = 'token';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

<?php if ($mode === 'tambah'): ?>
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
<?php else: ?>
    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">TOKEN UJIAN</div>
            <div class="question-text">
                Tabel token akan menampilkan: kode token, status
                (aktif/nonaktif), batas penggunaan, jumlah dipakai,
                dan masa berlaku.
            </div>
            <div class="navigation">
                <a class="btn btn-primary" href="token.php?tambah=1">+ Tambah Token</a>
            </div>
        </div>
    </main>
<?php endif; ?>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
