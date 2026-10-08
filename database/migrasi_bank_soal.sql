-- =====================================================================
-- database/migrasi_bank_soal.sql
-- ---------------------------------------------------------------------
-- MIGRASI BANK SOAL
-- Menjalankan seluruh perubahan struktur + data dari tabel `soal`
-- menjadi `bank_soal` beserta kolom-kolom baru yang dibutuhkan
-- fitur Bank Soal dan tipe jawaban yang beragam.
--
-- Isi migrasi (URUTAN WAJIB PERSIS SEPERTI DI BAWAH):
--   0. Perbaiki pasangan soal <-> pilihan jawaban (seed salah nomor +12)
--   1. Rename tabel `soal` -> `bank_soal`
--   2. Tambah kolom `kategori` lalu isi dari kategori_soal
--   3. Tipe input: radio/select/scale/card/ranking -> multiple_choice/dropdown/scale
--   4. Rename kolom (teks_soal->pertanyaan, tipe_input->tipe_soal,
--      dibuat_pada->created_at, diperbarui_pada->updated_at),
--      buang kategori_soal_id, tambah tingkat_kesulitan
--   5. Tambah kolom pilihan_jawaban JSON + jawaban JSON lalu isi dari
--      tabel pilihan_jawaban (id opsi = soal_id * 1000 + urutan)
--   6. Tambah 3 soal tipe checkbox kategori "Profil Kepribadian"
--   7. jawaban_peserta: buang FK ke tabel opsi, tambah pilihan_json
--   8. Buang tabel pilihan_jawaban (opsi sudah pindah ke JSON)
--
-- PENTING: jalankan SATU KALI pada database `softskill`
-- (phpMyAdmin / mysql CLI: mysql -P3307 -u root softskill < ...).
--
-- PENGAMAN: statement pertama yang dieksekusi di bawah adalah
--   SELECT COUNT(*) FROM soal;
-- sehingga bila file ini dijalankan ulang setelah migrasi selesai,
-- proses langsung berhenti dengan error
--   "Table 'softskill.soal' doesn't exist"
-- sebelum ada data yang tersentuh.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 0. PENJAGA: tabel `soal` masih ada = migrasi belum dijalankan
-- ---------------------------------------------------------------------
SELECT COUNT(*) FROM soal;


-- ---------------------------------------------------------------------
-- 0. PERBAIKAN PASANGAN SOAL - PILIHAN JAWABAN
--    seed_soal.sql menulis opsi jawaban pada nomor soal yang salah
--    (kurang 12). Yang benar: 4 baris terakhir (id terbesar) milik
--    setiap soal_id 1..60 adalah opsi dari seed, dipindahkan +12.
-- ---------------------------------------------------------------------
CREATE TEMPORARY TABLE tmp_opsi_shift AS
  SELECT id FROM (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY soal_id ORDER BY id DESC) AS rn
    FROM pilihan_jawaban
  ) t WHERE rn <= 4;

UPDATE pilihan_jawaban pj
JOIN tmp_opsi_shift x ON x.id = pj.id
SET pj.soal_id = pj.soal_id + 12;

DROP TEMPORARY TABLE tmp_opsi_shift;


-- ---------------------------------------------------------------------
-- 1. RENAME TABEL
-- ---------------------------------------------------------------------
RENAME TABLE soal TO bank_soal;


-- ---------------------------------------------------------------------
-- 2. KOLOM kategori (4 kategori sesuai ketentuan tugas)
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal
  ADD COLUMN kategori VARCHAR(100) NOT NULL DEFAULT '' AFTER id;

UPDATE bank_soal b
JOIN kategori_soal k ON k.id = b.kategori_soal_id
SET b.kategori = CASE k.kode
    WHEN 'SJT'          THEN 'Situational Judgment Test (SJT)'
    WHEN 'EMOTIONAL'    THEN 'Emotional Intelligence'
    WHEN 'COMMUNICATION' THEN 'Communication & Interpersonal Skills'
    WHEN 'INTERPERSONAL' THEN 'Communication & Interpersonal Skills'
    WHEN 'EQ'           THEN CASE b.skill
                                WHEN 'emotional' THEN 'Emotional Intelligence'
                                ELSE 'Communication & Interpersonal Skills'
                              END
    ELSE 'Profil Kepribadian'
  END;


-- ---------------------------------------------------------------------
-- 3. TIPE JAWABAN
--    Lebarkan dulu ke VARCHAR agar nilai baru bisa dimasukkan,
--    lalu petakan berdasarkan tipe asal + panjang teks pilihan.
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal MODIFY tipe_input VARCHAR(30) NOT NULL DEFAULT 'multiple_choice';

