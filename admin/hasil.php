<?php
/**
 * admin/hasil.php - Daftar hasil ujian + pintu detail satu peserta.
 *   hasil.php              -> daftar hasil (kerangka dasar)
 *   hasil.php?detail=<id>  -> detail hasil (kerangka dasar)
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT hasil_ujian + peserta + tipe_profil; ?id= untuk detail

$mode = isset($_GET['detail']) ? 'detail' : 'daftar';
$id   = $mode === 'detail' ? (int) $_GET['detail'] : 0;
?>
<?php
$pageTitle = $mode === 'detail' ? 'Detail Hasil - Admin' : 'Hasil Ujian - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = $mode === 'detail' ? 'Detail Hasil' : 'Hasil Ujian';
$adminActive = 'hasil';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

<?php if ($mode === 'detail'): ?>
    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">DETAIL HASIL</div>
            <div class="question-text">
                Detail peserta, skor per dimensi, ringkasan profil,
                dan daftar jawaban akan ditampilkan di sini.
            </div>
            <div class="navigation">
                <a class="btn btn-secondary" href="hasil.php">Kembali</a>
            </div>
        </div>
    </main>
<?php else: ?>
    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">HASIL UJIAN</div>
            <div class="question-text">
                Tabel hasil: nama peserta, NIM, tanggal, kode profil,
                skor Interpersonal / Communication / Emotional Intelligence.
            </div>
        </div>
    </main>
<?php endif; ?>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
