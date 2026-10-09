<?php
/**
 * admin/detail_hasil.php - Detail hasil satu peserta.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, ambil ?id= hasil_ujian + jawaban_peserta
?>
<?php
$pageTitle = 'Detail Hasil - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Detail Hasil';
$adminActive = 'hasil';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

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
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
