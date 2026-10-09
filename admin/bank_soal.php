<?php
/**
 * admin/bank_soal.php
 * ------------------------------------------------------------------
 * Satu pintu untuk semua urusan Bank Soal:
 *
 *   bank_soal.php            -> daftar soal (filter, cari, toggle status)
 *   bank_soal.php?tambah=1   -> form soal baru
 *   bank_soal.php?edit=<id>  -> form ubah soal
 *   bank_soal.php?hapus=<id> -> konfirmasi hapus
 *
 * CATATAN: gerbang login admin belum aktif (admin/login.php masih
 * kerangka), konsisten dengan halaman admin lainnya.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../config/database.php';
session_start();

$kategoriList = [
    'Situational Judgment Test (SJT)',
    'Emotional Intelligence',
    'Communication & Interpersonal Skills',
    'Profil Kepribadian',
];

/* Label pendek -> dipakai tabel & filter pada halaman daftar. */
$tipeList = [
    'multiple_choice' => 'Pilihan tunggal',
    'dropdown'        => 'Dropdown',
    'checkbox'        => 'Checkbox (multi)',
    'scale'           => 'Skala / rating',
];

/* Label panjang -> dipakai select pada form tambah / edit. */
$tipeListForm = [
    'multiple_choice' => 'Pilihan tunggal (radio)',
    'dropdown'        => 'Dropdown / select',
    'checkbox'        => 'Checkbox (boleh lebih dari satu)',
    'scale'           => 'Skala / rating',
];

$kesulitanList = ['mudah', 'sedang', 'sulit'];

$skillList = [
    'interpersonal' => 'Interpersonal',
    'communication' => 'Communication',
    'emotional'     => 'Emotional Intelligence',
];

$pesan = [
    'toggle'         => ['success', 'Status soal berhasil diubah.'],
    'hapus_ok'       => ['success', 'Soal berhasil dihapus dari bank soal.'],
    'hapus_nonaktif' => ['error', 'Soal punya jawaban peserta sehingga tidak bisa dihapus, statusnya diubah menjadi nonaktif.'],
    'hapus_batal'    => ['error', 'Penghapusan dibatalkan.'],
    'hapus_gagal'    => ['error', 'Soal gagal dihapus dan gagal dinonaktifkan. Periksa log server.'],
    'simpan_baru'    => ['success', 'Soal baru berhasil ditambahkan ke bank soal.'],
    'simpan_edit'    => ['success', 'Perubahan soal berhasil disimpan.'],
];

/** Bobot -> indikator (konsisten dengan data lama). */
function indikatorDariBobot(int $bobot): string
{
    if ($bobot >= 4) {
        return 'positif';
    }

    return $bobot === 3 ? 'netral' : 'negatif';
}

$db = getDB();

/* ------------------------------------------------------------- mode */
$mode  = 'daftar';
$id    = 0;
$pesanAktif = null;

if (isset($_GET['tambah'])) {
    $mode = 'form';
} elseif (isset($_GET['edit'])) {
    $mode = 'edit';
    $id   = (int) $_GET['edit'];
} elseif (isset($_GET['hapus'])) {
    $mode = 'hapus';
    $id   = (int) $_GET['hapus'];
}

/* Saat POST, id bisa datang dari field tersembunyi. */
if ($id <= 0 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
}

$isEdit = ($mode === 'edit');
$isForm = ($mode === 'form' || $mode === 'edit');

/* ==================================================== DAFTAR (default)
 * Filter, ringkasan, toggle status aktif/nonaktif.
 * ------------------------------------------------------------------ */
