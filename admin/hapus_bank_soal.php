<?php
/**
 * admin/hapus_bank_soal.php
 * ------------------------------------------------------------------
 * Konfirmasi + penghapusan soal dari bank soal.
 *   GET  ?id=<id>   -> halaman konfirmasi
 *   POST konfirmasi -> hapus (atau nonaktifkan bila soal sudah
 *                      punya jawaban peserta, karena FK mencegah
 *                      penghapusan)
 *
 * CATATAN: gerbang login admin belum aktif, konsisten dengan
 * halaman admin lainnya.
 */
require_once __DIR__ . '/../config/database.php';
session_start();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: bank_soal.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare(
    "SELECT s.id, s.pertanyaan, s.kategori, s.tipe_soal, s.status,
            JSON_LENGTH(s.pilihan_jawaban) AS jml_pilihan
     FROM bank_soal s
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$soal = $stmt->fetch();

if (!$soal) {
    header('Location: bank_soal.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $konfirmasi = (string) ($_POST['konfirmasi'] ?? '');

    if ($konfirmasi === 'ya') {
        try {
            $db->prepare("DELETE FROM bank_soal WHERE id = ?")->execute([$id]);
            header('Location: bank_soal.php?msg=hapus_ok');
            exit;
        } catch (PDOException $e) {
            // Ada jawaban peserta yang menunjuk soal ini (FK RESTRICT)
            // -> jadikan nonaktif supaya tidak lagi dipakai ujian.
            try {
                $db->prepare("UPDATE bank_soal SET status = 'nonaktif' WHERE id = ?")->execute([$id]);
                header('Location: bank_soal.php?msg=hapus_nonaktif');
                exit;
            } catch (PDOException $e2) {
                error_log('[bank_soal] gagal hapus id ' . $id . ': ' . $e->getMessage());
                header('Location: bank_soal.php?msg=hapus_gagal');
                exit;
            }
        }
    }

    header('Location: bank_soal.php?msg=hapus_batal');
    exit;
}
?>
<?php
$pageTitle = 'Hapus Soal - Admin';
require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = 'Hapus Soal #' . (int) $id;
$adminActive = 'bank_soal';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">KONFIRMASI HAPUS</div>

            <div class="question-text" style="margin-bottom:14px;">
                Yakin ingin menghapus soal berikut dari bank soal?
                Tindakan ini tidak bisa dibatalkan.
            </div>

            <div class="notice">
                <strong>Soal #<?= (int) $soal['id'] ?></strong>
                &mdash; <?= htmlspecialchars($soal['kategori'], ENT_QUOTES, 'UTF-8') ?>
                (<?= htmlspecialchars($soal['tipe_soal'], ENT_QUOTES, 'UTF-8') ?>,
                <?= (int) $soal['jml_pilihan'] ?> pilihan,
                status <?= htmlspecialchars($soal['status'], ENT_QUOTES, 'UTF-8') ?>)
                <div style="margin-top:8px;font-weight:600;">
                    "<?= htmlspecialchars(mb_substr($soal['pertanyaan'], 0, 220) . (mb_strlen($soal['pertanyaan']) > 220 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>"
                </div>
            </div>

            <p style="color:var(--muted);font-size:14px;line-height:1.6;">
                Jika soal ini sudah pernah dijawab peserta, sistem akan otomatis
                menonaktifkannya (status <strong>nonaktif</strong>) agar tidak
                mengganggu data hasil ujian yang sudah tersimpan.
            </p>

            <div class="form-actions">
                <form method="post" action="hapus_bank_soal.php">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="konfirmasi" value="ya">
                    <button class="btn btn-submit" type="submit">Ya, Hapus</button>
                </form>
                <form method="post" action="hapus_bank_soal.php">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="konfirmasi" value="batal">
                    <button class="btn btn-secondary" type="submit">Batal</button>
                </form>
                <a class="btn btn-secondary" href="bank_soal.php">Kembali ke Daftar</a>
            </div>
        </div>
    </main>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
