<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['sesi_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare(
    "SELECT h.*, p.nama, p.nim, p.kelas, tp.label AS profil_label, tp.judul AS profil_judul,
            tp.deskripsi AS profil_deskripsi, tp.deskripsi_panjang, tp.kelebihan, tp.perlu_dikembangkan, tp.pengembangan
     FROM hasil_ujian h
     JOIN peserta p ON p.id = h.peserta_id
     JOIN tipe_profil tp ON tp.kode = h.kode_profil
     WHERE h.sesi_id = ?"
);
$stmt->execute([$_SESSION['sesi_id']]);
$hasil = $stmt->fetch();

if (!$hasil) {
    header('Location: ujian.php');
    exit;
}

$kelebihan = json_decode($hasil['kelebihan'], true) ?: [];
$perluDikembangkan = json_decode($hasil['perlu_dikembangkan'], true) ?: [];
$skorDimensi = json_decode($hasil['skor_dimensi'], true) ?: [];

// Identitas peserta diambil dari session (dari hasil verifikasi token di login).
$namaPeserta = $_SESSION['peserta_nama'] ?? $hasil['nama'];
$nimPeserta  = $_SESSION['peserta_nim'] ?? $hasil['nim'];
$tokenPeserta = $_SESSION['token_kode'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil - Soft Skills Assessment</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <section class="screen result-screen">
        <div class="container">

            <div class="result-header">
                <span class="badge">ASSESSMENT RESULT</span>
                <h1>Hasil Assessment</h1>
                <p id="resultStudent"><?= htmlspecialchars($namaPeserta . ' • ' . $nimPeserta, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($tokenPeserta !== ''): ?>
                        &nbsp;&bull;&nbsp; Token: <?= htmlspecialchars($tokenPeserta, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </p>
            </div>

            <p class="disclaimer">
                Hasil berikut merupakan <strong>hasil asesmen</strong>, bukan diagnosis
                psikologis. Gambaran disusun berdasarkan jawaban yang diberikan peserta
                dan dapat digunakan sebagai bahan refleksi serta
                <strong>Modul 5 Core Competency</strong>.
            </p>

            <div class="personality-card">
                <div>
                    <div class="type-code" id="personalityCode"><?= htmlspecialchars($hasil['kode_profil'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="type-label" id="personalityLabel"><?= htmlspecialchars($hasil['profil_label'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <h2 id="personalityTitle"><?= htmlspecialchars($hasil['profil_judul'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p id="personalityDescription"><?= htmlspecialchars($hasil['profil_deskripsi'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="dimension-grid">
                <div class="dimension">
                    <div class="dimension-name">ENERGY</div>
                    <div class="dimension-value" id="eiValue"><?= (int)($skorDimensi['EI'] ?? 0) ?>% <?= ($skorDimensi['EI'] ?? 0) >= 50 ? 'Extravert' : 'Introvert' ?></div>
                    <div class="dimension-bar"><div class="dimension-fill" id="eiBar" style="width:<?= (int)($skorDimensi['EI'] ?? 0) ?>%"></div></div>
                </div>
                <div class="dimension">
                    <div class="dimension-name">INFORMATION</div>
                    <div class="dimension-value" id="snValue"><?= (int)($skorDimensi['SN'] ?? 0) ?>% <?= ($skorDimensi['SN'] ?? 0) >= 50 ? 'Intuitive' : 'Sensing' ?></div>
                    <div class="dimension-bar"><div class="dimension-fill" id="snBar" style="width:<?= (int)($skorDimensi['SN'] ?? 0) ?>%"></div></div>
                </div>
                <div class="dimension">
                    <div class="dimension-name">DECISION</div>
                    <div class="dimension-value" id="tfValue"><?= (int)($skorDimensi['TF'] ?? 0) ?>% <?= ($skorDimensi['TF'] ?? 0) >= 50 ? 'Feeling' : 'Thinking' ?></div>
                    <div class="dimension-bar"><div class="dimension-fill" id="tfBar" style="width:<?= (int)($skorDimensi['TF'] ?? 0) ?>%"></div></div>
                </div>
                <div class="dimension">
                    <div class="dimension-name">LIFESTYLE</div>
                    <div class="dimension-value" id="jpValue"><?= (int)($skorDimensi['JP'] ?? 0) ?>% <?= ($skorDimensi['JP'] ?? 0) >= 50 ? 'Judging' : 'Perceiving' ?></div>
                    <div class="dimension-bar"><div class="dimension-fill" id="jpBar" style="width:<?= (int)($skorDimensi['JP'] ?? 0) ?>%"></div></div>
                </div>
            </div>

            <div class="result-grid">
                <div class="result-card">
                    <h3>Profil Soft Skills</h3>

                    <div class="skill">
                        <div class="skill-top"><span>Interpersonal</span><span id="interpersonalScore"><?= (int) $hasil['skor_interpersonal'] ?>%</span></div>
                        <div class="skill-bar"><div class="skill-fill" id="interpersonalBar" style="width:<?= (int) $hasil['skor_interpersonal'] ?>%"></div></div>
                    </div>

                    <div class="skill">
                        <div class="skill-top"><span>Communication</span><span id="communicationScore"><?= (int) $hasil['skor_communication'] ?>%</span></div>
                        <div class="skill-bar"><div class="skill-fill" id="communicationBar" style="width:<?= (int) $hasil['skor_communication'] ?>%"></div></div>
                    </div>

                    <div class="skill">
                        <div class="skill-top"><span>Emotional Intelligence</span><span id="emotionalScore"><?= (int) $hasil['skor_emotional'] ?>%</span></div>
                        <div class="skill-bar"><div class="skill-fill" id="emotionalBar" style="width:<?= (int) $hasil['skor_emotional'] ?>%"></div></div>
                    </div>
                </div>

                <div class="result-card">
                    <h3>Radar Chart</h3>
                    <div class="radar-box">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="result-card" style="margin-top:20px;">
                <h3>Gambaran Kepribadian</h3>
                <p style="line-height:1.8;color:#686b7b;"><?= htmlspecialchars($hasil['deskripsi_panjang'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="result-card" style="margin-top:20px;">
                <h3>Kelebihan dan Hal yang Perlu Dikembangkan</h3>
                <div class="pros-cons">
                    <div class="pros">
                        <h4>&#10003; Kelebihan</h4>
                        <ul>
                            <?php foreach ($kelebihan as $k): ?>
                                <li><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="cons">
                        <h4>&#9651; Hal yang Perlu Dikembangkan</h4>
                        <ul>
                            <?php foreach ($perluDikembangkan as $p): ?>
                                <li><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="result-card" style="margin-top:20px;">
                <h3>Rekomendasi Pengembangan</h3>
                <div class="development"><?= htmlspecialchars($hasil['pengembangan'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div class="result-actions">
                <button class="btn btn-primary" style="width:auto;" onclick="window.print()">Cetak Hasil</button>
            </div>

        </div>
    </section>

    <script>
    const skillData = {
        interpersonal: <?= (int) $hasil['skor_interpersonal'] ?>,
        communication: <?= (int) $hasil['skor_communication'] ?>,
        emotional: <?= (int) $hasil['skor_emotional'] ?>
    };

    const ctx = document.getElementById('radarChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Interpersonal', 'Communication', 'Emotional Intelligence'],
            datasets: [{
                label: 'Soft Skills',
                data: [skillData.interpersonal, skillData.communication, skillData.emotional],
                backgroundColor: 'rgba(91, 91, 214, 0.2)',
                borderColor: 'rgba(91, 91, 214, 1)',
                borderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: { min: 0, max: 100, ticks: { stepSize: 20 } }
            },
            plugins: { legend: { display: false } }
        }
    });
    </script>
</body>
</html>
