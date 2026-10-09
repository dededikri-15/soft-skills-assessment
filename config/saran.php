<?php
/**
 * Penyusun saran pengembangan personal berdasarkan hasil assessment.
 *
 * Masukan : skor dimensi (EI/SN/TF/JP dalam 0-100) dan skor soft skills.
 * Keluaran: array kartu  -> [tag, judul, langkah[], nomor]  (maks 3 kartu,
 *                              maks 3 langkah per kartu)
 *
 * Prinsip:
 *  - Kecenderungan (mis. introvert) BUKAN penyakit -> tidak memaksa berubah,
 *    melainkan melatih perilaku agar kepentingan tetap tersampaikan.
 *  - Saran selalu berupa langkah kecil yang bisa dicoba minggu ini.
 *  - Skor >= ambang batas tidak diberi kartu (tidak perlu diinterupsi).
 */

function build_saran(array $dimensi, array $skills): array
{
    $items = [];

    /* ------------------------------------------------------------------ */
    /* 1. SOFT SKILLS (tampil bila < 70)                                   */
    /* ------------------------------------------------------------------ */
    $skillMeta = [
        'interpersonal' => [
            'tag'  => 'Soft Skill · Interpersonal',
            'nama' => 'Interpersonal',
            'langkah' => [
                'Mulai satu interaksi ringan tiap hari: sapaan, komentar singkat, atau satu pertanyaan tentang tugas mereka.',
                'Pakai pola dengar – rangkum – tambah: ulangi inti lawan bicara dulu, baru menyampaikan pendapatmu.',
                'Amati orang yang mudah akrab, catat dua kebiasaannya, dan tiru satu di minggu ini.',
                'Minta umpan balik singkat ke dua orang terdekat: "apa yang bikin kamu nyaman/nggak nyaman ngobrol sama saya?"',
            ],
        ],
        'communication' => [
            'tag'  => 'Soft Skill · Communication',
            'nama' => 'Communication',
            'langkah' => [
                'Rapikan pesan dengan pola SBI: Situation (kejadiannya), Behavior (yang saya lakukan), Impact (dampaknya).',
                'Rekam diri 60 detik menjelaskan satu ide, putar ulang, lalu potong kalimat yang berulang.',
                'Tulis tiga kalimat inti sebelum rapat atau presentasi supaya tidak kehilangan fokus.',
                'Latih menjelaskan hal teknis kepada orang awam tanpa jargon — kalimat sederhana adalah ujian sebenarnya.',
            ],
        ],
        'emotional' => [
            'tag'  => 'Soft Skill · Emotional Intelligence',
            'nama' => 'Emotional Intelligence',
            'langkah' => [
                'Journaling 5 menit tiap malam: apa yang saya rasakan, apa pemicunya, apa reaksi saya, respons apa yang lebih baik besok.',
                'Teknik jeda saat emosi naik: tarik napas 4 hitungan, tahan 4, buang 6, baru menjawab.',
                'Kenali tiga pemicu stres paling sering dan buat antisipasinya (istirahat, olahraga, atau minta bantuan).',
                'Beri nama perasaan secara spesifik: "ini kecewa", bukan "saya gagal" — agar masalah tidak melebar.',
            ],
        ],
    ];

    foreach (['interpersonal', 'communication', 'emotional'] as $key) {
        $skor = (int) ($skills[$key] ?? 0);
        if ($skor >= 70) {
            continue;
        }

        $items[] = [
            'tag'      => $skillMeta[$key]['tag'],
            'judul'    => $skillMeta[$key]['nama'] . ' · ' . $skor . '%',
            'urut'     => $skor,
            'langkah'  => $skillMeta[$key]['langkah'],
        ];
    }

    // dua soft skill terlemah saja, sisanya disisakan untuk kartu dimensi
    usort($items, fn($a, $b) => $a['urut'] <=> $b['urut']);
    $items = array_slice($items, 0, 2);

    /* ------------------------------------------------------------------ */
    /* 2. DIMENSI / GAYA ALAMI                                             */
    /*    EI  : >= 50 = Ekstrovert, < 50 = Introvert                      */
    /*    SN  : >= 50 = Intuitif,    < 50 = Sensing                        */
    /*    TF  : >= 50 = Feeling,     < 50 = Thinking                       */
    /*    JP  : >= 50 = Judging,     < 50 = Perceiving                     */
    /* ------------------------------------------------------------------ */
    $dimensiMeta = [
        'EI' => [
            'tag'   => 'Gaya Berenergi',
            'nama'  => 'Energy (Introvert – Ekstrovert)',
            'modus' => 'bar',
            'sisi'  => ['extrovert' => 'Ekstrovert', 'introvert' => 'Introvert'],
            'introvert' => [
                'judul'  => 'Introvert dominan ({sisi}%)',
                'langkah' => [
                    'Satu interaksi kecil tiap hari: sapaan, satu pertanyaan, atau komentar singkat di kelas/rapat. Targetnya bukan banyak bicara, tapi tetap terlihat.',
                    'Mulai dari satu lawan bicara atau tulisan — kenali orang baru lewat chat/discussion tertulis dulu, baru naik ke tatap muka.',
                    'Siapkan tiga topik cadangan (tugas, kejadian kampus, satu pertanyaan terbuka) supaya obrolan tidak berhenti di "ya".',
                    'Jadwalkan waktu isi ulang setelah interaksi panjang (15 menit sendiri) — itu kebutuhan, bukan lari dari tanggung jawab.',
                ],
            ],
            'extrovert' => [
                'judul'  => 'Ekstrovert dominan ({sisi}%)',
                'langkah' => [
                    'Jeda tiga detik setelah orang lain selesai bicara sebelum menyahut.',
                    'Rangkum dulu lawan bicara ("jadi maksudmu..."), baru tambahkan pendapatmu.',
                    'Tutup satu ruang untuk rekan pendiam: "menurutmu bagaimana?" — sekali tiap diskusi.',
                    'Catat tiga poin penting saat rapat untuk melatih menahan dorongan langsung merespons.',
                ],
            ],
        ],
        'SN' => [
            'tag'   => 'Cara Menerima Informasi',
            'nama'  => 'Information (Sensing – Intuitif)',
            'modus' => 'bar',
            'sisi'  => ['intuitif' => 'Intuitif', 'sensing' => 'Sensing'],
            'intuitif' => [
                'judul'  => 'Intuitif dominan ({sisi}%)',
                'langkah' => [
                    'Tiap kali dapat ide besar, pecah menjadi tiga langkah satu minggu yang bisa dimulai hari Senin.',
                    'Sebelum eksekusi, minta satu data konkret: siapa orangnya, kapan, berapa waktu atau biayanya.',
                    'Kerjakan pekerjaan membosankan sebagai sesi pertama hari ini (25 menit) sebelum ide baru datang.',
                    'Simpan ide tertunda di satu tempat dan pilih maksimal dua untuk dikerjakan per minggu.',
                ],
            ],
            'sensing' => [
                'judul'  => 'Sensing dominan ({sisi}%)',
                'langkah' => [
                    'Luangkan 10 menit brainstorming tanpa menilai: tulis sebanyak mungkin, saring belakangan.',
                    'Di setiap diskusi, ajukan minimal satu pertanyaan "bagaimana kalau...".',
                    'Coba satu cara baru tiap minggu walau belum pasti berhasil, lalu catat hasilnya.',
                    'Baca satu hal di luar bidangmu selama 15 menit untuk melatih pola pikir luas.',
                ],
            ],
        ],
        'TF' => [
            'tag'   => 'Cara Memutuskan',
            'nama'  => 'Decision (Thinking – Feeling)',
            'modus' => 'bar',
            'sisi'  => ['feeling' => 'Feeling', 'thinking' => 'Thinking'],
            'thinking' => [
                'judul'  => 'Thinking dominan ({sisi}%)',
                'langkah' => [
                    'Sebelum memutuskan, tanyakan: "bagaimana ini memengaruhi perasaan dan beban orang lain?"',
                    'Sampaikan kritik dengan pola hal positif → titik perbaikan → dukungan lanjutan.',
                    'Tunda keputusan penting 10 menit bila ada pihak yang terlihat keberatan.',
                    'Berikan pujian spesifik ("hasil laporan kamu rapi") minimal sekali seminggu.',
                ],
            ],
            'feeling' => [
                'judul'  => 'Feeling dominan ({sisi}%)',
                'langkah' => [
                    'Pisahkan fakta dan perasaan: tulis dua kolom "yang terjadi" dan "yang saya rasakan".',
                    'Tunda keputusan 10 menit saat emosi tinggi, lalu putuskan berdasarkan kriteria yang sudah disepakati.',
                    'Beri tahu rekan sejak awal bila ada hal yang tidak bisa kamu penuhi.',
                    'Latih menolak dengan sopan namun tetap jelas, tanpa memberi alasan berlebihan.',
                ],
            ],
        ],
        'JP' => [
            'tag'   => 'Gaya Menjalani Hari',
            'nama'  => 'Lifestyle (Judging – Perceiving)',
            'modus' => 'bar',
            'sisi'  => ['judging' => 'Judging', 'perceiving' => 'Perceiving'],
            'judging' => [
                'judul'  => 'Judging dominan ({sisi}%)',
                'langkah' => [
                    'Sisihkan 20% waktu dalam jadwal sebagai jaring pengaman, bukan diisi penuh.',
                    'Siapkan rencana B untuk setiap target penting: apa yang dilakukan bila melewati tenggat.',
                    'Sekali tiap pekan, biarkan satu hal berjalan tanpa rencana detail.',
                    'Saat rencana berantakan, tulis ulang tiga langkah berikutnya alih-alih mengulang semuanya.',
                ],
            ],
            'perceiving' => [
                'judul'  => 'Perceiving dominan ({sisi}%)',
                'langkah' => [
                    'Aturan 2 menit: pekerjaan yang selesai dalam kurang dari 2 menit dikerjakan saat itu juga.',
                    'Buat tenggat internal satu hari lebih awal dari tenggat resmi.',
                    'Pecah tugas besar menjadi sesi 25 menit (Pomodoro) dan kerjakan sesi pertama hari ini juga.',
                    'Tulis maksimal tiga prioritas harian pada pagi hari, bukan malam hari.',
                ],
            ],
        ],
    ];

    $kandidat = [];
    foreach ($dimensiMeta as $kode => $meta) {
        $nilai = (int) ($dimensi[$kode] ?? 50);
        $deviasi = abs($nilai - 50);
        if ($deviasi < 15) {
            continue; // seimbang, tidak perlu diinterupsi
        }
        $sisi = $nilai >= 50 ? array_key_first($meta['sisi']) : array_key_last($meta['sisi']);
        $persenSisi = $nilai >= 50 ? $nilai : (100 - $nilai);

        $kandidat[] = [
            'tag'      => $meta['tag'],
            'judul'    => str_replace('{sisi}', (string) $persenSisi, $meta[$sisi]['judul']),
            'urut'     => 100 - $deviasi,      // makin seimbang makin kecil prioritasnya
            'deviasi'  => $deviasi,
            'langkah'  => $meta[$sisi]['langkah'],
        ];
    }

    // total kartu dibatasi 3: soft skill dulu (maks 2), sisanya dimensi paling menyimpang
    usort($kandidat, fn($a, $b) => $b['deviasi'] <=> $a['deviasi']);
    foreach (array_slice($kandidat, 0, max(0, 3 - count($items))) as $k) {
        unset($k['deviasi']);
        $items[] = $k;
    }

    /* ------------------------------------------------------------------ */
    /* 3. Bila semua sudah baik -> kartu konsistensi, bukan kosong         */
    /* ------------------------------------------------------------------ */
    if (!$items) {
        $items[] = [
            'tag'      => 'Pertahankan',
            'judul'    => 'Kamu berada di jalur yang baik',
            'urut'     => 999,
            'langkah'  => [
                'Ulangi kuis ini satu bulan lagi dan bandingkan angkanya.',
                'Jaga satu kebiasaan yang sudah terbentuk walau jadwal sedang padat.',
                'Minta umpan balik jujur per bulan dari rekan atau dosen.',
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 4. Ringkas: urutkan, maksimal 3 kartu dan 3 langkah per kartu       */
    /* ------------------------------------------------------------------ */
    usort($items, fn($a, $b) => $a['urut'] <=> $b['urut']);

    $items = array_slice($items, 0, 3);
    foreach ($items as $i => &$item) {
        $item['langkah'] = array_slice($item['langkah'], 0, 3);
        $item['nomor']   = $i + 1;
        unset($item['urut']);
    }
    unset($item);

    return $items;
}
