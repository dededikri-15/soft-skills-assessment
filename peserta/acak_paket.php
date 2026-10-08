<?php
/**
 * Acak ulang paket soal untuk peserta yang sedang ujian.
 * Menghapus jawaban tersimpan + paket lama, lalu kembali ke ujian.php
 * (soal baru dipilih ulang secara acak dari bank soal).
 */
require_once __DIR__ . '/../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ujian.php');
    exit;
}

if (!isset($_SESSION['peserta_id'], $_SESSION['sesi_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT sudah_submit FROM sesi_ujian WHERE id = ? AND peserta_id = ?"
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

// Bersihkan jawaban sesi + paket lama supaya paket baru diacak saat ujian.php dimuat.
$stmt = $db->prepare('DELETE FROM jawaban_peserta WHERE sesi_id = ?');
$stmt->execute([$_SESSION['sesi_id']]);

unset($_SESSION['soal_ids'], $_SESSION['opsi_urut']);
$_SESSION['acak_paket'] = ((int) ($_SESSION['acak_paket'] ?? 0)) + 1;

header('Location: ujian.php?acak=1');
exit;
