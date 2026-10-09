<?php
/**
 * admin/hasil.php - Daftar hasil ujian semua peserta.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT hasil_ujian + peserta + tipe_profil
?>
<?php
$pageTitle = 'Hasil Ujian - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Hasil Ujian';
$adminActive = 'hasil';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">HASIL UJIAN</div>
            <div class="question-text">
                Tabel hasil: nama peserta, NIM, tanggal, kode profil,
                skor Interpersonal / Communication / Emotional Intelligence.
            </div>
        </div>
    </main>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
