<?php
require_once __DIR__ . '/../config/database.php';
session_start();

$nama  = '';
$nim   = '';
$token = '';
$error = '';

/*
 * Endpoint JSON untuk tombol "Reload".
 * Mengambil SATU token yang masih berlaku dari database, sehingga
 * token yang muncul pasti bisa diverifikasi (bukan token acak).
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ambil_token'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT kode_token FROM token_ujian
             WHERE status = 'aktif'
               AND jumlah_dipakai < batas_penggunaan
               AND (berlaku_mulai IS NULL OR berlaku_mulai <= NOW())
               AND (berlaku_sampai IS NULL OR berlaku_sampai >= NOW())
             ORDER BY RAND() LIMIT 1"
        );
        $stmt->execute();
        $tokenTersedia = $stmt->fetchColumn();

        // Kalau semua token sudah habis (kuota penuh / kedaluwarsa),
        // buat token baru otomatis supaya peserta selalu bisa login.
        if (!$tokenTersedia) {
            for ($coba = 0; $coba < 5 && !$tokenTersedia; $coba++) {
                $kodeBaru = strtoupper(bin2hex(random_bytes(4)));
                $stmt = $db->prepare(
                    "INSERT INTO token_ujian
                        (kode_token, status, batas_penggunaan, jumlah_dipakai,
                         berlaku_mulai, berlaku_sampai, keterangan)
                     VALUES (?, 'aktif', 4294967295, 0, NOW(), NULL,
                             'Token otomatis (tak terbatas) dari halaman peserta')"
                );
                try {
                    $stmt->execute([$kodeBaru]);
                    $tokenTersedia = $kodeBaru;
                } catch (PDOException $e) {
                    // Kode bentrok (unique key) -> coba kode lain.
                }
            }
        }

        echo $tokenTersedia
            ? json_encode(['ok' => true, 'token' => $tokenTersedia])
            : json_encode(['ok' => false, 'message' => 'Token gagal dibuat otomatis. Hubungi pengawas.']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Gagal mengambil token dari server. Coba lagi.']);
    }
    exit;
}

/*
 * Halaman login SELALU menampilkan form (nama, NIM, token).
 * Kalau peserta masih punya sesi ujian yang berjalan, tampilkan banner
 * "Lanjutkan Ujian" supaya bisa kembali tanpa kehilangan sesi.
 */
$sesiLanjut = null;

