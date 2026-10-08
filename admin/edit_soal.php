<?php
/**
 * admin/edit_soal.php
 * ------------------------------------------------------------------
 * Halaman lama. Form edit kini berada di admin/edit_bank_soal.php
 * (mendukung kategori, tipe jawaban, dan pilihan JSON).
 * ------------------------------------------------------------------
 */
$id = (int) ($_GET['id'] ?? 0);

header('Location: edit_bank_soal.php' . ($id > 0 ? '?id=' . $id : ''), true, 302);
exit;
