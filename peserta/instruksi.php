<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['peserta_id']) || !isset($_SESSION['sesi_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT s.*, TIMESTAMPDIFF(SECOND, NOW(), s.batas_waktu) AS sisa_detik
     FROM sesi_ujian s
     WHERE s.id = ? AND s.peserta_id = ?"
);
$stmt->execute([$_SESSION['sesi_id'], $_SESSION['peserta_id']]);
$sesi = $stmt->fetch();

if (!$sesi) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($sesi['sudah_submit']) {
    header('Location: hasil.php');
    exit;
}

if ((int) $sesi['sisa_detik'] <= 0) {
    header('Location: submit.php');
    exit;
}

$pesertaNama = $_SESSION['peserta_nama'] ?? '';
$pesertaNim  = $_SESSION['peserta_nim'] ?? '';
$tokenKode   = $_SESSION['token_kode'] ?? '';

// Jumlah soal yang benar-benar tersedia (maksimal EXAM_QUESTION_COUNT)
$jumlahSoal = (int) $db->query(
    "SELECT COUNT(DISTINCT pertanyaan)
     FROM bank_soal
     WHERE status = 'aktif'
       AND pilihan_jawaban IS NOT NULL
       AND JSON_LENGTH(pilihan_jawaban) > 0"
)->fetchColumn();
$jumlahSoal = min((int) EXAM_QUESTION_COUNT, $jumlahSoal);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Ujian - Soft Skills Assessment</title>
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
<body class="bg-slate-50 text-slate-800 antialiased dark:bg-[#0b1020] dark:text-slate-200">

    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="relative w-full max-w-3xl">
            <div class="pointer-events-none absolute -top-10 -left-10 h-40 w-40 rounded-full bg-brand-300/30 blur-3xl dark:bg-brand-700/30"></div>

            <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-xl sm:p-10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-2xl">

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <span class="inline-block rounded-full bg-brand-100 px-3.5 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.18em] text-brand-700 dark:bg-brand-900/60 dark:text-brand-300">
                        Soft Skills Assessment
                    </span>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="clock-pill" id="liveClock" aria-live="off">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <span id="clockText">Memuat waktu...</span>
                        </span>

                        <button type="button" id="themeToggle" aria-label="Ganti tema terang / gelap" aria-pressed="false"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                            <svg class="icon-sun h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                            <svg class="icon-moon hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        </button>
                    </div>
                </div>

                <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                    Petunjuk Pelaksanaan Tes
                </h1>

                <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-slate-400">
                    Peserta: <strong class="text-slate-800 dark:text-slate-200"><?= htmlspecialchars($pesertaNama, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="mx-1">&bull;</span>
                    <?= htmlspecialchars($pesertaNim, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($tokenKode !== ''): ?>
                        <span class="mx-1">&bull;</span>
                        Token: <strong class="text-brand-600 dark:text-brand-300"><?= htmlspecialchars($tokenKode, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php endif; ?>
                </p>

                <p class="mt-5 leading-relaxed text-slate-600 dark:text-slate-300">
                    Tes ini dirancang untuk melihat kecenderungan respons
                    mahasiswa dalam berbagai situasi interpersonal, komunikasi,
                    pengelolaan emosi, dan pengambilan keputusan.
                </p>

                <div class="mt-7 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-5 text-center dark:border-slate-700 dark:bg-slate-800/70">
                        <strong class="block text-4xl font-extrabold text-brand-600 dark:text-brand-300"><?= (int) $jumlahSoal ?></strong>
                        <span class="mt-1 block text-sm font-semibold text-slate-500 dark:text-slate-400">Pertanyaan</span>
                    </div>
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-5 text-center dark:border-slate-700 dark:bg-slate-800/70">
                        <strong class="block text-4xl font-extrabold text-brand-600 dark:text-brand-300"><?= (int) EXAM_DURATION_MINUTES ?></strong>
                        <span class="mt-1 block text-sm font-semibold text-slate-500 dark:text-slate-400">Menit</span>
                    </div>
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-5 text-center dark:border-slate-700 dark:bg-slate-800/70">
                        <strong class="block text-4xl font-extrabold text-brand-600 dark:text-brand-300">4</strong>
                        <span class="mt-1 block text-sm font-semibold text-slate-500 dark:text-slate-400">Dimensi Kepribadian</span>
                    </div>
                </div>

                <div class="mt-8">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Yang perlu diperhatikan</h2>
                    <ul class="mt-3 space-y-2.5">
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Bacalah setiap situasi dengan teliti.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Pilih respons yang paling menggambarkan diri kamu.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Pada soal yang membolehkan lebih dari satu jawaban, centang semua yang sesuai.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Tidak ada jawaban yang sepenuhnya benar atau salah pada pertanyaan profil.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            <span>Paket soal dan urutan pilihan jawaban diacak &mdash; <strong class="font-bold text-slate-800 dark:text-slate-100">berbeda untuk setiap peserta</strong>.</span>
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Isian jawaban bervariasi: pilihan ganda, daftar (dropdown), centang lebih dari satu, dan skala 1&ndash;5.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Jangan meninggalkan halaman selama ujian berlangsung.
                        </li>
                        <li class="flex gap-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 dark:bg-brand-900/70 dark:text-brand-300">&#10003;</span>
                            Setiap token hanya dapat digunakan sesuai aturan yang ditentukan.
                        </li>
                    </ul>
                </div>

                <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 sm:px-6 sm:py-5 dark:border-slate-700 dark:bg-slate-800/60">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-brand-100 text-brand-600 dark:bg-brand-900/60 dark:text-brand-300">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </span>
                            <div>
                                <p class="text-[13.5px] font-bold text-slate-700 dark:text-slate-200">Timer mulai berjalan setelah kamu masuk halaman ujian</p>
                                <p class="mt-0.5 text-[13px] text-slate-500 dark:text-slate-400">Pastikan koneksi internet stabil sebelum memulai.</p>
                            </div>
                        </div>

                        <div class="flex flex-none items-center gap-3">
                            <a href="login.php"
                               class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-5 py-3.5 text-[15px] font-bold text-slate-700 transition hover:border-brand-400 hover:text-brand-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-brand-500 dark:hover:text-brand-300">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                                Kembali
                            </a>

                            <a href="ujian.php"
                               class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-600 to-violet-600 px-7 py-3.5 text-[15px] font-bold text-white shadow-lg shadow-brand-600/30 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-brand-600/40 dark:from-brand-500 dark:to-violet-500">
                                Lanjut
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <script>
    (function () {
        // Jam: hari, tanggal, bulan, tahun + jam (bahasa Indonesia, update tiap detik)
        var el = document.getElementById('clockText');
        if (el) {
            var hari   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            var bulan  = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            var pad    = function (n) { return n < 10 ? '0' + n : '' + n; };
            var tick   = function () {
                var d = new Date();
                el.textContent = hari[d.getDay()] + ', ' + pad(d.getDate()) + ' ' + bulan[d.getMonth()]
                    + ' ' + d.getFullYear() + ' \u2022 ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            };
            tick();
            setInterval(tick, 1000);
        }

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
    </script>

</body>
</html>
