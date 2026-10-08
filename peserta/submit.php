<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['sesi_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM sesi_ujian WHERE id = ? AND peserta_id = ?");
$stmt->execute([$_SESSION['sesi_id'], $_SESSION['peserta_id']]);
$sesi = $stmt->fetch();

if (!$sesi) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($sesi['sudah_submit']) {
    header('Location: hasil.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT jp.soal_id, jp.skor, s.dimensi_id, s.skill, d.kode AS dimensi_kode
     FROM jawaban_peserta jp
     JOIN bank_soal s ON s.id = jp.soal_id
     JOIN dimensi d ON d.id = s.dimensi_id
     WHERE jp.sesi_id = ?"
);
$stmt->execute([$_SESSION['sesi_id']]);
$jawaban = $stmt->fetchAll();

$dimScores = ['EI' => 0, 'SN' => 0, 'TF' => 0, 'JP' => 0];
$dimCount = ['EI' => 0, 'SN' => 0, 'TF' => 0, 'JP' => 0];
$skillScores = ['interpersonal' => 0, 'communication' => 0, 'emotional' => 0];
$skillCount = ['interpersonal' => 0, 'communication' => 0, 'emotional' => 0];

foreach ($jawaban as $row) {
    $dimScores[$row['dimensi_kode']] += $row['skor'];
    $dimCount[$row['dimensi_kode']]++;
    $skillScores[$row['skill']] += $row['skor'];
    $skillCount[$row['skill']]++;
}

$dimPercent = [];
foreach (['EI', 'SN', 'TF', 'JP'] as $dim) {
    $max = $dimCount[$dim] * 5;
    $dimPercent[$dim] = $max > 0 ? (int) round($dimScores[$dim] / $max * 100) : 0;
}

$skillPercent = [];
foreach (['interpersonal', 'communication', 'emotional'] as $skill) {
    $max = $skillCount[$skill] * 5;
    $skillPercent[$skill] = $max > 0 ? (int) round($skillScores[$skill] / $max * 100) : 0;
}

$kodeProfil =
    ($dimPercent['EI'] >= 50 ? 'E' : 'I') .
    ($dimPercent['SN'] >= 50 ? 'N' : 'S') .
    ($dimPercent['TF'] >= 50 ? 'F' : 'T') .
    ($dimPercent['JP'] >= 50 ? 'J' : 'P');

$totalSkor = array_sum($dimScores);
$jumlahDijawab = count($jawaban);

// Jumlah soal sesi ini (bisa kurang dari 60 bila bank soal terbatas).
$jumlahSoal = (!empty($_SESSION['soal_ids']) && is_array($_SESSION['soal_ids']))
    ? count($_SESSION['soal_ids'])
    : (int) ($sesi['jumlah_soal'] ?: EXAM_QUESTION_COUNT);

$stmt = $db->prepare(
    "INSERT INTO hasil_ujian (sesi_id, peserta_id, token_id, kode_profil, skor_interpersonal, skor_communication, skor_emotional, skor_dimensi, jumlah_dijawab, jumlah_soal, total_skor, ringkasan)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $_SESSION['sesi_id'], $_SESSION['peserta_id'], $_SESSION['token_id'],
    $kodeProfil, $skillPercent['interpersonal'], $skillPercent['communication'], $skillPercent['emotional'],
    json_encode($dimPercent), $jumlahDijawab, $jumlahSoal, $totalSkor,
    'Hasil asesmen soft skills berdasarkan ' . $jumlahDijawab . ' jawaban.'
]);

$stmt = $db->prepare("UPDATE sesi_ujian SET sudah_submit = 1, waktu_selesai = NOW(), status = 'selesai' WHERE id = ?");
$stmt->execute([$_SESSION['sesi_id']]);

header('Location: hasil.php');
exit;
