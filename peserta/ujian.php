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

/**
 * Jumlah soal unik (teks berbeda) yang aktif & punya pilihan jawaban.
 * Dipakai untuk membatasi jumlah soal bila bank soal kurang dari 60.
 */
function jumlahSoalTersedia(PDO $db): int
{
    static $jumlah = null;

    if ($jumlah !== null) {
        return $jumlah;
    }

    $stmt = $db->query(
        "SELECT COUNT(DISTINCT pertanyaan)
         FROM bank_soal
         WHERE status = 'aktif'
           AND pilihan_jawaban IS NOT NULL
           AND JSON_LENGTH(pilihan_jawaban) > 0"
    );
    $jumlah = (int) $stmt->fetchColumn();

    return $jumlah;
}

function targetJumlahSoal(PDO $db): int
{
    return min((int) EXAM_QUESTION_COUNT, jumlahSoalTersedia($db));
}

/** Acak array dengan Fisher-Yates memakai random_int (bukan mt_rand). */
function acakArray(array $items): array
{
    $n = count($items);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }

    return $items;
}

/**
 * Urutan pilihan jawaban dibuat berbeda untuk tiap peserta
 * (disimpan sekali di session supaya tidak berubah saat refresh).
 * Soal skala (scale) tidak diacak agar urutannya tetap masuk akal.
 */
function urutPilihanSesi(int $soalId, string $tipe, array $pilihan): array
{
    if ($tipe === 'scale' || count($pilihan) < 2) {
        return $pilihan;
    }

    $kunci     = (string) $soalId;
    $tersimpan = $_SESSION['opsi_urut'][$kunci] ?? [];
    $index     = [];
    foreach ($pilihan as $p) {
        $index[(int) $p['id']] = $p;
    }

    if ($tersimpan) {
        $urut = [];
        foreach ($tersimpan as $id) {
            if (isset($index[$id])) {
                $urut[] = $index[$id];
                unset($index[$id]);
            }
        }
        // Pilihan baru dari admin ditaruh di akhir.
        foreach ($index as $p) {
            $urut[] = $p;
        }

        return $urut;
    }

    $urut = acakArray($pilihan);
    $_SESSION['opsi_urut'][$kunci] = array_map(
        static function ($p) {
            return (int) $p['id'];
        },
        $urut
    );

    return $urut;
}

/** Ambil baris soal sesuai daftar id, urutan mengikuti $soalIds. */
function ambilBarisSoal(PDO $db, array $soalIds): array
{
    if (!$soalIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($soalIds), '?'));
    $fieldList    = implode(',', $soalIds);

    $stmt = $db->prepare(
        "SELECT s.id, s.pertanyaan, s.tipe_soal, s.kategori, s.petunjuk,
                s.pilihan_jawaban, d.kode AS dimensi_kode
         FROM bank_soal s
         JOIN dimensi d ON d.id = s.dimensi_id
         WHERE s.status = 'aktif'
           AND s.id IN ($placeholders)
           AND s.pilihan_jawaban IS NOT NULL
           AND JSON_LENGTH(s.pilihan_jawaban) > 0
         ORDER BY FIELD(s.id, $fieldList)"
    );
    $stmt->execute($soalIds);

    return $stmt->fetchAll();
}

/**
 * Daftar soal untuk sesi berjalan.
 * - Sekali dipilih disimpan di $_SESSION['soal_ids'], sehingga urutan
 *   dan soal tetap sama saat halaman dimuat ulang (tanpa randomisasi
 *   ulang pada tombol Berikutnya / refresh).
 * - Teks soal yang sama tidak boleh muncul dua kali dalam satu ujian.
 */
function loadSoalList(PDO $db): array
{
    $target = targetJumlahSoal($db);

    if (!empty($_SESSION['soal_ids']) && is_array($_SESSION['soal_ids'])) {
        $soalIds = array_map('intval', $_SESSION['soal_ids']);

        if (count($soalIds) === $target && count(array_unique($soalIds)) === $target) {
            $rows = ambilBarisSoal($db, $soalIds);
            if (count($rows) === $target) {
                return $rows;
            }
        }
    }

    // Pertama kali (atau ada soal yang dinonaktifkan) -> acak sekali.
    $kandidat = $db->query(
        "SELECT id, pertanyaan
         FROM bank_soal
         WHERE status = 'aktif'
           AND pilihan_jawaban IS NOT NULL
           AND JSON_LENGTH(pilihan_jawaban) > 0"
    )->fetchAll();

    $kandidat = acakArray($kandidat);

    $soalIds  = [];
    $terpakai = [];
    foreach ($kandidat as $row) {
        if (isset($terpakai[$row['pertanyaan']])) {
            continue;
        }
        $terpakai[$row['pertanyaan']] = true;
        $soalIds[] = (int) $row['id'];

        if (count($soalIds) === $target) {
            break;
        }
    }

    if (count($soalIds) < $target) {
        return ambilBarisSoal($db, $soalIds);
    }

    $_SESSION['soal_ids'] = $soalIds;

    return ambilBarisSoal($db, $soalIds);
}

$soalList  = loadSoalList($db);
$jumlahSoal = count($soalList);