-- soal tipe ranking belum punya tampilan/penilaian -> nonaktif
-- (tetap tersimpan di bank soal dan bisa diaktifkan kembali dari admin)
UPDATE bank_soal SET status = 'nonaktif' WHERE tipe_input = 'ranking';

-- radio dengan pilihan pendek (label skala / angka) -> skala
UPDATE bank_soal b
JOIN (
    SELECT soal_id, MAX(CHAR_LENGTH(teks_pilihan)) AS max_len
    FROM pilihan_jawaban GROUP BY soal_id
) p ON p.soal_id = b.id
SET b.tipe_input = 'scale'
WHERE b.tipe_input = 'radio' AND p.max_len <= 25;

-- radio dengan pilihan teks panjang -> dropdown
UPDATE bank_soal b
JOIN (
    SELECT soal_id, AVG(CHAR_LENGTH(teks_pilihan)) AS avg_len
    FROM pilihan_jawaban GROUP BY soal_id
) p ON p.soal_id = b.id
SET b.tipe_input = 'dropdown'
WHERE b.tipe_input = 'radio' AND p.avg_len >= 60;

-- select -> dropdown ; radio sisa, card, ranking -> multiple_choice
-- (scale dipertahankan)
UPDATE bank_soal SET tipe_input = 'dropdown'        WHERE tipe_input = 'select';
UPDATE bank_soal SET tipe_input = 'multiple_choice' WHERE tipe_input IN ('radio', 'card', 'ranking');


-- ---------------------------------------------------------------------
-- 4. RENAME / TAMBAH KOLOM
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal
  DROP FOREIGN KEY fk_soal_kategori,
  DROP KEY idx_soal_kategori,
  DROP COLUMN kategori_soal_id,
  CHANGE COLUMN teks_soal pertanyaan TEXT NOT NULL,
  CHANGE COLUMN tipe_input tipe_soal ENUM('multiple_choice','dropdown','checkbox','scale')
           NOT NULL DEFAULT 'multiple_choice',
  CHANGE COLUMN dibuat_pada created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN diperbarui_pada updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
           ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE bank_soal
  ADD COLUMN tingkat_kesulitan ENUM('mudah','sedang','sulit')
           NOT NULL DEFAULT 'sedang' AFTER tipe_soal;


-- ---------------------------------------------------------------------
-- 5a. KOLOM pilihan_jawaban (JSON) - opsi pindah dari tabel opsi
--     id opsi = soal_id * 1000 + urutan (unik lintas soal, tanpa FK)
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal
  ADD COLUMN pilihan_jawaban JSON NULL AFTER petunjuk;

UPDATE bank_soal b
JOIN (
    SELECT pj.soal_id,
           JSON_ARRAYAGG(JSON_OBJECT(
               'id',       pj.soal_id * 1000 + pj.urutan,
               'urutan',   pj.urutan,
               'teks',     pj.teks_pilihan,
               'bobot',    pj.bobot,
               'indikator', pj.indikator
           )) AS arr
    FROM pilihan_jawaban pj
    GROUP BY pj.soal_id
) p ON p.soal_id = b.id
SET b.pilihan_jawaban = p.arr;


-- ---------------------------------------------------------------------
-- 5b. KOLOM jawaban (JSON) = kunci jawaban (id opsi bobot tertinggi)
--     untuk tipe multiple_choice / dropdown / checkbox.
--     Skala tidak punya jawaban "benar" -> NULL (skor = bobot terpilih).
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal
  ADD COLUMN jawaban JSON NULL AFTER pilihan_jawaban;

UPDATE bank_soal b
JOIN (
    SELECT pj.soal_id, JSON_ARRAYAGG(pj.soal_id * 1000 + pj.urutan) AS kunci
    FROM pilihan_jawaban pj
    JOIN (
        SELECT soal_id, MAX(bobot) AS max_bobot
        FROM pilihan_jawaban
        GROUP BY soal_id
    ) m ON m.soal_id = pj.soal_id AND pj.bobot = m.max_bobot
    GROUP BY pj.soal_id
) k ON k.soal_id = b.id
SET b.jawaban = CASE
    WHEN b.tipe_soal IN ('multiple_choice', 'dropdown', 'checkbox') THEN k.kunci
    ELSE NULL
  END;


-- ---------------------------------------------------------------------
-- 5c. INDEX
-- ---------------------------------------------------------------------
ALTER TABLE bank_soal
  ADD KEY idx_bank_soal_kategori (kategori),
  ADD KEY idx_bank_soal_tipe (tipe_soal);