if ($mode === 'daftar') {
    $q         = trim((string) ($_GET['q'] ?? ''));
    $kategoriF = (string) ($_GET['kategori'] ?? '');
    $tipeF     = (string) ($_GET['tipe'] ?? '');
    $statusF   = (string) ($_GET['status'] ?? '');

    $qs = http_build_query(array_filter([
        'q'        => $q,
        'kategori' => $kategoriF,
        'tipe'     => $tipeF,
        'status'   => $statusF,
    ], static function ($v) {
        return $v !== '';
    }));

    $selfUrl = 'bank_soal.php' . ($qs !== '' ? '?' . $qs : '');

    $urlPesan = static function (string $msg) use ($selfUrl) {
        return $selfUrl . (strpos($selfUrl, '?') === false ? '?' : '&') . 'msg=' . rawurlencode($msg);
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
        $toggleId = (int) ($_POST['id'] ?? 0);
        if ($toggleId > 0) {
            $stmt = $db->prepare(
                "UPDATE bank_soal SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = ?"
            );
            $stmt->execute([$toggleId]);
        }
        header('Location: ' . $urlPesan('toggle'));
        exit;
    }

    $where  = [];
    $params = [];
    if ($q !== '') {
        $where[]  = '(s.pertanyaan LIKE ? OR s.kategori LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (in_array($kategoriF, $kategoriList, true)) {
        $where[]  = 's.kategori = ?';
        $params[] = $kategoriF;
    }
    if (isset($tipeList[$tipeF])) {
        $where[]  = 's.tipe_soal = ?';
        $params[] = $tipeF;
    }
    if (in_array($statusF, ['aktif', 'nonaktif'], true)) {
        $where[]  = 's.status = ?';
        $params[] = $statusF;
    }

    $sql = "SELECT s.id, s.pertanyaan, s.kategori, s.tipe_soal, s.tingkat_kesulitan,
                   s.status, s.petunjuk, s.skill, d.kode AS dimensi_kode,
                   JSON_LENGTH(s.pilihan_jawaban) AS jml_pilihan
            FROM bank_soal s
            JOIN dimensi d ON d.id = s.dimensi_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY s.id LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $ringkas = $db->query(
        "SELECT COUNT(*) AS total,
                SUM(status = 'aktif') AS aktif,
                SUM(status = 'nonaktif') AS nonaktif,
                COUNT(DISTINCT CASE WHEN status = 'aktif' AND pilihan_jawaban IS NOT NULL
                                    AND JSON_LENGTH(pilihan_jawaban) > 0
                               THEN pertanyaan END) AS unik_aktif
         FROM bank_soal"
    )->fetch();

    $msgKey     = (string) ($_GET['msg'] ?? '');
    $pesanAktif = $pesan[$msgKey] ?? null;
}

/* ================================================== FORM TAMBAH / EDIT
 * Satu bentuk form; edit mengisi nilai awal dari bank soal.
 * ------------------------------------------------------------------ */