if ($jumlahSoal < targetJumlahSoal($db)) {
    die(
        'Jumlah soal aktif di bank soal tidak mencukupi. Dibutuhkan '
        . targetJumlahSoal($db) . ' soal unik, tersedia ' . jumlahSoalTersedia($db) . '.'
    );
}

// Jawaban tersimpan (array id opsi, mendukung pilihan ganda)
$stmt = $db->prepare("SELECT soal_id, pilihan_id, pilihan_json FROM jawaban_peserta WHERE sesi_id = ?");
$stmt->execute([$_SESSION['sesi_id']]);
$existingAnswers = [];
foreach ($stmt->fetchAll() as $row) {
    $ids = json_decode((string) $row['pilihan_json'], true);
    if (!is_array($ids) || !$ids) {
        $ids = $row['pilihan_id'] !== null ? [(int) $row['pilihan_id']] : [];
    }
    $existingAnswers[(int) $row['soal_id']] = array_map('intval', $ids);
}

$questionsData = [];
foreach ($soalList as $s) {
    $pilihan = json_decode((string) $s['pilihan_jawaban'], true);
    if (!is_array($pilihan)) {
        $pilihan = [];
    }
    usort($pilihan, static function ($a, $b) {
        return ($a['urutan'] ?? 0) <=> ($b['urutan'] ?? 0);
    });
    $pilihan = urutPilihanSesi((int) $s['id'], (string) $s['tipe_soal'], $pilihan);

    $questionsData[] = [
        'id'       => (int) $s['id'],
        'teks'     => $s['pertanyaan'],
        'tipe'     => $s['tipe_soal'],
        'kategori' => $s['kategori'],
        'petunjuk' => $s['petunjuk'],
        'dimensi'  => $s['dimensi_kode'],
        'pilihan'  => array_map(static function ($p) {
            return [
                'id'    => (int) $p['id'],
                'teks'  => (string) $p['teks'],
                'bobot' => (int) $p['bobot'],
            ];
        }, $pilihan),
    ];
}

$batasWaktu = $sesi['batas_waktu'];
$sisaDetik  = max(0, (int) $sesi['sisa_detik']);
$timerAwal  = sprintf('%02d:%02d', intdiv($sisaDetik, 60), $sisaDetik % 60);
$pesertaNama = $_SESSION['peserta_nama'] ?? '';
$pesertaNim = $_SESSION['peserta_nim'] ?? '';
?>

<?php
$pageTitle = 'Ujian - Soft Skills Assessment';
require __DIR__ . '/../assets/partials/head.php';

$pesertaSubtitle = htmlspecialchars($pesertaNama, ENT_QUOTES, 'UTF-8')
    . ' &bull; ' . htmlspecialchars($pesertaNim, ENT_QUOTES, 'UTF-8');
$showClock = false;
$headerExtra = '
    <div class="exam-note" id="acakInfo" title="Paket soal diacak otomatis untuk setiap peserta">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/>
        </svg>
        <span class="exam-note-text">Paket soal berbeda untuk tiap peserta</span>
    </div>';
$headerRight = '<div class="timer" id="timer">' . $timerAwal . '</div>';
require __DIR__ . '/../assets/partials/header_peserta.php';
?>
<div class="progress-area">
    <div class="container">
        <div class="progress-top">
            <span id="progressText">Pertanyaan 1 dari <?= count($questionsData) ?></span>
            <span class="progress-percent" id="progressPercent">1%</span>
        </div>
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width:1%"></div>
        </div>
    </div>
</div>

<main class="exam-container">

    <?php if (isset($_GET['acak'])): ?>
    <div class="notice success" role="status">
        Paket soal baru sudah diacak ulang &mdash; soal dan urutan pilihan kini berbeda dengan peserta lain.
    </div>
    <?php endif; ?>

    <section class="question-card">
        <div class="question-type" id="questionType">-</div>
        <div class="question-number" id="questionNumber">Pertanyaan 1</div>
        <h2 class="question-text" id="questionText">Memuat soal...</h2>
        <p class="question-hint" id="questionHint"></p>

        <div class="option-list" id="optionList"></div>

        <div class="navigation">
            <button id="prevBtn" class="btn btn-secondary" type="button">&#8592; Sebelumnya</button>
            <div class="navigation-right">
                <button id="finishBtn" class="btn btn-finish" type="button">Selesaikan Tes &#10003;</button>
                <button id="nextBtn" class="btn btn-next" type="button">Berikutnya &#8594;</button>
            </div>
        </div>
    </section>

    <section class="qnav-card">
        <div class="qnav-head">
            <span class="qnav-title">Navigasi Soal</span>
            <span class="qnav-legend">
                <span class="legend-item"><i class="legend-dot active"></i>Sedang dijawab</span>
                <span class="legend-item"><i class="legend-dot answered"></i>Terjawab</span>
                <span class="legend-item"><i class="legend-dot"></i>Belum dijawab</span>
            </span>
        </div>
        <div class="question-nav" id="questionNav"></div>
    </section>

</main>

<script>
window.ExamConfig = <?= json_encode([
    'totalQuestions'   => count($questionsData),
    'durationMinutes'  => (int) EXAM_DURATION_MINUTES,
    'serverEndTime'    => $batasWaktu,
    'remainingSeconds' => $sisaDetik,
    'questions'        => $questionsData,
    'existingAnswers'  => $existingAnswers,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
<script src="../assets/js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
