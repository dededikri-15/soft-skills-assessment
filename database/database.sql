-- =====================================================================
-- SOFT SKILLS ASSESSMENT - Database Schema & Seed Data
-- ---------------------------------------------------------------------
-- Database : softskill
-- Engine   : MySQL 8.4 (Laragon)
-- Charset  : utf8mb4 / utf8mb4_general_ci
-- Dibuat   : untuk aplikasi PHP Native + PDO MySQL
--
-- CARA MENJALANKAN (pilih salah satu):
--   A) phpMyAdmin (Laragon > Database > phpMyAdmin)
--      1. pilih database "softskill" di panel kiri
--      2. klik tab "Import"
--      3. pilih file ini -> Go
--   B) Terminal VS Code (dari folder C:\laragon\www\soft-skills-assessment)
--      "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -P 3307 -u root softskill < database/database.sql
--
-- CATATAN: file ini BELUM dijalankan. Tunggu konfirmasi pemilik project.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. pengguna_admin
--    Login pengelola/pengawas ujian. Password disimpan sebagai hash.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS hasil_ujian;
DROP TABLE IF EXISTS jawaban_peserta;
DROP TABLE IF EXISTS sesi_ujian;
DROP TABLE IF EXISTS pilihan_jawaban;
DROP TABLE IF EXISTS soal;
DROP TABLE IF EXISTS tipe_profil;
DROP TABLE IF EXISTS kategori_soal;
DROP TABLE IF EXISTS dimensi;
DROP TABLE IF EXISTS token_ujian;
DROP TABLE IF EXISTS peserta;
DROP TABLE IF EXISTS pengguna_admin;

CREATE TABLE pengguna_admin (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)     NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    nama            VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NULL,
    peran           ENUM('admin','pengawas') NOT NULL DEFAULT 'admin',
    terakhir_login  DATETIME        NULL,
    dibuat_pada     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 2. peserta
