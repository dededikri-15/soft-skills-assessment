<?php
/**
 * peserta/logout.php
 * ------------------------------------------------------------------
 * Keluar dari sesi ujian (tahap akhir / kapan pun).
 * Menghapus seluruh session peserta sehingga peserta berikutnya
 * bisa login tanpa menabrak sesi lama, lalu diarahkan ke login.
 * ------------------------------------------------------------------
 */

session_start();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