if ($isForm) {
    $dimensiList = $db->query("SELECT id, kode, nama FROM dimensi ORDER BY urutan")->fetchAll();

    $errors = [];

    $nilai = [
        'pertanyaan'        => '',
        'kategori'          => $kategoriList[3] ?? ($kategoriList[0] ?? ''),
        'tipe_soal'         => 'multiple_choice',
        'dimensi_id'        => (int) ($dimensiList[0]['id'] ?? 0),
        'skill'             => 'interpersonal',
        'tingkat_kesulitan' => 'sedang',
        'status'            => 'aktif',
        'petunjuk'          => '',
        'opsi'              => [
            ['teks' => '', 'bobot' => 5, 'kunci' => true],
            ['teks' => '', 'bobot' => 4, 'kunci' => false],
            ['teks' => '', 'bobot' => 3, 'kunci' => false],
            ['teks' => '', 'bobot' => 2, 'kunci' => false],
        ],
    ];

    if ($isEdit) {
        if ($id <= 0) {
            header('Location: bank_soal.php');
            exit;
        }

        $stmt = $db->prepare(
            "SELECT s.*, d.kode AS dimensi_kode
             FROM bank_soal s
             JOIN dimensi d ON d.id = s.dimensi_id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        $soal = $stmt->fetch();

        if (!$soal) {
            header('Location: bank_soal.php');
            exit;
        }

        $pilihanAwal = json_decode((string) $soal['pilihan_jawaban'], true);
        if (!is_array($pilihanAwal)) {
            $pilihanAwal = [];
        }
        usort($pilihanAwal, static function ($a, $b) {
            return ($a['urutan'] ?? 0) <=> ($b['urutan'] ?? 0);
        });

        $kunciAwal = json_decode((string) $soal['jawaban'], true);
        if (!is_array($kunciAwal)) {
            $kunciAwal = [];
        }
        $kunciSet = array_flip(array_map('intval', $kunciAwal));

        $nilai = [
            'id'               => $id,
            'pertanyaan'       => $soal['pertanyaan'],
            'kategori'         => $soal['kategori'],
            'tipe_soal'        => $soal['tipe_soal'],
            'dimensi_id'       => (int) $soal['dimensi_id'],
            'skill'            => $soal['skill'],
            'tingkat_kesulitan' => $soal['tingkat_kesulitan'],
            'status'           => $soal['status'],
            'petunjuk'         => (string) $soal['petunjuk'],
            'opsi'             => [],
        ];

        foreach ($pilihanAwal as $o) {
            $nilai['opsi'][] = [
                'teks'  => (string) ($o['teks'] ?? ''),
                'bobot' => (int) ($o['bobot'] ?? 5),
                'kunci' => isset($kunciSet[(int) ($o['id'] ?? 0)]),
            ];
        }
        if (!$nilai['opsi']) {
            $nilai['opsi'] = [
                ['teks' => '', 'bobot' => 5, 'kunci' => true],
                ['teks' => '', 'bobot' => 4, 'kunci' => false],
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nilai['pertanyaan']        = trim((string) ($_POST['pertanyaan'] ?? ''));
        $nilai['kategori']          = (string) ($_POST['kategori'] ?? '');
        $nilai['tipe_soal']         = (string) ($_POST['tipe_soal'] ?? '');
        $nilai['dimensi_id']        = (int) ($_POST['dimensi_id'] ?? 0);
        $nilai['skill']             = (string) ($_POST['skill'] ?? '');
        $nilai['tingkat_kesulitan'] = (string) ($_POST['tingkat_kesulitan'] ?? '');
        $nilai['status']            = (string) ($_POST['status'] ?? 'aktif');
        $nilai['petunjuk']          = trim((string) ($_POST['petunjuk'] ?? ''));

        $postedTeks  = is_array($_POST['opt_teks'] ?? null) ? $_POST['opt_teks'] : [];
        $postedBobot = is_array($_POST['opt_bobot'] ?? null) ? $_POST['opt_bobot'] : [];
        $postedKunci = is_array($_POST['opt_kunci'] ?? null) ? $_POST['opt_kunci'] : [];
        ksort($postedTeks);
        ksort($postedBobot);

        if ($postedTeks) {
            $nilai['opsi'] = [];
            foreach ($postedTeks as $i => $teksOpsi) {
                $nilai['opsi'][] = [
                    'teks'  => (string) $teksOpsi,
                    'bobot' => (int) ($postedBobot[$i] ?? 5),
                    'kunci' => isset($postedKunci[$i]),
                ];
            }
        }

        /* ------------------------- validasi ------------------------- */
        if ($nilai['pertanyaan'] === '') {
            $errors[] = 'Pertanyaan wajib diisi.';
        }
        if (!in_array($nilai['kategori'], $kategoriList, true)) {
            $errors[] = 'Kategori tidak valid.';
        }
        if (!isset($tipeList[$nilai['tipe_soal']])) {
            $errors[] = 'Tipe soal tidak valid.';
        }
        $dimensiValid = array_column($dimensiList, 'id');
        if (!in_array($nilai['dimensi_id'], $dimensiValid, true)) {
            $errors[] = 'Dimensi tidak valid.';
        }
        if (!isset($skillList[$nilai['skill']])) {
            $errors[] = 'Skill tidak valid.';
        }
        if (!in_array($nilai['tingkat_kesulitan'], $kesulitanList, true)) {
            $errors[] = 'Tingkat kesulitan tidak valid.';
        }
        if (!in_array($nilai['status'], ['aktif', 'nonaktif'], true)) {
            $errors[] = 'Status tidak valid.';
        }
        if ($nilai['petunjuk'] !== '' && mb_strlen($nilai['petunjuk']) > 150) {
            $errors[] = 'Petunjuk maksimal 150 karakter.';
        }

        $opsiValid = [];
        foreach ($nilai['opsi'] as $i => $o) {
            $teksOpsi = trim($o['teks']);
            $bobot    = (int) $o['bobot'];

            if ($teksOpsi === '') {
                continue; // baris kosong diabaikan
            }
            if ($bobot < 1 || $bobot > 5) {
                $errors[] = 'Bobot pilihan ke-' . ($i + 1) . ' harus antara 1 sampai 5.';
                continue;
            }
            $opsiValid[] = ['teks' => $teksOpsi, 'bobot' => $bobot, 'kunci' => (bool) $o['kunci']];
        }

        if (count($opsiValid) < 2) {
            $errors[] = 'Pilihan jawaban minimal 2 buah.';
        }

        /* --------------------------- simpan -------------------------- */
        if (!$errors) {
            try {
                if ($isEdit) {
                    $pilihan = [];
                    $kunci   = [];
                    foreach ($opsiValid as $i => $o) {
                        $optId = $id * 1000 + ($i + 1);
                        $pilihan[] = [
                            'id'        => $optId,
                            'urutan'    => $i + 1,
                            'teks'      => $o['teks'],
                            'bobot'     => $o['bobot'],
                            'indikator' => indikatorDariBobot($o['bobot']),
                        ];
                        if ($o['kunci']) {
                            $kunci[] = $optId;
                        }
                    }

                    $stmt = $db->prepare(
                        "UPDATE bank_soal
                         SET kategori = ?, dimensi_id = ?, skill = ?, pertanyaan = ?,
                             tipe_soal = ?, tingkat_kesulitan = ?, petunjuk = ?, status = ?,
                             pilihan_jawaban = ?, jawaban = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?"
                    );
                    $stmt->execute([
                        $nilai['kategori'],
                        $nilai['dimensi_id'],
                        $nilai['skill'],
                        $nilai['pertanyaan'],
                        $nilai['tipe_soal'],
                        $nilai['tingkat_kesulitan'],
                        $nilai['petunjuk'] !== '' ? $nilai['petunjuk'] : null,
                        $nilai['status'],
                        json_encode($pilihan, JSON_UNESCAPED_UNICODE),
                        ($nilai['tipe_soal'] === 'scale' || !$kunci) ? null : json_encode($kunci),
                        $id,
                    ]);

                    header('Location: bank_soal.php?msg=simpan_edit');
                    exit;
                }

                $db->beginTransaction();

                $stmt = $db->prepare(
                    "INSERT INTO bank_soal
                        (kategori, dimensi_id, skill, pertanyaan, tipe_soal,
                         tingkat_kesulitan, petunjuk, urutan, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)"
                );
                $stmt->execute([
                    $nilai['kategori'],
                    $nilai['dimensi_id'],
                    $nilai['skill'],
                    $nilai['pertanyaan'],
                    $nilai['tipe_soal'],
                    $nilai['tingkat_kesulitan'],
                    $nilai['petunjuk'] !== '' ? $nilai['petunjuk'] : null,
                    $nilai['status'],
                ]);
                $soalId = (int) $db->lastInsertId();

                $pilihan = [];
                $kunci   = [];
                foreach ($opsiValid as $i => $o) {
                    $optId = $soalId * 1000 + ($i + 1);
                    $pilihan[] = [
                        'id'        => $optId,
                        'urutan'    => $i + 1,
                        'teks'      => $o['teks'],
                        'bobot'     => $o['bobot'],
                        'indikator' => indikatorDariBobot($o['bobot']),
                    ];
                    if ($o['kunci']) {
                        $kunci[] = $optId;
                    }
                }

                $stmt = $db->prepare("UPDATE bank_soal SET pilihan_jawaban = ?, jawaban = ? WHERE id = ?");
                $stmt->execute([
                    json_encode($pilihan, JSON_UNESCAPED_UNICODE),
                    ($nilai['tipe_soal'] === 'scale' || !$kunci) ? null : json_encode($kunci),
                    $soalId,
                ]);

                $db->commit();
                header('Location: bank_soal.php?msg=simpan_baru');
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('[bank_soal] gagal simpan id ' . $id . ': ' . $e->getMessage());
                $errors[] = $isEdit
                    ? 'Gagal menyimpan perubahan ke database. Coba lagi.'
                    : 'Gagal menyimpan soal ke database. Coba lagi.';
            }
        }
    }
}

/* ========================================================== HAPUS */
if ($mode === 'hapus') {
    if ($id <= 0) {
        header('Location: bank_soal.php');
        exit;
    }

    $stmt = $db->prepare(
        "SELECT s.id, s.pertanyaan, s.kategori, s.tipe_soal, s.status,
                JSON_LENGTH(s.pilihan_jawaban) AS jml_pilihan
         FROM bank_soal s
         WHERE s.id = ?"
    );
    $stmt->execute([$id]);
    $soalHapus = $stmt->fetch();

    if (!$soalHapus) {
        header('Location: bank_soal.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $konfirmasi = (string) ($_POST['konfirmasi'] ?? '');

        if ($konfirmasi === 'ya') {
            try {
                $db->prepare("DELETE FROM bank_soal WHERE id = ?")->execute([$id]);
                header('Location: bank_soal.php?msg=hapus_ok');
                exit;
            } catch (PDOException $e) {
                // Ada jawaban peserta yang menunjuk soal ini (FK RESTRICT)
                // -> jadikan nonaktif supaya tidak lagi dipakai ujian.
                try {
                    $db->prepare("UPDATE bank_soal SET status = 'nonaktif' WHERE id = ?")->execute([$id]);
                    header('Location: bank_soal.php?msg=hapus_nonaktif');
                    exit;
                } catch (PDOException $e2) {
                    error_log('[bank_soal] gagal hapus id ' . $id . ': ' . $e->getMessage());
                    header('Location: bank_soal.php?msg=hapus_gagal');
                    exit;
                }
            }
        }

        header('Location: bank_soal.php?msg=hapus_batal');
        exit;
    }
}
?>
<?php
switch ($mode) {
    case 'form':
        $pageTitle = 'Tambah Soal - Admin';
        $subTitle  = 'Tambah Soal';
        break;
    case 'edit':
        $pageTitle = 'Edit Soal - Admin';
        $subTitle  = 'Edit Soal #' . $id;
        break;
    case 'hapus':
        $pageTitle = 'Hapus Soal - Admin';
        $subTitle  = 'Hapus Soal #' . $id;
        break;
    default:
        $pageTitle = 'Bank Soal - Admin';
        $subTitle  = 'Bank Soal';
}

require __DIR__ . '/../assets/partials/head.php';

$adminSubtitle = $subTitle;
$adminActive = 'bank_soal';
require __DIR__ . '/../assets/partials/header_admin.php';
?>

<?php if ($mode === 'daftar'): ?>

    <main class="exam-container">

        <div class="info-grid" style="margin-bottom:24px;">
            <div class="info-box"><strong><?= (int) $ringkas['total'] ?></strong> Total Soal</div>
            <div class="info-box"><strong><?= (int) $ringkas['aktif'] ?></strong> Aktif</div>
            <div class="info-box"><strong><?= (int) $ringkas['nonaktif'] ?></strong> Nonaktif</div>
            <div class="info-box"><strong><?= (int) $ringkas['unik_aktif'] ?></strong> Unik untuk Ujian</div>
        </div>

        <div class="question-card">
            <div class="question-type">BANK SOAL</div>

            <?php if ($pesanAktif): ?>
                <div class="notice <?= htmlspecialchars($pesanAktif[0], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($pesanAktif[1], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form class="filter-bar" method="get" action="bank_soal.php">
                <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Cari teks soal atau kategori...">
                <select name="kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($kategoriList as $k): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $kategoriF === $k ? ' selected' : '' ?>>
                            <?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="tipe">
                    <option value="">Semua tipe</option>
                    <?php foreach ($tipeList as $kode => $nama): ?>
                        <option value="<?= $kode ?>"<?= $tipeF === $kode ? ' selected' : '' ?>><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">Semua status</option>
                    <option value="aktif"<?= $statusF === 'aktif' ? ' selected' : '' ?>>Aktif</option>
                    <option value="nonaktif"<?= $statusF === 'nonaktif' ? ' selected' : '' ?>>Nonaktif</option>
                </select>
                <button class="btn btn-next" type="submit">Filter</button>
                <a class="btn btn-secondary" href="bank_soal.php">Reset</a>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pertanyaan</th>
                            <th>Kategori</th>
                            <th>Tipe</th>
                            <th>Dimensi / Skill</th>
                            <th>Opsi</th>
                            <th>Kesulitan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:var(--muted);padding:26px;">
                                Tidak ada soal yang cocok dengan filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="num"><?= (int) $r['id'] ?></td>
                            <td class="question-cell">
                                <p><?= htmlspecialchars(mb_substr($r['pertanyaan'], 0, 150) . (mb_strlen($r['pertanyaan']) > 150 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($r['petunjuk']): ?>
                                    <small>Petunjuk: <?= htmlspecialchars($r['petunjuk'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="tag blue"><?= htmlspecialchars($tipeList[$r['tipe_soal']] ?? $r['tipe_soal'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <span class="tag purple"><?= htmlspecialchars($r['dimensi_kode'], ENT_QUOTES, 'UTF-8') ?></span><br>
                                <small style="color:var(--muted);"><?= htmlspecialchars($r['skill'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td class="num">
                                <?php if ((int) $r['jml_pilihan'] === 0): ?>
                                    <span class="tag red">0</span>
                                <?php else: ?>
                                    <?= (int) $r['jml_pilihan'] ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="tag"><?= htmlspecialchars($r['tingkat_kesulitan'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <?php if ($r['status'] === 'aktif'): ?>
                                    <span class="tag green">aktif</span>
                                <?php else: ?>
                                    <span class="tag red">nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="act-edit" href="bank_soal.php?edit=<?= (int) $r['id'] ?>">Edit</a>
                                    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                        <input type="hidden" name="aksi" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="act-edit">
                                            <?= $r['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <a class="act-danger" href="bank_soal.php?hapus=<?= (int) $r['id'] ?>">Hapus</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p style="margin-top:12px;color:var(--muted);font-size:13.5px;">
                Menampilkan <?= count($rows) ?> soal<?= count($rows) === 500 ? ' (batas tampilan 500, gunakan filter untuk mempersempit)' : '' ?>.
                Ujian mengambil soal <strong>aktif</strong> secara acak, maksimal
                <?= (int) EXAM_QUESTION_COUNT ?> soal unik per sesi.
            </p>

            <div class="form-actions">
                <a class="btn btn-primary" href="bank_soal.php?tambah=1" style="width:auto;">+ Tambah Soal</a>
            </div>
        </div>

    </main>

<?php elseif ($isForm): ?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type"><?= $isEdit ? 'EDIT SOAL #' . (int) $id : 'FORM SOAL BARU' ?></div>

            <?php if ($errors): ?>
                <div class="notice error">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="admin-form" method="post" id="soalForm" autocomplete="off"<?= $isEdit ? ' action="bank_soal.php?edit=' . (int) $id . '"' : '' ?>>
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <?php endif; ?>

                <label for="pertanyaan">Pertanyaan</label>
                <textarea id="pertanyaan" name="pertanyaan" placeholder="Tulis situasi atau pertanyaan..."><?= htmlspecialchars($nilai['pertanyaan'], ENT_QUOTES, 'UTF-8') ?></textarea>

                <div class="form-grid">
                    <div>
                        <label for="kategori">Kategori</label>
                        <select id="kategori" name="kategori">
                            <?php
                            $opsiKategori = $kategoriList;
                            if ($isEdit && !in_array($nilai['kategori'], $opsiKategori, true)) {
                                array_unshift($opsiKategori, $nilai['kategori']);
                            }
                            foreach ($opsiKategori as $k): ?>
                                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $nilai['kategori'] === $k ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="tipe_soal">Tipe jawaban</label>
                        <select id="tipe_soal" name="tipe_soal">
                            <?php foreach ($tipeListForm as $kode => $nama): ?>
                                <option value="<?= $kode ?>"<?= $nilai['tipe_soal'] === $kode ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="dimensi_id">Dimensi</label>
                        <select id="dimensi_id" name="dimensi_id">
                            <?php foreach ($dimensiList as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"<?= (int) $nilai['dimensi_id'] === (int) $d['id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($d['kode'] . ' - ' . $d['nama'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="skill">Skill</label>
                        <select id="skill" name="skill">
                            <?php foreach ($skillList as $kode => $nama): ?>
                                <option value="<?= $kode ?>"<?= $nilai['skill'] === $kode ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="tingkat_kesulitan">Tingkat kesulitan</label>
                        <select id="tingkat_kesulitan" name="tingkat_kesulitan">
                            <?php foreach ($kesulitanList as $k): ?>
                                <option value="<?= $k ?>"<?= $nilai['tingkat_kesulitan'] === $k ? ' selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="aktif"<?= $nilai['status'] === 'aktif' ? ' selected' : '' ?>>aktif (ikut ujian)</option>
                            <option value="nonaktif"<?= $nilai['status'] === 'nonaktif' ? ' selected' : '' ?>>nonaktif (tidak ikut ujian)</option>
                        </select>
                    </div>
                </div>

                <label for="petunjuk">Petunjuk singkat <small>(opsional, maks. 150 karakter)</small></label>
                <input type="text" id="petunjuk" name="petunjuk" maxlength="150"
                       value="<?= htmlspecialchars($nilai['petunjuk'], ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="mis. Pilih semua yang sesuai">

                <label>Pilihan jawaban <small>(bobot 1&ndash;5, centang = kunci jawaban)</small></label>
                <div class="option-editor" id="optionRows"></div>
                <div class="form-actions" style="margin-top:12px;">
                    <button type="button" class="btn btn-secondary" id="addOptionBtn">+ Tambah Pilihan</button>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" style="width:auto;"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Soal' ?></button>
                    <a class="btn btn-secondary" href="bank_soal.php">Batal</a>
                </div>
            </form>
        </div>
    </main>

<?php else: ?>

    <main class="exam-container">
        <div class="question-card">
            <div class="question-type">KONFIRMASI HAPUS</div>

            <div class="question-text" style="margin-bottom:14px;">
                Yakin ingin menghapus soal berikut dari bank soal?
                Tindakan ini tidak bisa dibatalkan.
            </div>

            <div class="notice">
                <strong>Soal #<?= (int) $soalHapus['id'] ?></strong>
                &mdash; <?= htmlspecialchars($soalHapus['kategori'], ENT_QUOTES, 'UTF-8') ?>
                (<?= htmlspecialchars($soalHapus['tipe_soal'], ENT_QUOTES, 'UTF-8') ?>,
                <?= (int) $soalHapus['jml_pilihan'] ?> pilihan,
                status <?= htmlspecialchars($soalHapus['status'], ENT_QUOTES, 'UTF-8') ?>)
                <div style="margin-top:8px;font-weight:600;">
                    "<?= htmlspecialchars(mb_substr($soalHapus['pertanyaan'], 0, 220) . (mb_strlen($soalHapus['pertanyaan']) > 220 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>"
                </div>
            </div>

            <p style="color:var(--muted);font-size:14px;line-height:1.6;">
                Jika soal ini sudah pernah dijawab peserta, sistem akan otomatis
                menonaktifkannya (status <strong>nonaktif</strong>) agar tidak
                mengganggu data hasil ujian yang sudah tersimpan.
            </p>

            <div class="form-actions">
                <form method="post" action="bank_soal.php?hapus=<?= (int) $id ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="konfirmasi" value="ya">
                    <button class="btn btn-submit" type="submit">Ya, Hapus</button>
                </form>
                <form method="post" action="bank_soal.php?hapus=<?= (int) $id ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="konfirmasi" value="batal">
                    <button class="btn btn-secondary" type="submit">Batal</button>
                </form>
                <a class="btn btn-secondary" href="bank_soal.php">Kembali ke Daftar</a>
            </div>
        </div>
    </main>

<?php endif; ?>

<?php if ($isForm): ?>
    <script>
    window.OptionSeed = <?= json_encode($nilai['opsi'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    </script>
    <script>
    (function () {
        'use strict';

        var rowsWrap = document.getElementById('optionRows');
        var form     = document.getElementById('soalForm');
        var addBtn   = document.getElementById('addOptionBtn');
        var bobot    = [5, 4, 3, 2, 1];

        function buildRow(data) {
            data = data || {};

            var row = document.createElement('div');
            row.className = 'option-row';

            var input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'Teks pilihan';
            input.value = data.teks || '';

            var sel = document.createElement('select');
            bobot.forEach(function (b) {
                var o = document.createElement('option');
                o.value = String(b);
                o.textContent = 'Bobot ' + b;
                sel.appendChild(o);
            });
            sel.value = String(data.bobot || 5);

            var label = document.createElement('label');
            label.className = 'key-check';
            var key = document.createElement('input');
            key.type = 'checkbox';
            key.checked = !!data.kunci;
            label.appendChild(key);
            label.appendChild(document.createTextNode(' Kunci'));

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-option';
            remove.title = 'Hapus pilihan';
            remove.innerHTML = '&times;';
            remove.onclick = function () {
                if (rowsWrap.querySelectorAll('.option-row').length <= 2) {
                    alert('Pilihan jawaban minimal 2 buah.');
                    return;
                }
                row.parentNode.removeChild(row);
            };

            row.appendChild(input);
            row.appendChild(sel);
            row.appendChild(label);
            row.appendChild(remove);
            rowsWrap.appendChild(row);
        }

        (window.OptionSeed || []).forEach(buildRow);
        if (!rowsWrap.querySelectorAll('.option-row').length) {
            for (var i = 0; i < <?= (int) ($isEdit ? 2 : 4) ?>; i++) buildRow();
        }

        addBtn.onclick = function () { buildRow(); };

        // Nama field diberi indeks per baris saat submit agar
        // teks / bobot / kunci tetap selaras meski ada checkbox
        // yang tidak dicentang.
        form.addEventListener('submit', function () {
            var list = rowsWrap.querySelectorAll('.option-row');
            Array.prototype.forEach.call(list, function (row, i) {
                row.querySelector('input[type=text]').name = 'opt_teks[' + i + ']';
                row.querySelector('select').name = 'opt_bobot[' + i + ']';
                var key = row.querySelector('input[type=checkbox]');
                key.name = key.checked ? 'opt_kunci[' + i + ']' : '';
            });
        });
    })();
    </script>
<?php endif; ?>
<script src="../assets/js/ui.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
</body>
</html>
