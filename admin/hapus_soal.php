<?php
/**
 * admin/hapus_soal.php
 * ------------------------------------------------------------------
 * Halaman lama. Penghapusan kini berada di admin/hapus_bank_soal.php
 * (konfirmasi dulu, dan otomatis menonaktifkan bila soal sudah
 * dijawab peserta).
 * ------------------------------------------------------------------
 */
$id = (int) ($_GET['id'] ?? 0);

header('Location: hapus_bank_soal.php' . ($id > 0 ? '?id=' . $id : ''), true, 302);
exit;
