<?php
/**
 * admin/token.php - Daftar token ujian + status pemakaian.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, SELECT token_ujian, tampilkan status/batas/jumlah dipakai
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Ujian - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Token Ujian</div></div>
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
            <div class="question-type">TOKEN UJIAN</div>
            <div class="question-text">
                Tabel token akan menampilkan: kode token, status
                (aktif/nonaktif), batas penggunaan, jumlah dipakai,
                dan masa berlaku.
            </div>
            <div class="navigation">
                <a class="btn btn-primary" href="tambah_token.php">+ Tambah Token</a>
            </div>
        </div>
    </main>
</body>
</html>
