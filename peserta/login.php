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
 * "Lanjutkan Ujian" (tombol Next) supaya bisa kembali tanpa kehilangan
 * sesi. Bila ujian sudah selesai / habis waktu, sesi dibersihkan agar
 * peserta berikutnya bisa login.
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Peserta - Soft Skills Assessment</title>
    <script>
    try {
        var t = localStorage.getItem('theme');
        if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {}
    </script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/tailwind.css">
</head>
<body class="login-screen">

    <div class="relative w-full max-w-5xl overflow-hidden rounded-[28px] bg-white shadow-[0_25px_70px_rgba(40,40,80,.14)] ring-1 ring-slate-200/70 md:grid md:grid-cols-2 dark:bg-slate-900 dark:shadow-2xl dark:ring-slate-700">

        <!-- Panel kiri: profil aplikasi -->
        <div class="relative overflow-hidden bg-gradient-to-br from-brand-600 via-brand-600 to-violet-600 p-8 text-white sm:p-10">
            <div class="pointer-events-none absolute -right-16 -bottom-24 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -right-4 top-28 h-32 w-32 rounded-full bg-white/10"></div>

            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 text-2xl shadow-lg">&#129504;</div>

            <h1 class="mt-6 text-[34px] font-extrabold leading-[1.12] tracking-tight">Soft Skills<br>Assessment</h1>
            <p class="mt-4 max-w-sm text-[15px] leading-relaxed text-white/85">
                Sistem ujian kemampuan interpersonal, komunikasi,
                kecerdasan emosional, dan profil kecenderungan
                kepribadian mahasiswa.
            </p>

            <ul class="mt-6 space-y-3 text-[14.5px] font-medium text-white/95">
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-white/20 text-[12px] font-bold">&#10003;</span>
                    Situational Judgment Test (SJT)
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-white/20 text-[12px] font-bold">&#10003;</span>
                    Emotional Intelligence Assessment
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-white/20 text-[12px] font-bold">&#10003;</span>
                    Communication &amp; Interpersonal Skills
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-white/20 text-[12px] font-bold">&#10003;</span>
                    Profil kepribadian dan kompetensi
                </li>
            </ul>

            <div class="mt-7 inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full bg-white/15 px-4 py-2 text-[13px] font-bold ring-1 ring-white/25">
                60 soal <span class="opacity-60">&bull;</span> 30 menit
                <span class="opacity-60">&bull;</span> paket soal berbeda tiap peserta
            </div>
        </div>

        <!-- Panel kanan: form login -->
        <div class="p-7 sm:p-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="inline-block rounded-full bg-brand-100 px-3.5 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.18em] text-brand-700 dark:bg-brand-900/60 dark:text-brand-300">
                    Masuk Ujian
                </span>

                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="clock-pill" id="liveClock" aria-live="off">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <span id="clockText">Memuat waktu...</span>
                    </span>

                    <button type="button" id="themeToggle" aria-label="Ganti tema terang / gelap" aria-pressed="false"
                            class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        <svg class="icon-sun h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                        <svg class="icon-moon hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    </button>
                </div>
            </div>

            <h2 class="mt-4 text-[26px] font-extrabold tracking-tight text-slate-900 dark:text-white">Mulai Assessment</h2>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Masukkan identitas dan token ujian yang telah diberikan.</p>

            <?php if ($sesiLanjut): $sisa = max(0, (int) $sesiLanjut['sisa_detik']); ?>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 dark:border-emerald-800 dark:bg-emerald-900/40">
                <div class="min-w-0">
                    <p class="text-[13.5px] font-bold text-emerald-700 dark:text-emerald-300">Sesi ujian kamu masih berjalan</p>
                    <p class="mt-0.5 text-[13px] leading-relaxed text-emerald-700/80 dark:text-emerald-300/80">
                        <strong><?= htmlspecialchars($sesiLanjut['nama'], ENT_QUOTES, 'UTF-8') ?></strong>
                        &bull; <?= htmlspecialchars($sesiLanjut['nim'], ENT_QUOTES, 'UTF-8') ?>
                        &bull; sisa waktu <strong><?= sprintf('%02d:%02d', intdiv($sisa, 60), $sisa % 60) ?></strong>
                    </p>
                </div>
                <a href="instruksi.php"
                   class="inline-flex flex-none items-center gap-2 rounded-xl bg-gradient-to-r from-brand-600 to-violet-600 px-5 py-2.5 text-[14px] font-bold text-white shadow-md shadow-brand-600/25 transition hover:-translate-y-0.5 dark:from-brand-500 dark:to-violet-500">
                    Lanjutkan Ujian
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="off" class="mt-7">

                <div class="mb-4">
                    <label for="studentName" class="mb-2 block text-[13px] font-bold text-slate-700 dark:text-slate-300">Nama Lengkap</label>
                    <input type="text" name="nama" id="studentName" placeholder="Masukkan nama lengkap"
                           value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>" required
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-[15px] text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                </div>

                <div class="mb-4">
                    <label for="studentNim" class="mb-2 block text-[13px] font-bold text-slate-700 dark:text-slate-300">NIM</label>
                    <input type="text" name="nim" id="studentNim" placeholder="Masukkan NIM"
                           value="<?= htmlspecialchars($nim, ENT_QUOTES, 'UTF-8') ?>" required
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-[15px] text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
                </div>

                <div class="mb-4">
                    <label for="examToken" class="mb-2 block text-[13px] font-bold text-slate-700 dark:text-slate-300">Token Ujian</label>
                    <div class="flex gap-2">
                        <input type="text" name="token" id="examToken" class="token-input min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-[15px] text-slate-800 outline-none transition placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 placeholder:normal-case focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                               maxlength="30" placeholder="CONTOH: EQ2026A" required
                               value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" id="reloadTokenBtn" title="Ambil token yang masih berlaku"
                                class="inline-flex flex-none items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-100 px-4 text-sm font-bold text-slate-700 transition hover:border-brand-400 hover:text-brand-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-500 dark:hover:text-brand-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                            Reload
                        </button>
                    </div>
                </div>

                <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-600 dark:bg-red-950/60 dark:text-red-300" id="loginError"<?= $error === '' ? ' style="display:none"' : '' ?>><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

                <button type="submit" class="btn btn-primary">Verifikasi &amp; Lanjut</button>

                <p class="mt-5 flex items-start gap-2.5 rounded-xl bg-slate-50 px-4 py-3 text-[13px] leading-relaxed text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <svg class="mt-0.5 h-4 w-4 flex-none text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
                    <span>Setiap peserta mendapat <strong class="font-bold text-slate-700 dark:text-slate-200">paket soal yang berbeda</strong> (60 soal) &mdash; diacak otomatis saat login.</span>
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
                    reloadBtn.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg> Reload';
                });
        });

        // Jam: hari, tanggal, bulan, tahun + jam (bahasa Indonesia, update tiap detik)
        (function () {
            var el = document.getElementById('clockText');
            if (!el) return;
            var hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            var bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            var pad   = function (n) { return n < 10 ? '0' + n : '' + n; };
            var tick  = function () {
                var d = new Date();
                el.textContent = hari[d.getDay()] + ', ' + pad(d.getDate()) + ' ' + bulan[d.getMonth()]
                    + ' ' + d.getFullYear() + ' \u2022 ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            };
            tick();
            setInterval(tick, 1000);
        })();

        // Mode terang / gelap (preferensi disimpan di localStorage)
        (function () {
            var root = document.documentElement;
            var btn  = document.getElementById('themeToggle');

            function apply() {
                if (!btn) return;
                var dark = root.classList.contains('dark');
                btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
                var sun  = btn.querySelector('.icon-sun');
                var moon = btn.querySelector('.icon-moon');
                if (sun)  sun.classList.toggle('hidden', dark);
                if (moon) moon.classList.toggle('hidden', !dark);
            }

            apply();

            if (btn) {
                btn.addEventListener('click', function () {
                    root.classList.toggle('dark');
                    try {
                        localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
                    } catch (e) {}
                    apply();
                });
            }
        })();
    })();
    </script>

</body>
</html>
