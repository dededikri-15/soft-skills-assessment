<?php
/**
 * =====================================================================
 * config/database.php
 * ---------------------------------------------------------------------
 * Koneksi database MySQL (Laragon) menggunakan PDO MySQL.
 *
 * PENTING:
 *   File ini TIDAK membuka koneksi saat di-include.
 *   Koneksi dibuat HANYA ketika fungsi getDB() dipanggil.
 * =====================================================================
 */

/* ---------------------------------------------------------------
   TIMEZONE
   Disamakan dengan timezone MySQL (Asia/Jakarta / WIB) agar
   date() di PHP dan NOW() di MySQL selalu menghasilkan waktu yang
   sama. Jika tidak sama, batas waktu ujian & timer jadi salah.
   --------------------------------------------------------------- */
date_default_timezone_set('Asia/Jakarta');


/* ---------------------------------------------------------------
   KONFIGURASI DATABASE
   Ubah nilai di bawah ini jika berubah pada Laragon / phpMyAdmin.
   --------------------------------------------------------------- */
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'softskill');
define('DB_USER', 'root');
define('DB_PASS', '');           // Laragon: root tanpa password
define('DB_CHARSET', 'utf8mb4');


/* ---------------------------------------------------------------
   NAMA APLIKASI & KONFIGURASI UJIAN
   --------------------------------------------------------------- */
define('APP_NAME', 'Soft Skills Assessment');
define('EXAM_QUESTION_COUNT', 60);   // jumlah soal per ujian
define('EXAM_DURATION_MINUTES', 30); // durasi ujian (menit)


/*
   Session peserta tidak boleh kedaluwarsa di tengah ujian.
   Umur session dibuat lebih lama dari durasi ujian.
   WAJIB dipanggil SEBELUM session_start() di halaman peserta,
   jadi file ini harus di-include sebelum session_start(). */
ini_set('session.gc_maxlifetime', ((int) EXAM_DURATION_MINUTES * 60) + 600);


/**
 * Mengembalikan objek koneksi PDO (singleton).
 * Dipanggil hanya di file yang benar-benar butuh database.
 *
 * @return PDO
 * @throws PDOException jika koneksi gagal
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('[DB] Koneksi gagal: ' . $e->getMessage());
        die('Koneksi database gagal. Pastikan MySQL (Laragon) berjalan '
           . 'dan database "' . DB_NAME . '" sudah dibuat.');
    }

    return $pdo;
}
