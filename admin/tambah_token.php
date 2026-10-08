<?php
/**
 * admin/tambah_token.php - Membuat token ujian baru.
 * STATUS: kerangka dasar.
 */
session_start();
// TODO: auth admin, INSERT token_ujian (kode, batas, masa berlaku)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Token - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Tambah Token</div></div>
            <nav class="admin-nav"><a href="token.php">Kembali ke Token</a></nav>
        </div>
    </header>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">FORM TOKEN</div>
            <div class="question-text">
                Form akan berisi: kode token, batas penggunaan,
                tanggal berlaku mulai &amp; sampai, serta keterangan.
            </div>
            <div class="navigation">
                <a class="btn btn-secondary" href="token.php">Batal</a>
            </div>
        </div>
    </main>
</body>
</html>
