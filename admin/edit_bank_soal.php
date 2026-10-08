<?php
/**
 * admin/edit_bank_soal.php
 * ------------------------------------------------------------------
 * Mengubah soal yang sudah ada di bank soal (teks, kategori, tipe
 * jawaban, dimensi, skill, status) beserta daftar pilihan + bobotnya.
 * Dipanggil dengan ?id=<id_soal>.
 *
 * CATATAN: gerbang login admin belum aktif, konsisten dengan
 * halaman admin lainnya.
 */
require_once __DIR__ . '/../config/database.php';
session_start();

$kategoriList = [
    'Situational Judgment Test (SJT)',
    'Emotional Intelligence',
    'Communication & Interpersonal Skills',
    'Profil Kepribadian',
];

$tipeList = [
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

/** Bobot -> indikator (konsisten dengan data lama). */
function indikatorDariBobot(int $bobot): string
{
    if ($bobot >= 4) {
        return 'positif';
    }

    return $bobot === 3 ? 'netral' : 'negatif';
}

$db = getDB();
$dimensiList = $db->query("SELECT id, kode, nama FROM dimensi ORDER BY urutan")->fetchAll();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
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

/* ------------------------ isi awal form ------------------------ */
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

$errors = [];

/* --------------------------- simpan ---------------------------- */
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

    /* ------------------------ validasi ------------------------- */
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
            continue;
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

    if (!$errors) {
        try {
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
        } catch (Throwable $e) {
            error_log('[bank_soal] gagal update id ' . $id . ': ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan perubahan ke database. Coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Soal - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Edit Soal #<?= (int) $id ?></div></div>
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
            <div class="question-type">EDIT SOAL #<?= (int) $id ?></div>

            <?php if ($errors): ?>
                <div class="notice error">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="admin-form" method="post" id="soalForm" autocomplete="off"
                  action="edit_bank_soal.php?id=<?= (int) $id ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">

                <label for="pertanyaan">Pertanyaan</label>
                <textarea id="pertanyaan" name="pertanyaan" placeholder="Tulis situasi atau pertanyaan..."><?= htmlspecialchars($nilai['pertanyaan'], ENT_QUOTES, 'UTF-8') ?></textarea>

                <div class="form-grid">
                    <div>
                        <label for="kategori">Kategori</label>
                        <select id="kategori" name="kategori">
                            <?php
                            $opsiKategori = $kategoriList;
                            if (!in_array($nilai['kategori'], $opsiKategori, true)) {
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
                            <?php foreach ($tipeList as $kode => $nama): ?>
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
                    <button class="btn btn-primary" type="submit" style="width:auto;">Simpan Perubahan</button>
                    <a class="btn btn-secondary" href="bank_soal.php">Batal</a>
                </div>
            </form>
        </div>
    </main>

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
            buildRow(); buildRow();
        }

        addBtn.onclick = function () { buildRow(); };

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
</body>
</html>
