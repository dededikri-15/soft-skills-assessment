<?php
/**
 * admin/hasil.php - Daftar hasil ujian semua peserta.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT hasil_ujian + peserta + tipe_profil
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Ujian - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Hasil Ujian</div></div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="bank_soal.php">Bank Soal</a>
                <a href="token.php">Token</a>
                <a href="hasil.php">Hasil</a>
            </nav>
        </div>
    </header>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">HASIL UJIAN</div>
            <div class="question-text">
                Tabel hasil: nama peserta, NIM, tanggal, kode profil,
                skor Interpersonal / Communication / Emotional Intelligence.
            </div>
        </div>
    </main>
</body>
</html>