-- ---------------------------------------------------------------------
-- 5d. Tingkat kesulitan awal berdasarkan panjang situasi/pertanyaan
--     (bisa diubah kembali lewat halaman edit bank soal)
-- ---------------------------------------------------------------------
UPDATE bank_soal SET tingkat_kesulitan = 'sulit' WHERE CHAR_LENGTH(pertanyaan) > 100;
UPDATE bank_soal SET tingkat_kesulitan = 'mudah' WHERE CHAR_LENGTH(pertanyaan) <= 60;


-- ---------------------------------------------------------------------
-- 6. SOAL BARU TIPE CHECKBOX (multi jawaban) - kategori Profil Kepribadian
--    id eksplisit 73-75 supaya id opsi di JSON bisa ditulis statis:
--    73001 = soal 73 opsi 1, 73002 = soal 73 opsi 2, dst.
-- ---------------------------------------------------------------------
INSERT INTO bank_soal
  (id, kategori, dimensi_id, skill, pertanyaan, tipe_soal, tingkat_kesulitan,
   petunjuk, pilihan_jawaban, jawaban, urutan, status)
VALUES
(73, 'Profil Kepribadian', 4, 'interpersonal',
 'Aspek apa saja yang menurutmu paling penting dalam sebuah kerja kelompok? Pilih lebih dari satu jawaban',
 'checkbox', 'sedang', 'Boleh memilih lebih dari satu',
 '[{"id":73001,"urutan":1,"teks":"Komunikasi yang terbuka antar anggota","bobot":5,"indikator":"positif"},'
 '{"id":73002,"urutan":2,"teks":"Pembagian tugas yang jelas dan adil","bobot":5,"indikator":"positif"},'
 '{"id":73003,"urutan":3,"teks":"Saling menghargai perbedaan pendapat","bobot":4,"indikator":"positif"},'
 '{"id":73004,"urutan":4,"teks":"Ketepatan waktu penyelesaian tugas","bobot":4,"indikator":"positif"},'
 '{"id":73005,"urutan":5,"teks":"Kreativitas dalam mencari solusi baru","bobot":3,"indikator":"netral"}]',
 '[73001,73002]', 73, 'aktif'),

(74, 'Profil Kepribadian', 2, 'communication',
 'Keterampilan apa saja yang ingin kamu kembangkan selama perkuliahan? Pilih lebih dari satu jawaban',
 'checkbox', 'sedang', 'Pilih yang paling ingin kamu tingkatkan',
 '[{"id":74001,"urutan":1,"teks":"Komunikasi verbal dan presentasi","bobot":5,"indikator":"positif"},'
 '{"id":74002,"urutan":2,"teks":"Manajemen waktu dan disiplin","bobot":5,"indikator":"positif"},'
 '{"id":74003,"urutan":3,"teks":"Menulis laporan dan dokumentasi","bobot":4,"indikator":"positif"},'
 '{"id":74004,"urutan":4,"teks":"Negosiasi dan penyelesaian konflik","bobot":4,"indikator":"positif"},'
 '{"id":74005,"urutan":5,"teks":"Kepemimpinan dan pengambilan keputusan","bobot":3,"indikator":"netral"}]',
 '[74001,74002]', 74, 'aktif'),

(75, 'Profil Kepribadian', 1, 'emotional',
 'Dalam situasi apa kamu paling nyaman menyampaikan pendapat? Pilih semua yang sesuai',
 'checkbox', 'sedang', 'Boleh memilih lebih dari satu situasi',
 '[{"id":75001,"urutan":1,"teks":"Diskusi kecil bersama beberapa orang","bobot":5,"indikator":"positif"},'
 '{"id":75002,"urutan":2,"teks":"Saat diminta langsung oleh dosen","bobot":4,"indikator":"positif"},'
 '{"id":75003,"urutan":3,"teks":"Melalui tulisan atau laporan tertulis","bobot":4,"indikator":"positif"},'
 '{"id":75004,"urutan":4,"teks":"Forum kelas dengan banyak peserta","bobot":3,"indikator":"netral"},'
 '{"id":75005,"urutan":5,"teks":"Percakapan melalui grup chat daring","bobot":3,"indikator":"netral"}]',
 '[75001]', 75, 'aktif');


-- ---------------------------------------------------------------------
-- 7. jawaban_peserta: pilihan ganda tersimpan di pilihan_json,
--    FK ke tabel opsi dibuang (tabelnya akan dihapus di langkah 8)
-- ---------------------------------------------------------------------
ALTER TABLE jawaban_peserta
  DROP FOREIGN KEY fk_jawaban_pilihan,
  DROP KEY fk_jawaban_pilihan,
  ADD COLUMN pilihan_json JSON NULL AFTER pilihan_id;


-- ---------------------------------------------------------------------
-- 8. Buang tabel opsi lama (sudah pindah ke bank_soal.pilihan_jawaban)
-- ---------------------------------------------------------------------
DROP TABLE pilihan_jawaban;