--    Identitas mahasiswa yang mengikuti asesmen.
-- ---------------------------------------------------------------------
CREATE TABLE peserta (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nama            VARCHAR(100)    NOT NULL,
    nim             VARCHAR(30)     NOT NULL,
    email           VARCHAR(100)    NULL,
    kelas           VARCHAR(30)     NULL,
    dibuat_pada     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_peserta_nim (nim),
    KEY idx_peserta_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 3. token_ujian
--    Token yang dibagikan ke peserta. Memiliki batas penggunaan,
--    status aktif/nonaktif, dan masa berlaku.
-- ---------------------------------------------------------------------
CREATE TABLE token_ujian (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kode_token          VARCHAR(30)     NOT NULL,
    status              ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    batas_penggunaan    INT UNSIGNED    NOT NULL DEFAULT 1,
    jumlah_dipakai      INT UNSIGNED    NOT NULL DEFAULT 0,
    berlaku_mulai       DATETIME        NULL,
    berlaku_sampai      DATETIME        NULL,
    keterangan          VARCHAR(150)    NULL,
    dibuat_pada         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_kode (kode_token),
    CONSTRAINT ck_token_batas CHECK (jumlah_dipakai <= batas_penggunaan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 4. dimensi
--    Empat dimensi kecenderungan kepribadian.
-- ---------------------------------------------------------------------
CREATE TABLE dimensi (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kode            VARCHAR(5)      NOT NULL,   -- EI, SN, TF, JP
    nama            VARCHAR(50)     NOT NULL,
    nama_panjang    VARCHAR(80)     NOT NULL,
    label_tampilan  VARCHAR(30)     NOT NULL,   -- ENERGY / INFORMATION / ...
    label_positif   VARCHAR(30)     NOT NULL,   -- Extravert / Intuitive / ...
    label_negatif   VARCHAR(30)     NOT NULL,   -- Introvert / Sensing / ...
    urutan          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dimensi_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 5. kategori_soal
--    Kategori/jenis soal dalam bank soal.
-- ---------------------------------------------------------------------
CREATE TABLE kategori_soal (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kode            VARCHAR(20)     NOT NULL,   -- SJT, EQ, INTERPERSONAL, ...
    nama            VARCHAR(80)     NOT NULL,
    deskripsi       TEXT            NULL,
    urutan          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kategori_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 6. tipe_profil
--    16 kecenderungan hasil asesmen (hasil pengembangan dari modul 5).
--    Bukan diagnosis psikologis - hanya gambaran berdasarkan jawaban.
-- ---------------------------------------------------------------------
CREATE TABLE tipe_profil (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kode                CHAR(4)         NOT NULL,   -- INTJ, ENFP, dst
    label               VARCHAR(120)    NOT NULL,   -- Introverted - Intuitive - ...
    judul               VARCHAR(80)     NOT NULL,   -- The Strategic Thinker
    deskripsi           TEXT            NOT NULL,   -- ringkasan singkat
    deskripsi_panjang   TEXT            NOT NULL,   -- penjelasan lengkap
    kelebihan           JSON            NOT NULL,   -- ["Analitis", ...]
    perlu_dikembangkan  JSON            NOT NULL,   -- ["Terlalu fokus logika", ...]
    pengembangan        TEXT            NOT NULL,   -- arahan pengembangan
    PRIMARY KEY (id),
    UNIQUE KEY uq_profil_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 7. soal
--    Bank soal. Jumlah soal aktif dapat melebihi jumlah soal ujian
--    (misal 100/200 soal), sistem mengambil 60 soal aktif saat ujian.
--
--    tipe_input menentukan bentuk jawaban di halaman ujian:
--      radio   -> pilihan biasa (kartu pilihan)
--      select  -> dropdown
--      scale   -> skala pilihan (1-5)
--      card    -> pilihan berbentuk kartu situasi/tindakan
--      ranking -> urutan prioritas
-- ---------------------------------------------------------------------
CREATE TABLE soal (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kategori_soal_id INT UNSIGNED   NOT NULL,
    dimensi_id      INT UNSIGNED    NOT NULL,
    skill           ENUM('interpersonal','communication','emotional') NOT NULL,
    teks_soal       TEXT            NOT NULL,
    tipe_input      ENUM('radio','select','scale','card','ranking') NOT NULL DEFAULT 'radio',
    petunjuk        VARCHAR(150)    NULL,
    urutan          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    dibuat_pada     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_soal_status (status),
    KEY idx_soal_dimensi (dimensi_id),
    KEY idx_soal_kategori (kategori_soal_id),
    CONSTRAINT fk_soal_kategori
        FOREIGN KEY (kategori_soal_id) REFERENCES kategori_soal (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_soal_dimensi
        FOREIGN KEY (dimensi_id) REFERENCES dimensi (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 8. pilihan_jawaban
--    Opsi jawaban tiap soal beserta BOBOT yang dapat dikelola admin.
--    bobot 1-5 (5 = paling sesuai), indikator = arah/arah jawaban.
-- ---------------------------------------------------------------------
CREATE TABLE pilihan_jawaban (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    soal_id         INT UNSIGNED    NOT NULL,
    teks_pilihan    TEXT            NOT NULL,
    bobot           SMALLINT        NOT NULL DEFAULT 1,
    indikator       VARCHAR(30)     NULL,
    urutan          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pilihan_soal (soal_id),
    CONSTRAINT fk_pilihan_soal
        FOREIGN KEY (soal_id) REFERENCES soal (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_pilihan_bobot CHECK (bobot BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 9. sesi_ujian
--    Satu baris untuk setiap peserta yang mulai mengerjakan.
--    Waktu mulai & batas waktu disimpan di server agar timer
--    dapat divalidasi oleh PHP (bukan hanya JavaScript).
-- ---------------------------------------------------------------------
CREATE TABLE sesi_ujian (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kode_sesi       CHAR(12)        NOT NULL,
    peserta_id      INT UNSIGNED    NOT NULL,
    token_id        INT UNSIGNED    NOT NULL,
    jumlah_soal     SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    durasi_menit    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    waktu_mulai     DATETIME        NOT NULL,
    batas_waktu     DATETIME        NOT NULL,
    waktu_selesai   DATETIME        NULL,
    status          ENUM('berlangsung','selesai','kedaluwarsa','dibatalkan') NOT NULL DEFAULT 'berlangsung',
    sudah_submit    TINYINT(1)      NOT NULL DEFAULT 0,
    ip_address      VARCHAR(45)     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sesi_kode (kode_sesi),
    KEY idx_sesi_peserta (peserta_id),
    KEY idx_sesi_token (token_id),
    KEY idx_sesi_status (status),
    CONSTRAINT fk_sesi_peserta
        FOREIGN KEY (peserta_id) REFERENCES peserta (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sesi_token
        FOREIGN KEY (token_id) REFERENCES token_ujian (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 10. jawaban_peserta
--     Satu baris per soal per sesi. UNIQUE mencegah jawaban ganda.
--     pilihan_id dapat NULL untuk tipe scale/ranking bila menyimpan
--     nilai mentah, tetapi skor tetap dihitung dari bobot di DB.
-- ---------------------------------------------------------------------
CREATE TABLE jawaban_peserta (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    sesi_id         INT UNSIGNED    NOT NULL,
    soal_id         INT UNSIGNED    NOT NULL,
    pilihan_id      INT UNSIGNED    NULL,
    nilai           SMALLINT        NULL,   -- untuk tipe scale/ranking
    skor            SMALLINT        NOT NULL DEFAULT 0,  -- bobot terpilih
    waktu_jawab     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jawaban_sesi_soal (sesi_id, soal_id),
    KEY idx_jawaban_soal (soal_id),
    CONSTRAINT fk_jawaban_sesi
        FOREIGN KEY (sesi_id) REFERENCES sesi_ujian (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_jawaban_soal
        FOREIGN KEY (soal_id) REFERENCES soal (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_jawaban_pilihan
        FOREIGN KEY (pilihan_id) REFERENCES pilihan_jawaban (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- 11. hasil_ujian
--     Hasil akhir per sesi. satu sesi = satu hasil (UNIQUE).
--     Semua angka disimpan sebagai persentase 0-100.
-- ---------------------------------------------------------------------
CREATE TABLE hasil_ujian (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    sesi_id             INT UNSIGNED    NOT NULL,
    peserta_id          INT UNSIGNED    NOT NULL,
    token_id            INT UNSIGNED    NOT NULL,
    kode_profil         CHAR(4)         NOT NULL,
    skor_interpersonal  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    skor_communication  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    skor_emotional      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    skor_dimensi        JSON            NOT NULL,  -- {"EI":75,"SN":60,...}
    jumlah_dijawab      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    jumlah_soal         SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    total_skor          INT UNSIGNED    NOT NULL DEFAULT 0,
    ringkasan           TEXT            NULL,      -- bahan Modul 5 Core Competency
    dibuat_pada         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hasil_sesi (sesi_id),
    KEY idx_hasil_peserta (peserta_id),
    KEY idx_hasil_profil (kode_profil),
    CONSTRAINT fk_hasil_sesi
        FOREIGN KEY (sesi_id) REFERENCES sesi_ujian (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hasil_peserta
        FOREIGN KEY (peserta_id) REFERENCES peserta (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hasil_token
        FOREIGN KEY (token_id) REFERENCES token_ujian (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hasil_profil
        FOREIGN KEY (kode_profil) REFERENCES tipe_profil (kode)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- SEED DATA 1: admin
--    username: admin | password: admin123  (WAJIB diganti setelah login)
-- =====================================================================
INSERT INTO pengguna_admin (username, password_hash, nama, email, peran) VALUES
('admin', '$2y$10$2SPcpEHZqNlW1D/zqrkONOdhhw3rY64J7p.dqKqQcgLvxmna9FFdC', 'Administrator', 'admin@localhost.local', 'admin');


-- =====================================================================
-- SEED DATA 2: dimensi (4)
-- =====================================================================
INSERT INTO dimensi (kode, nama, nama_panjang, label_tampilan, label_positif, label_negatif, urutan) VALUES
('EI', 'Energi',      'Extraversion / Introversion',    'ENERGY',      'Extravert', 'Introvert', 1),
('SN', 'Informasi',   'Intuition / Sensing',            'INFORMATION', 'Intuitive', 'Sensing',   2),
('TF', 'Keputusan',   'Feeling / Thinking',             'DECISION',    'Feeling',   'Thinking',  3),
('JP', 'Gaya Hidup',  'Judging / Perceiving',           'LIFESTYLE',   'Judging',   'Perceiving',4);


-- =====================================================================
-- SEED DATA 3: kategori soal
-- =====================================================================
INSERT INTO kategori_soal (kode, nama, deskripsi, urutan) VALUES
('SJT',          'Situational Judgment Test',        'Menilai pilihan tindakan dalam situasi kerja/kelompok.', 1),
('EQ',           'Emotional & Communication Assessment', 'Menilai respons emosi dan gaya komunikasi.', 2),
('INTERPERSONAL','Interpersonal Skills',             'Menilai cara berinteraksi dengan orang lain.', 3),
('COMMUNICATION','Communication Skills',             'Menilai cara menyampaikan dan menerima informasi.', 4),
('EMOTIONAL',    'Emotional Intelligence',           'Menilai pengenalan dan pengelolaan emosi.', 5);


-- =====================================================================
-- SEED DATA 4: tipe profil (16) - dipindahkan dari Cloud Computing_Kel.4.html
-- =====================================================================
INSERT INTO tipe_profil (kode, label, judul, deskripsi, deskripsi_panjang, kelebihan, perlu_dikembangkan, pengembangan) VALUES
('INTJ','Introverted – Intuitive – Thinking – Judging','The Strategic Thinker',
 'Cenderung mandiri, analitis, terstruktur, dan suka memikirkan cara yang efektif untuk mencapai tujuan.',
 'Individu dengan kecenderungan INTJ biasanya menikmati proses memahami suatu masalah secara mendalam, menyusun strategi, dan mencari cara yang lebih efektif untuk menyelesaikan sesuatu. Mereka cenderung nyaman bekerja secara mandiri, tetapi tetap dapat berkontribusi ketika tujuan kelompok sudah jelas.',
 '["Analitis dan mampu melihat pola","Mandiri dalam menyelesaikan tugas","Memiliki perencanaan yang kuat","Berorientasi pada tujuan"]',
 '["Terkadang terlalu fokus pada logika","Dapat terlihat kurang ekspresif","Cenderung memiliki standar yang tinggi","Dapat kurang sabar terhadap proses yang lambat"]',
 'Latih kemampuan menyampaikan ide secara sederhana, dengarkan perspektif orang lain, dan berikan ruang terhadap pendekatan yang berbeda.'),

('INTP','Introverted – Intuitive – Thinking – Perceiving','The Analytical Explorer',
 'Cenderung analitis, ingin tahu, fleksibel, dan menikmati proses memahami bagaimana sesuatu bekerja.',
 'Kecenderungan INTP biasanya terlihat dari ketertarikan terhadap ide, konsep, dan pemecahan masalah. Individu dengan kecenderungan ini dapat menikmati kebebasan dalam menentukan cara kerja dan sering mempertanyakan apakah suatu sistem dapat dilakukan dengan cara yang lebih baik.',
 '["Analitis","Kritis terhadap informasi","Kreatif dalam mencari solusi","Terbuka terhadap ide baru"]',
 '["Terkadang terlalu banyak menganalisis","Kesulitan mempertahankan rutinitas","Dapat kurang memperhatikan aspek emosional","Kadang menunda penyelesaian"]',
 'Latih konsistensi, kemampuan menyelesaikan pekerjaan sampai tuntas, serta komunikasi interpersonal ketika menyampaikan kritik.'),

('ENTJ','Extraverted – Intuitive – Thinking – Judging','The Strategic Leader',
 'Cenderung aktif, berorientasi pada tujuan, tegas, dan menyukai perencanaan serta pencapaian target.',
 'Kecenderungan ENTJ biasanya terlihat melalui orientasi terhadap tujuan, keberanian mengambil keputusan, dan kemampuan mengorganisasi orang maupun sumber daya. Mereka sering nyaman ketika harus mengambil tanggung jawab dalam kelompok.',
 '["Berorientasi pada tujuan","Tegas dalam mengambil keputusan","Mampu mengorganisasi","Memiliki inisiatif"]',
 '["Dapat terlihat terlalu tegas","Kadang kurang sabar","Dapat terlalu fokus pada hasil","Perlu memperhatikan perasaan anggota tim"]',
 'Perkuat kemampuan mendengarkan, memberikan ruang bagi anggota tim, dan mempertimbangkan dampak keputusan terhadap orang lain.'),

('ENTP','Extraverted – Intuitive – Thinking – Perceiving','The Innovative Challenger',
 'Cenderung aktif, kreatif, suka mengeksplorasi ide dan menikmati tantangan intelektual.',
 'Kecenderungan ENTP biasanya terlihat dari rasa ingin tahu, kemampuan menghasilkan banyak alternatif, dan ketertarikan pada diskusi. Mereka dapat menikmati situasi yang memungkinkan kebebasan berpikir dan mencoba pendekatan baru.',
 '["Kreatif","Adaptif","Berani mengeksplorasi ide","Komunikatif dalam diskusi"]',
 '["Mudah berpindah fokus","Kurang menyukai rutinitas","Dapat terlalu sering mempertanyakan keputusan","Kadang kurang memperhatikan detail"]',
 'Latih konsistensi, manajemen waktu, dan kemampuan mengubah ide menjadi rencana yang dapat diselesaikan.'),

('INFJ','Introverted – Intuitive – Feeling – Judging','The Insightful Counselor',
 'Cenderung reflektif, memiliki perhatian terhadap orang lain, berorientasi pada makna, dan menyukai struktur.',
 'Kecenderungan INFJ biasanya terlihat dari kemampuan memahami perspektif orang lain, perhatian terhadap hubungan interpersonal, dan kecenderungan memikirkan tujuan yang lebih luas. Mereka dapat bekerja dengan tenang tetapi tetap memiliki kepedulian kuat terhadap lingkungan sosialnya.',
 '["Empatik","Reflektif","Memiliki tujuan yang jelas","Memperhatikan kebutuhan orang lain"]',
 '["Dapat terlalu memikirkan masalah","Mudah merasa terbebani","Cenderung memiliki ekspektasi tinggi","Perlu menjaga batas pribadi"]',
 'Latih kemampuan menetapkan batas, mengomunikasikan kebutuhan diri, dan tidak terlalu mengambil tanggung jawab atas masalah orang lain.'),

('INFP','Introverted – Intuitive – Feeling – Perceiving','The Reflective Idealist',
 'Cenderung reflektif, empatik, kreatif, fleksibel, dan memiliki nilai pribadi yang kuat.',
 'Kecenderungan INFP biasanya ditandai dengan perhatian terhadap nilai pribadi, empati, dan kemampuan melihat berbagai kemungkinan. Mereka dapat menikmati pekerjaan yang memberikan ruang untuk kreativitas dan makna personal.',
 '["Empatik","Kreatif","Terbuka terhadap kemungkinan","Memiliki nilai pribadi yang kuat"]',
 '["Mudah terpengaruh suasana","Kadang sulit mengambil keputusan","Dapat menunda pekerjaan","Terlalu idealis pada kondisi tertentu"]',
 'Latih pengambilan keputusan berbasis prioritas dan kemampuan mengubah ide menjadi langkah konkret.'),

('ENFJ','Extraverted – Intuitive – Feeling – Judging','The People-Oriented Leader',
 'Cenderung komunikatif, peduli terhadap orang lain, terorganisasi, dan nyaman membantu kelompok mencapai tujuan.',
 'Kecenderungan ENFJ biasanya terlihat dari kemampuan membangun hubungan, mengomunikasikan ide, dan memperhatikan perkembangan orang lain. Individu dengan kecenderungan ini sering nyaman mengambil peran yang melibatkan kerja sama dan koordinasi.',
 '["Komunikatif","Empatik","Mampu membangun hubungan","Terorganisasi"]',
 '["Dapat terlalu memikirkan penilaian orang lain","Terkadang sulit mengatakan tidak","Dapat mengambil terlalu banyak tanggung jawab","Perlu menjaga keseimbangan kebutuhan diri dan orang lain"]',
 'Latih kemampuan menetapkan batas, menerima perbedaan pendapat, dan membagi tanggung jawab secara proporsional.'),

('ENFP','Extraverted – Intuitive – Feeling – Perceiving','The Enthusiastic Explorer',
 'Cenderung antusias, kreatif, terbuka, komunikatif, dan tertarik mengeksplorasi berbagai kemungkinan.',
 'Kecenderungan ENFP biasanya terlihat dari energi dalam berinteraksi, rasa ingin tahu, dan kemampuan menemukan kemungkinan baru. Mereka dapat berkembang dalam lingkungan yang memberikan ruang untuk kreativitas dan interaksi.',
 '["Antusias","Kreatif","Komunikatif","Adaptif"]',
 '["Mudah kehilangan fokus","Kurang menyukai rutinitas","Dapat terlalu banyak memulai hal baru","Kadang kesulitan menentukan prioritas"]',
 'Perkuat manajemen prioritas dan konsistensi agar ide yang muncul dapat diwujudkan sampai selesai.'),

('ISTJ','Introverted – Sensing – Thinking – Judging','The Responsible Organizer',
 'Cenderung teliti, terstruktur, bertanggung jawab, dan memperhatikan aturan serta fakta.',
 'Kecenderungan ISTJ biasanya terlihat melalui sikap bertanggung jawab, perhatian terhadap detail, dan kenyamanan terhadap struktur. Mereka cenderung menyukai prosedur yang jelas dan dapat diikuti secara konsisten.',
 '["Teliti","Disiplin","Bertanggung jawab","Konsisten"]',
 '["Dapat kurang fleksibel","Terlalu berfokus pada aturan","Kurang nyaman dengan perubahan mendadak","Dapat terlihat terlalu serius"]',
 'Latih fleksibilitas dan kemampuan menerima pendekatan baru ketika kondisi membutuhkan perubahan.'),

('ISFJ','Introverted – Sensing – Feeling – Judging','The Supportive Guardian',
 'Cenderung bertanggung jawab, perhatian, teliti, dan memperhatikan kebutuhan orang lain.',
 'Kecenderungan ISFJ biasanya terlihat melalui kepedulian terhadap orang lain, konsistensi, dan kemampuan memperhatikan kebutuhan praktis dalam kelompok.',
 '["Peduli terhadap orang lain","Teliti","Setia pada tanggung jawab","Dapat diandalkan"]',
 '["Sulit mengatakan tidak","Dapat terlalu memprioritaskan kebutuhan orang lain","Kurang nyaman terhadap konflik","Cenderung menyimpan masalah sendiri"]',
 'Latih komunikasi asertif dan kemampuan menyampaikan kebutuhan pribadi secara terbuka.'),

('ESTJ','Extraverted – Sensing – Thinking – Judging','The Organized Executor',
 'Cenderung aktif, praktis, tegas, dan nyaman mengatur kegiatan agar berjalan sesuai target.',
 'Kecenderungan ESTJ biasanya terlihat dari orientasi terhadap hasil, struktur, dan pelaksanaan. Mereka dapat nyaman ketika memiliki tanggung jawab yang jelas dan target yang dapat diukur.',
 '["Organisatoris","Tegas","Praktis","Berorientasi pada hasil"]',
 '["Dapat terlalu langsung","Kurang sabar terhadap proses","Dapat terlalu fokus pada aturan","Perlu memperhatikan aspek emosional"]',
 'Perkuat kemampuan mendengarkan dan mempertimbangkan perspektif anggota kelompok.'),

('ESFJ','Extraverted – Sensing – Feeling – Judging','The Social Supporter',
 'Cenderung ramah, komunikatif, bertanggung jawab, dan memperhatikan keharmonisan kelompok.',
 'Kecenderungan ESFJ biasanya terlihat dari perhatian terhadap hubungan sosial, kebutuhan kelompok, dan keteraturan kegiatan.',
 '["Ramah","Kooperatif","Peduli terhadap kelompok","Terorganisasi"]',
 '["Terlalu memikirkan penerimaan sosial","Sulit menghadapi konflik","Dapat terlalu mengutamakan kebutuhan orang lain","Mudah merasa kecewa jika usaha tidak dihargai"]',
 'Latih kemampuan menerima kritik dan menjaga keseimbangan antara kebutuhan kelompok dan kebutuhan diri.'),

('ISTP','Introverted – Sensing – Thinking – Perceiving','The Practical Problem Solver',
 'Cenderung tenang, praktis, mandiri, fleksibel, dan suka menyelesaikan masalah secara langsung.',
 'Kecenderungan ISTP biasanya terlihat dari kemampuan mengamati masalah secara praktis dan mencari solusi yang dapat langsung diterapkan.',
 '["Praktis","Mandiri","Tenang menghadapi masalah","Fleksibel"]',
 '["Kurang menyukai aturan yang terlalu ketat","Dapat kurang ekspresif","Kadang terlalu fokus pada solusi","Dapat kehilangan minat terhadap rutinitas"]',
 'Latih komunikasi interpersonal dan kemampuan menjelaskan proses berpikir kepada orang lain.'),

('ISFP','Introverted – Sensing – Feeling – Perceiving','The Gentle Explorer',
 'Cenderung tenang, fleksibel, sensitif terhadap lingkungan, dan menghargai pengalaman personal.',
 'Kecenderungan ISFP biasanya terlihat dari kemampuan memperhatikan kondisi sekitar, empati, dan fleksibilitas dalam menghadapi situasi.',
 '["Empatik","Fleksibel","Observatif","Praktis"]',
 '["Kurang nyaman dengan konflik","Dapat sulit menyampaikan pendapat","Kurang menyukai struktur yang kaku","Cenderung memendam perasaan"]',
 'Latih komunikasi asertif dan kemampuan menyampaikan kebutuhan maupun pendapat secara terbuka.'),

('ESTP','Extraverted – Sensing – Thinking – Perceiving','The Action-Oriented Problem Solver',
 'Cenderung aktif, praktis, spontan, berani mengambil tindakan, dan cepat beradaptasi.',
 'Kecenderungan ESTP biasanya terlihat dari orientasi terhadap tindakan, kemampuan merespons situasi secara langsung, dan kenyamanan menghadapi perubahan.',
 '["Adaptif","Berani mengambil tindakan","Praktis","Komunikatif"]',
 '["Kurang menyukai perencanaan panjang","Dapat bertindak terlalu cepat","Kadang mengabaikan detail","Kurang nyaman dengan rutinitas"]',
 'Latih perencanaan jangka panjang dan evaluasi risiko sebelum mengambil tindakan.'),

('ESFP','Extraverted – Sensing – Feeling – Perceiving','The Energetic Supporter',
 'Cenderung ramah, aktif, spontan, komunikatif, dan menikmati interaksi dengan orang lain.',
 'Kecenderungan ESFP biasanya terlihat dari energi dalam lingkungan sosial, perhatian terhadap pengalaman nyata, dan kemampuan menciptakan suasana yang menyenangkan.',
 '["Ramah","Komunikatif","Adaptif","Empatik"]',
 '["Mudah terdistraksi","Kurang menyukai rutinitas","Dapat mengambil keputusan terlalu spontan","Perlu memperkuat perencanaan"]',
 'Latih manajemen waktu, perencanaan, dan kemampuan mempertimbangkan konsekuensi sebelum mengambil keputusan.');


-- =====================================================================
-- SEED DATA 5: token ujian contoh
-- =====================================================================
INSERT INTO token_ujian (kode_token, status, batas_penggunaan, jumlah_dipakai, berlaku_mulai, berlaku_sampai, keterangan) VALUES
('EQ2026A', 'aktif', 10, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Token demo kelas A'),
('EQ2026B', 'aktif', 10, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Token demo kelas B'),
('EQ2026C', 'aktif',  5, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Token demo kelas C'),
('EQ2026D', 'aktif',  5, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Token demo kelas D'),
('EQ2026X', 'nonaktif', 1, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Contoh token NONAKTIF (untuk uji validasi)');


-- =====================================================================
-- SEED DATA 6: 12 contoh soal + pilihan jawaban (bobot)
--    - 4 soal pertama dipindahkan dari Cloud Computing_Kel.4.html
--    - sisanya contoh untuk tipe select / scale / card / ranking
--    60 soal lengkap akan di-seed pada tahap berikutnya.
-- =====================================================================
INSERT INTO soal (kategori_soal_id, dimensi_id, skill, teks_soal, tipe_input, petunjuk, urutan, status) VALUES
(1, 1, 'communication', 'Dalam diskusi kelompok, kamu memiliki pendapat yang berbeda dengan mayoritas anggota. Apa yang paling mungkin kamu lakukan?', 'radio', NULL, 1, 'aktif'),
(1, 1, 'interpersonal', 'Kamu masuk ke lingkungan organisasi baru dan belum mengenal siapa pun. Apa yang biasanya kamu lakukan?', 'radio', NULL, 2, 'aktif'),
(2, 3, 'emotional',     'Ketika seseorang memberikan kritik terhadap pekerjaanmu, respons yang paling menggambarkan dirimu adalah...', 'radio', NULL, 3, 'aktif'),
(1, 4, 'interpersonal', 'Kelompokmu memiliki tugas dengan batas waktu tiga hari. Apa yang paling mungkin kamu lakukan?', 'radio', NULL, 4, 'aktif'),
(5, 2, 'communication', 'Ketika menerima tugas baru dari dosen, mana yang paling menggambarkan cara kerjamu?', 'select', 'Pilih satu pernyataan.', 5, 'aktif'),
(4, 1, 'emotional',     'Seberapa nyaman kamu menyampaikan pendapat di depan kelompok yang besar?', 'scale', '1 = Sangat tidak nyaman, 5 = Sangat nyaman', 6, 'aktif'),
(1, 3, 'interpersonal', 'Rekan kelompokmu terlambat mengerjakan bagian tugasnya dan mendekati batas pengumpulan. Apa tindakanmu?', 'card', 'Pilih kartu tindakan yang paling sesuai.', 7, 'aktif'),
(4, 4, 'communication', 'Urutkan prioritas berikut dari yang paling penting (1) hingga paling akhir (4) ketika menyelesaikan proyek kelompok.', 'ranking', 'Klik untuk mengatur urutan.', 8, 'aktif'),
(1, 2, 'emotional',     'Ketika kamu menghadapi situasi yang sangat menekan menjelang batas waktu pengumpulan, kamu cenderung...', 'radio', NULL, 9, 'aktif'),
(2, 3, 'communication', 'Dalam percakapan, hal mana yang paling sering kamu lakukan?', 'select', NULL, 10, 'aktif'),
(5, 1, 'interpersonal', 'Ketika ada anggota tim yang pendiam, apa yang biasanya kamu lakukan?', 'card', 'Pilih satu kartu.', 11, 'aktif'),
(4, 4, 'emotional',     'Seberapa mudah kamu menyesuaikan diri ketika rencana berubah secara mendadak?', 'scale', '1 = Sangat sulit, 5 = Sangat mudah', 12, 'aktif');

-- id soal mengikuti urutan insert: 1..12
-- soal 1 (radio)
INSERT INTO pilihan_jawaban (soal_id, teks_pilihan, bobot, indikator, urutan) VALUES
(1, 'Menyampaikan pendapat dengan jelas dan membuka ruang diskusi.', 5, 'positif', 1),
(1, 'Menunggu sampai ada anggota lain yang menyampaikan pendapat serupa.', 3, 'netral', 2),
(1, 'Mengikuti keputusan kelompok agar diskusi cepat selesai.', 2, 'netral', 3),
(1, 'Tidak menyampaikan pendapat karena takut menimbulkan perdebatan.', 1, 'negatif', 4),

(2, 'Mencoba menyapa dan membuka percakapan dengan anggota lain.', 5, 'positif', 1),
(2, 'Menunggu ada orang yang mengajak berbicara terlebih dahulu.', 3, 'netral', 2),
(2, 'Mengamati situasi cukup lama sebelum berinteraksi.', 2, 'netral', 3),
(2, 'Memilih tetap sendiri selama memungkinkan.', 1, 'negatif', 4),

(3, 'Saya langsung memahami bahwa kritik tersebut merupakan masukan yang perlu dipertimbangkan.', 5, 'positif', 1),
(3, 'Saya merasa kurang nyaman tetapi tetap mencoba mendengarkannya.', 4, 'positif', 2),
(3, 'Saya cenderung memikirkan alasan orang tersebut mengkritik saya.', 3, 'netral', 3),
(3, 'Saya merasa tersinggung dan sulit menerima kritik.', 2, 'negatif', 4),

(4, 'Membuat pembagian tugas dan jadwal penyelesaian sejak awal.', 5, 'positif', 1),
(4, 'Menentukan tugas utama terlebih dahulu lalu menyesuaikan sisanya.', 4, 'positif', 2),
(4, 'Mengerjakan bagian sendiri dan melihat perkembangan kelompok.', 3, 'netral', 3),
(4, 'Menunggu sampai mendekati batas waktu sebelum menentukan langkah.', 1, 'negatif', 4),

(5, 'Saya mencatat detail tugas dan membuat daftar langkah pengerjaan.', 5, 'positif', 1),
(5, 'Saya langsung mengerjakan bagian yang paling saya kuasai terlebih dahulu.', 4, 'positif', 2),
(5, 'Saya menunggu penjelasan tambahan sebelum memulai.', 3, 'netral', 3),
(5, 'Saya menyelesaikannya di saat terakhir agar tekanannya tidak terasa.', 1, 'negatif', 4),

(6, '1', 1, 'sangat_tidak_nyaman', 1),
(6, '2', 2, 'tidak_nyaman', 2),
(6, '3', 3, 'netral', 3),
(6, '4', 4, 'cukup_nyaman', 4),
(6, '5', 5, 'sangat_nyaman', 5),

(7, 'Menghubungi rekan tersebut dan menawarkan bantuan mengerjakan bagianya.', 5, 'positif', 1),
(7, 'Mengingatkan kembali tenggat waktu kepada rekan tersebut.', 4, 'positif', 2),
(7, 'Melaporkan ke ketua kelompok agar bagian tersebut dialihkan.', 3, 'netral', 3),
(7, 'Mengabaikan dan mengerjakan bagian saya sendiri.', 1, 'negatif', 4),

(8, 'Kualitas hasil akhir', 4, 'prioritas_1', 1),
(8, 'Ketepatan waktu pengumpulan', 3, 'prioritas_2', 2),
(8, 'Pembagian tugas yang adil', 2, 'prioritas_3', 3),
(8, 'Suasana kerja yang menyenangkan', 1, 'prioritas_4', 4),

(9, 'Tetap tenang, membuat daftar tugas, dan mengerjakan satu per satu.', 5, 'positif', 1),
(9, 'Menyusun ulang prioritas agar tugas terpenting selesai lebih dulu.', 4, 'positif', 2),
(9, 'Bekerja cepat tanpa rencana agar semua selesai bersamaan.', 3, 'netral', 3),
(9, 'Merasa cemas dan kesulitan menentukan harus mulai dari mana.', 1, 'negatif', 4),

(10, 'Saya banyak bertanya untuk memastikan saya memahami pembicaraan.', 5, 'positif', 1),
(10, 'Saya mendengarkan penuh sebelum memberikan tanggapan.', 4, 'positif', 2),
(10, 'Saya sesekali memberikan tanggapan ketika ada kesempatan.', 3, 'netral', 3),
(10, 'Saya lebih banyak diam dan menunggu percakapan selesai.', 2, 'netral', 4),

(11, 'Saya mengajaknya berbicara pelan dan memberi ruang untuk bicara.', 5, 'positif', 1),
(11, 'Saya melibatkannya pada bagian tugas yang sesuai kemampuannya.', 4, 'positif', 2),
(11, 'Saya membiarkannya dan hanya mendekati jika ia mau.', 3, 'netral', 3),
(11, 'Saya menganggap ia tidak tertarik bekerja sama.', 1, 'negatif', 4),

(12, '1', 1, 'sangat_sulit', 1),
(12, '2', 2, 'sulit', 2),
(12, '3', 3, 'netral', 3),
(12, '4', 4, 'mudah', 4),
(12, '5', 5, 'sangat_mudah', 5);
