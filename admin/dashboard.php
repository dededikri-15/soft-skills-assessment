<?php
/**
 * =====================================================================
 * admin/dashboard.php
 * ---------------------------------------------------------------------
 * Ringkasan: jumlah peserta, soal, token, dan hasil ujian.
 *
 * STATUS: kerangka dasar
 * =====================================================================
 */

session_start();
// TODO: if (!isset($_SESSION['admin'])) header('Location: login.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Soft Skills Assessment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <header class="exam-header">
        <div class="container exam-header-inner">
            <div>
                <div class="exam-title">Admin Panel</div>
                <div class="exam-user">Soft Skills Assessment</div>
            </div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="bank_soal.php">Bank Soal</a>
                <a href="token.php">Token</a>
                <a href="hasil.php">Hasil</a>
                <a href="login.php">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="exam-container">
        <div class="info-grid" style="margin-bottom:24px;">
            <div class="info-box"><strong>--</strong> Peserta</div>
            <div class="info-box"><strong>--</strong> Soal</div>
            <div class="info-box"><strong>--</strong> Token</div>
            <div class="info-box"><strong>--</strong> Hasil Ujian</div>
        </div>

        <div class="question-card">
            <div class="question-type">RINGKASAN</div>
            <div class="question-text">
                Dashboard ini akan menampilkan statistik dari database
                <strong>softskill</strong> setelah koneksi &amp; tabel aktif.
            </div>
        </div>
    </main>

</body>
</html>