if (isset($_SESSION['peserta_id'], $_SESSION['sesi_id'])) {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT s.id, s.batas_waktu, s.sudah_submit,
                TIMESTAMPDIFF(SECOND, NOW(), s.batas_waktu) AS sisa_detik,
                p.nama, p.nim
         FROM sesi_ujian s
         JOIN peserta p ON p.id = s.peserta_id
         WHERE s.id = ? AND s.peserta_id = ?"
    );
    $stmt->execute([$_SESSION['sesi_id'], $_SESSION['peserta_id']]);
    $sesi = $stmt->fetch();

    if ($sesi && !$sesi['sudah_submit'] && (int) $sesi['sisa_detik'] > 0) {
        $sesiLanjut = $sesi;
    } else {
        // Ujian sudah selesai / habis waktu -> bersihkan sesi.
        $_SESSION = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama'] ?? '');
    $nim   = trim($_POST['nim'] ?? '');
    $token = strtoupper(trim($_POST['token'] ?? ''));

    if ($nama === '' || $nim === '' || $token === '') {
        $error = 'Nama Lengkap, NIM, dan Token Ujian wajib diisi.';
    } elseif (stripos($token, 'CONTOH') !== false) {
        $error = 'Isikan hanya kode tokennya saja, contohnya EQ2026A (tanpa kata CONTOH).';
    } elseif (mb_strlen($nama) < 2 || mb_strlen($nim) < 2) {
        $error = 'Nama Lengkap dan NIM minimal 2 karakter.';
    } else {
        $db = getDB();

        // Waktu server diambil dari MySQL supaya validasi masa berlaku token akurat.
        $stmt = $db->prepare("SELECT *, NOW() AS waktu_server FROM token_ujian WHERE kode_token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            $error = 'Token ujian tidak ditemukan. Periksa kembali token yang diberikan.';
        } elseif ($tokenData['status'] !== 'aktif') {
            $error = 'Token ujian sudah nonaktif dan tidak dapat digunakan.';
        } elseif ($tokenData['berlaku_mulai'] !== null && $tokenData['waktu_server'] < $tokenData['berlaku_mulai']) {
            $error = 'Token ujian belum berlaku. Gunakan token pada waktunya.';
        } elseif ($tokenData['berlaku_sampai'] !== null && $tokenData['waktu_server'] > $tokenData['berlaku_sampai']) {
            $error = 'Token ujian sudah kedaluwarsa dan tidak dapat digunakan.';
        } elseif ((int) $tokenData['jumlah_dipakai'] >= (int) $tokenData['batas_penggunaan']) {
            $error = 'Token ujian sudah mencapai batas penggunaan.';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO peserta (nama, nim) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nama = VALUES(nama)"
            );
            $stmt->execute([$nama, $nim]);

            $stmt = $db->prepare("SELECT id FROM peserta WHERE nim = ?");
            $stmt->execute([$nim]);
            $pesertaId = (int) $stmt->fetch()['id'];

            $kodeSesi = strtoupper(bin2hex(random_bytes(6)));
            $durasi   = (int) EXAM_DURATION_MINUTES;

            // Jumlah soal sesi = jumlah soal unik yang tersedia di bank soal.
            $jumlahSoal = (int) $db->query(
                "SELECT COUNT(DISTINCT pertanyaan)
                 FROM bank_soal
                 WHERE status = 'aktif'
                   AND pilihan_jawaban IS NOT NULL
                   AND JSON_LENGTH(pilihan_jawaban) > 0"
            )->fetchColumn();
            $jumlahSoal = min((int) EXAM_QUESTION_COUNT, $jumlahSoal);

            $stmt = $db->prepare(
                "INSERT INTO sesi_ujian (kode_sesi, peserta_id, token_id, jumlah_soal, durasi_menit, waktu_mulai, batas_waktu, ip_address)
                 VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL " . $durasi . " MINUTE), ?)"
            );
            $stmt->execute([
                $kodeSesi, $pesertaId, $tokenData['id'],
                $jumlahSoal, $durasi,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $sesiId = (int) $db->lastInsertId();

            $stmt = $db->prepare("UPDATE token_ujian SET jumlah_dipakai = jumlah_dipakai + 1 WHERE id = ? AND jumlah_dipakai < batas_penggunaan");
            $stmt->execute([$tokenData['id']]);

            // Data peserta disimpan di session agar tidak perlu diisi ulang.
            $_SESSION['peserta_id']   = $pesertaId;
            $_SESSION['sesi_id']      = $sesiId;
            $_SESSION['token_id']     = (int) $tokenData['id'];
            $_SESSION['token_kode']   = $token;
            $_SESSION['peserta_nama'] = $nama;
            $_SESSION['peserta_nim']  = $nim;
            $_SESSION['soal_ids']     = [];

            header('Location: instruksi.php');
            exit;
        }
    }
}

