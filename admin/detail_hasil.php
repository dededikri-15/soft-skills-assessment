<?php
/**
 * admin/detail_hasil.php - Detail hasil satu peserta.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, ambil ?id= hasil_ujian + jawaban_peserta
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Hasil - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Detail Hasil</div></div>
            <nav class="admin-nav"><a href="hasil.php">Kembali ke Hasil</a></nav>
        </div>
    </header>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">DETAIL HASIL</div>
            <div class="question-text">
                Detail peserta, skor per dimensi, ringkasan profil,
                dan daftar jawaban akan ditampilkan di sini.
            </div>
            <div class="navigation">
                <a class="btn btn-secondary" href="hasil.php">Kembali</a>
            </div>
        </div>
    </main>
</body>
</html>
