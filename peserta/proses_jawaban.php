<?php
require_once __DIR__ . '/../config/database.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['sesi_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Sesi tidak valid.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'message' => 'Data jawaban tidak valid.']);
    exit;
}

$soalId = (int) ($input['soal_id'] ?? 0);

// Mendukung dua format: tunggal (pilihan_id) dan ganda (pilihan_ids).
$pilihanIds = [];
if (isset($input['pilihan_ids']) && is_array($input['pilihan_ids'])) {
    foreach ($input['pilihan_ids'] as $id) {
        $pilihanIds[] = (int) $id;
    }
} elseif (isset($input['pilihan_id'])) {
    $pilihanIds[] = (int) $input['pilihan_id'];
}

$pilihanIds = array_values(array_unique(array_filter($pilihanIds, static function ($id) {
    return $id > 0;
})));

if ($soalId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Data jawaban tidak valid.']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM sesi_ujian WHERE id = ? AND sudah_submit = 0 AND batas_waktu > NOW()");
$stmt->execute([$_SESSION['sesi_id']]);
$sesi = $stmt->fetch();

if (!$sesi) {
    echo json_encode(['ok' => false, 'message' => 'Sesi sudah ditutup atau kedaluwarsa.']);
    exit;
}

// Soal wajib bagian dari paket soal sesi ini.
if (!empty($_SESSION['soal_ids']) && is_array($_SESSION['soal_ids'])) {
    if (!in_array($soalId, array_map('intval', $_SESSION['soal_ids']), true)) {
        echo json_encode(['ok' => false, 'message' => 'Soal di luar paket ujian sesi ini.']);
        exit;
    }
}

$stmt = $db->prepare("SELECT id, tipe_soal, pilihan_jawaban FROM bank_soal WHERE id = ? AND status = 'aktif'");
$stmt->execute([$soalId]);
$soal = $stmt->fetch();

if (!$soal) {
    echo json_encode(['ok' => false, 'message' => 'Soal tidak valid.']);
    exit;
}

$opsi = json_decode((string) $soal['pilihan_jawaban'], true);
if (!is_array($opsi)) {
    $opsi = [];
}

$opsiById = [];
foreach ($opsi as $o) {
    $opsiById[(int) $o['id']] = $o;
}

$tipe  = $soal['tipe_soal'];
$multi = ($tipe === 'checkbox');

// Kosongkan jawaban (mis. semua centang dicabut kembali)
if ($multi && !$pilihanIds) {
    $stmt = $db->prepare("DELETE FROM jawaban_peserta WHERE sesi_id = ? AND soal_id = ?");
    $stmt->execute([$_SESSION['sesi_id'], $soalId]);
    echo json_encode(['ok' => true, 'message' => 'Jawaban dikosongkan.']);
    exit;
}

if (!$pilihanIds) {
    echo json_encode(['ok' => false, 'message' => 'Data jawaban tidak valid.']);
    exit;
}

if (!$multi && count($pilihanIds) !== 1) {
    echo json_encode(['ok' => false, 'message' => 'Soal ini hanya boleh dijawab satu pilihan.']);
    exit;
}

$bobotTerpilih = [];
foreach ($pilihanIds as $id) {
    if (!isset($opsiById[$id])) {
        echo json_encode(['ok' => false, 'message' => 'Pilihan jawaban tidak valid.']);
        exit;
    }
    $bobotTerpilih[] = (int) $opsiById[$id]['bobot'];
}

if ($multi) {
    // Pilihan ganda: skor = rata-rata bobot pilihan terpilih (dibulatkan, 1-5)
    $skor = (int) round(array_sum($bobotTerpilih) / count($bobotTerpilih));
    $skor = max(1, min(5, $skor));
    $nilai = null;
} else {
    // Tunggal: skor = bobot pilihan (skala menyimpan nilai skornya)
    $skor  = $bobotTerpilih[0];
    $nilai = ($tipe === 'scale') ? $skor : null;
}

$stmt = $db->prepare(
    "INSERT INTO jawaban_peserta (sesi_id, soal_id, pilihan_id, pilihan_json, nilai, skor)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE pilihan_id = VALUES(pilihan_id),
                             pilihan_json = VALUES(pilihan_json),
                             nilai = VALUES(nilai),
                             skor = VALUES(skor)"
);
$stmt->execute([
    $_SESSION['sesi_id'],
    $soalId,
    $pilihanIds[0],
    json_encode(array_map('intval', $pilihanIds)),
    $nilai,
    $skor,
]);

echo json_encode(['ok' => true, 'message' => 'Jawaban tersimpan.']);