$pageTitle = 'Login Peserta - Soft Skills Assessment';
$bodyClass = 'login-screen';
require __DIR__ . '/../assets/partials/head.php';
?>

    <div class="login-wrapper">

        <!-- Panel kiri: profil aplikasi -->
        <div class="login-info">
            <div class="logo-circle">&#129504;</div>

            <h1>Soft Skills<br>Assessment</h1>
            <p>
                Sistem ujian kemampuan interpersonal, komunikasi,
                kecerdasan emosional, dan profil kecenderungan
                kepribadian mahasiswa.
            </p>

            <div class="feature-list">
                <div class="feature">
                    <span class="feature-icon">&#10003;</span>
                    Situational Judgment Test (SJT)
                </div>
                <div class="feature">
                    <span class="feature-icon">&#10003;</span>
                    Emotional Intelligence Assessment
                </div>
                <div class="feature">
                    <span class="feature-icon">&#10003;</span>
                    Communication &amp; Interpersonal Skills
                </div>
                <div class="feature">
                    <span class="feature-icon">&#10003;</span>
                    Profil kepribadian dan kompetensi
                </div>
            </div>

            <div class="pill-note">
                60 soal <span class="opacity-60">&bull;</span> 30 menit
                <span class="opacity-60">&bull;</span> paket soal berbeda tiap peserta
            </div>
        </div>

        <!-- Panel kanan: form login -->
        <div class="login-form">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="badge !mb-0">Masuk Ujian</span>

                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="clock-pill" aria-live="off">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <span class="clock-text" id="clockText">Memuat waktu...</span>
                    </span>

                    <?php $extraToggleClass = 'h-10 w-10'; require __DIR__ . '/../assets/partials/theme_toggle.php'; ?>
                </div>
            </div>

            <h2 class="mt-4">Mulai Assessment</h2>
            <p class="subtitle" style="margin-top:6px;">Masukkan identitas dan token ujian yang telah diberikan.</p>

            <?php if ($sesiLanjut): $sisa = max(0, (int) $sesiLanjut['sisa_detik']); ?>
            <div class="notice success flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div style="font-weight:800;">Sesi ujian kamu masih berjalan</div>
                    <div style="font-size:13px;margin-top:3px;">
                        <strong><?= htmlspecialchars($sesiLanjut['nama'], ENT_QUOTES, 'UTF-8') ?></strong>
                        &bull; <?= htmlspecialchars($sesiLanjut['nim'], ENT_QUOTES, 'UTF-8') ?>
                        &bull; sisa waktu <strong><?= sprintf('%02d:%02d', intdiv($sisa, 60), $sisa % 60) ?></strong>
                    </div>
                </div>
                <a href="instruksi.php" class="btn btn-next" style="padding:11px 20px;font-size:14px;">
                    Lanjutkan Ujian
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="off" style="margin-top:14px;">

                <div class="form-group">
                    <label for="studentName">Nama Lengkap</label>
                    <input type="text" class="field" name="nama" id="studentName"
                           placeholder="Masukkan nama lengkap" required
                           value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="studentNim">NIM</label>
                    <input type="text" class="field" name="nim" id="studentNim"
                           placeholder="Masukkan NIM" required
                           value="<?= htmlspecialchars($nim, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="examToken">Token Ujian</label>
                    <div class="flex gap-2">
                        <input type="text" class="field token-input min-w-0 flex-1"
                               name="token" id="examToken" maxlength="30"
                               placeholder="CONTOH: EQ2026A" required
                               value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" id="reloadTokenBtn" title="Ambil token yang masih berlaku"
                                class="btn btn-secondary" style="padding:0 16px;white-space:nowrap;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                            Reload
                        </button>
                    </div>
                </div>

                <div class="error" id="loginError"<?= $error === '' ? ' style="display:none"' : '' ?>><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

                <button type="submit" class="btn btn-primary">Verifikasi &amp; Lanjut</button>

                <p class="mt-5 flex items-start gap-2.5 rounded-xl text-muted"
                   style="background:var(--c-surface);border:1px solid var(--c-line);border-radius:14px;padding:12px 14px;font-size:13px;line-height:1.65;margin-top:20px;">
                    <svg class="mt-0.5 flex-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--c-brand)"><path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
                    <span>Setiap peserta mendapat <strong style="color:var(--c-text)">paket soal yang berbeda</strong> (60 soal) &mdash; diacak otomatis saat login.</span>
                </p>

            </form>
        </div>

    </div>

    <script>
    (function () {
        const errorBox   = document.getElementById('loginError');
        const tokenInput = document.getElementById('examToken');
        const reloadBtn  = document.getElementById('reloadTokenBtn');

        function showError(message) {
            errorBox.textContent = message;
            errorBox.style.display = '';
        }

        reloadBtn.addEventListener('click', function () {
            reloadBtn.disabled = true;
            reloadBtn.textContent = '...';

            fetch('login.php?ambil_token=1', { cache: 'no-store' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok && data.token) {
                        tokenInput.value = data.token;
                        tokenInput.focus();
                        errorBox.style.display = 'none';
                    } else {
                        showError(data.message || 'Token tidak tersedia.');
                    }
                })
                .catch(function () {
                    showError('Gagal mengambil token dari server. Coba lagi.');
                })
                .finally(function () {
                    reloadBtn.disabled = false;
                    reloadBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg> Reload';
                });
        });
    })();
    </script>
    <script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>

</body>
</html>
