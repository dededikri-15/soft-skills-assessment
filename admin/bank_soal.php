<?php
/**
 * admin/bank_soal.php
 * ------------------------------------------------------------------
 * Daftar soal pada Bank Soal: pencarian/filter, ubah status aktif,
 * pintu ke halaman edit dan hapus.
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

$tipeList = [
    'multiple_choice' => 'Pilihan tunggal',
    'dropdown'        => 'Dropdown',
    'checkbox'        => 'Checkbox (multi)',
    'scale'           => 'Skala / rating',
];

$pesan = [
    'toggle'       => ['success', 'Status soal berhasil diubah.'],
    'hapus_ok'     => ['success', 'Soal berhasil dihapus dari bank soal.'],
    'hapus_nonaktif' => ['error', 'Soal punya jawaban peserta sehingga tidak bisa dihapus, statusnya diubah menjadi nonaktif.'],
    'hapus_batal'  => ['error', 'Penghapusan dibatalkan.'],
    'hapus_gagal'  => ['error', 'Soal gagal dihapus dan gagal dinonaktifkan. Periksa log server.'],
    'simpan_baru'  => ['success', 'Soal baru berhasil ditambahkan ke bank soal.'],
    'simpan_edit'  => ['success', 'Perubahan soal berhasil disimpan.'],
];

$db = getDB();

/* ---------------------------------------------------------- filter */
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

/* ------------------------------------------------- ubah status (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare(
            "UPDATE bank_soal SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = ?"
        );
        $stmt->execute([$id]);
    }
    header('Location: ' . $urlPesan('toggle'));
    exit;
}

/* ---------------------------------------------------------- ambil data */
$where   = [];
$params  = [];
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

$msgKey = (string) ($_GET['msg'] ?? '');
$msg = $pesan[$msgKey] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Soal - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="exam-header">
        <div class="container exam-header-inner">
            <div><div class="exam-title">Admin Panel</div><div class="exam-user">Bank Soal</div></div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="bank_soal.php">Bank Soal</a>
                <a href="token.php">Token</a>
                <a href="hasil.php">Hasil</a>
            </nav>
        </div>
    </header>

    <main class="exam-container">

        <div class="info-grid" style="margin-bottom:24px;">
            <div class="info-box"><strong><?= (int) $ringkas['total'] ?></strong> Total Soal</div>
            <div class="info-box"><strong><?= (int) $ringkas['aktif'] ?></strong> Aktif</div>
            <div class="info-box"><strong><?= (int) $ringkas['nonaktif'] ?></strong> Nonaktif</div>
            <div class="info-box"><strong><?= (int) $ringkas['unik_aktif'] ?></strong> Unik untuk Ujian</div>
        </div>

        <div class="question-card">
            <div class="question-type">BANK SOAL</div>

            <?php if ($msg): ?>
                <div class="notice <?= htmlspecialchars($msg[0], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($msg[1], ENT_QUOTES, 'UTF-8') ?>
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
                                    <a class="act-edit" href="edit_bank_soal.php?id=<?= (int) $r['id'] ?>">Edit</a>
                                    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                        <input type="hidden" name="aksi" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="act-edit">
                                            <?= $r['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <a class="act-danger" href="hapus_bank_soal.php?id=<?= (int) $r['id'] ?>">Hapus</a>
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
                <a class="btn btn-primary" href="tambah_soal.php" style="width:auto;">+ Tambah Soal</a>
            </div>
        </div>

    </main>
</body>
</html>
