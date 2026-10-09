<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['peserta_id']) || !isset($_SESSION['sesi_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT s.*, TIMESTAMPDIFF(SECOND, NOW(), s.batas_waktu) AS sisa_detik
     FROM sesi_ujian s
     WHERE s.id = ? AND s.peserta_id = ?"
);
$stmt->execute([$_SESSION['sesi_id'], $_SESSION['peserta_id']]);
$sesi = $stmt->fetch();

if (!$sesi) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($sesi['sudah_submit']) {
    header('Location: hasil.php');
    exit;
}

if ((int) $sesi['sisa_detik'] <= 0) {
    header('Location: submit.php');
    exit;
}

$pesertaNama = $_SESSION['peserta_nama'] ?? '';
$pesertaNim  = $_SESSION['peserta_nim'] ?? '';
$tokenKode   = $_SESSION['token_kode'] ?? '';

// Jumlah soal yang benar-benar tersedia (maksimal EXAM_QUESTION_COUNT)
$jumlahSoal = (int) $db->query(
    "SELECT COUNT(DISTINCT pertanyaan)
     FROM bank_soal
     WHERE status = 'aktif'
       AND pilihan_jawaban IS NOT NULL
       AND JSON_LENGTH(pilihan_jawaban) > 0"
)->fetchColumn();
$jumlahSoal = min((int) EXAM_QUESTION_COUNT, $jumlahSoal);

$subTitlePeserta = htmlspecialchars($pesertaNama, ENT_QUOTES, 'UTF-8')
    . ' &bull; ' . htmlspecialchars($pesertaNim, ENT_QUOTES, 'UTF-8');
if ($tokenKode !== '') {
    $subTitlePeserta .= ' &bull; Token: <strong style="color:var(--c-brand)">'
        . htmlspecialchars($tokenKode, ENT_QUOTES, 'UTF-8') . '</strong>';
}

$pageTitle = 'Instruksi Ujian - Soft Skills Assessment';
require __DIR__ . '/../assets/partials/head.php';

$pesertaSubtitle = $subTitlePeserta;
require __DIR__ . '/../assets/partials/header_peserta.php';
?>

<main class="container instruction-screen">
    <section class="instruction-card w-full">

        <span class="badge">Soft Skills Assessment</span>

        <h1>Petunjuk Pelaksanaan Tes</h1>

        <p>
            Tes ini dirancang untuk melihat kecenderungan respons
            mahasiswa dalam berbagai situasi interpersonal, komunikasi,
            pengelolaan emosi, dan pengambilan keputusan.
        </p>

        <div class="info-grid">
            <div class="info-box"><strong><?= (int) $jumlahSoal ?></strong>Pertanyaan</div>
            <div class="info-box"><strong><?= (int) EXAM_DURATION_MINUTES ?></strong>Menit</div>
            <div class="info-box"><strong>4</strong>Dimensi Kepribadian</div>
        </div>

        <div class="rules">
            <h3>Yang perlu diperhatikan</h3>

            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Bacalah setiap situasi dengan teliti.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Pilih respons yang paling menggambarkan diri kamu.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Pada soal yang membolehkan lebih dari satu jawaban, centang semua yang sesuai.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Tidak ada jawaban yang sepenuhnya benar atau salah pada pertanyaan profil.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Paket soal dan urutan pilihan jawaban diacak &mdash; <strong style="color:var(--c-text)">berbeda untuk setiap peserta</strong>.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Isian jawaban bervariasi: pilihan ganda, daftar (dropdown), centang lebih dari satu, dan skala 1&ndash;5.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Jangan meninggalkan halaman selama ujian berlangsung.</span></div>
            <div class="rule-item"><span class="rule-check">&#10003;</span><span>Setiap token hanya dapat digunakan sesuai aturan yang ditentukan.</span></div>
        </div>

        <div class="notice mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" style="padding:18px 20px;">
            <div class="flex items-start gap-3">
                <span class="rule-check" style="width:32px;height:32px;border-radius:10px;font-size:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div>
                    <p style="font-weight:800;">Timer mulai berjalan setelah kamu masuk halaman ujian</p>
                    <p style="font-size:13px;margin-top:3px;opacity:.85;">Pastikan koneksi internet stabil sebelum memulai.</p>
                </div>
            </div>

            <div class="flex flex-none flex-wrap items-center gap-3">
                <a href="login.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                    Kembali
                </a>

                <a href="ujian.php" class="btn btn-next">
                    Lanjut
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>

    </section>
</main>

<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>

</body>
</html>
