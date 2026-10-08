# Soft Skills Assessment

Aplikasi asesmen soft skills untuk mahasiswa: interpersonal, komunikasi,
kecerdasan emosional, dan profil kecenderungan kepribadian.

Dibangun dengan **PHP Native** (tanpa framework), **MySQL**, dan **Apache**
melalui **Laragon**.

---

## Kebutuhan

| Komponen | Versi / Keterangan |
|---|---|
| PHP | 8.3.30 (bawaan Laragon) |
| Web server | Apache (Laragon) |
| Database | MySQL 8.4 (Laragon), port **3307** |
| Ekstensi PHP | `pdo_mysql` (sudah aktif) |
| Browser | Chrome / Edge / Firefox |

---

## Konfigurasi

Pengaturan database ada di `config/database.php`:

```
DB_HOST = localhost
DB_PORT = 3307
DB_NAME = softskill
DB_USER = root
DB_PASS = (kosong)
```

---

## Menjalankan

1. Buka **Laragon** → klik **Start All**
2. Buka browser → `http://localhost/soft-skills-assessment/`
3. Halaman peserta: `http://localhost/soft-skills-assessment/peserta/login.php`
4. Halaman admin: `http://localhost/soft-skills-assessment/admin/login.php`

---

## Struktur Folder

```
soft-skills-assessment/
├── index.php               pengalih ke halaman peserta
├── config/
│   └── database.php        koneksi PDO MySQL
├── peserta/
│   ├── login.php           identitas + token
│   ├── instruksi.php       petunjuk ujian
│   ├── ujian.php           pengerjaan 60 soal + timer
│   ├── proses_jawaban.php  endpoint AJAX simpan jawaban
│   ├── submit.php          pengumpulan + perhitungan skor
│   ├── acak_paket.php      acak ulang paket sesi (POST, untuk pengawas)
│   └── hasil.php           hasil + radar chart
├── admin/
│   ├── login.php           login pengelola
│   ├── dashboard.php       ringkasan statistik
│   ├── bank_soal.php       daftar bank soal + filter
│   ├── tambah_soal.php     tambah soal
│   ├── edit_bank_soal.php  edit soal
│   ├── hapus_bank_soal.php hapus / menonaktifkan soal
│   ├── token.php           daftar token
│   ├── tambah_token.php    tambah token
│   ├── hasil.php           daftar hasil ujian
│   └── detail_hasil.php    detail satu peserta
├── assets/
│   ├── css/style.css       gaya UI utama (tema ungu + mode gelap)
│   ├── css/tailwind.css    utilitas Tailwind hasil build (commit hasil build)
│   ├── js/app.js           timer, progress, renderer tipe soal, tema
│   └── images/
├── tailwind/
│   ├── tailwind.config.js  konfigurasi Tailwind (darkMode: 'class')
│   ├── input.css           sumber @tailwind
│   └── build.bat           perintah build CSS
└── database/
    ├── database.sql        struktur tabel + seed awal
    ├── seed_soal.sql       seed 60 soal
    └── migrasi_bank_soal.sql  migrasi ke tabel bank_soal (sekali jalan)
```

---

## Database

Nama database: **`softskill`**

Tabel:
`pengguna_admin`, `peserta`, `token_ujian`, `dimensi`, `kategori_soal`,
`tipe_profil`, `bank_soal`, `sesi_ujian`, `jawaban_peserta`, `hasil_ujian`

---

## Bank Soal & Tipe Jawaban

- Soal disimpan di tabel **`bank_soal`**: `kategori`, `pertanyaan`,
  `tipe_soal`, `pilihan_jawaban` (JSON), `jawaban` (JSON),
  `tingkat_kesulitan`, `status`, `created_at`.
- Kategori: *Situational Judgment Test (SJT)*, *Emotional Intelligence*,
  *Communication & Interpersonal Skills*, *Profil Kepribadian*.
- Tipe jawaban: `multiple_choice` (radio), `dropdown`, `checkbox`
  (boleh lebih dari satu), `scale` (skala 1–5).
- Setiap peserta mendapat **paket acak 60 soal** yang disimpan di
  `sesi_ujian` (`soal_ids`) → urutan tidak berubah saat refresh dan
  tidak diacak ulang saat klik *Berikutnya*.
- Admin mengelola soal lewat `admin/bank_soal.php` (tambah, edit,
  aktif/nonaktif, hapus).
- Migrasi sekali jalan:
  `mysql -u root -P 3307 softskill < database/migrasi_bank_soal.sql`

---

## Tampilan Ujian (Ala Format Gambar)

- Halaman **login, instruksi, dan ujian** memakai **Tailwind CSS** (utility)
  di atas `style.css` (tema ungu asli tidak berubah; preflight Tailwind
  dimatikan) sehingga tampilannya konsisten dari awal sampai akhir.
- Login: kartu dua panel (profil aplikasi + form), tombol *Reload token*,
  dan info "**paket soal yang berbeda (60 soal) — diacak otomatis saat login**".
  Halaman login **selalu menampilkan form** (nama, NIM, token); bila sesi ujian
  masih berjalan muncul banner hijau **"Sesi ujian kamu masih berjalan"** +
  tombol next **Lanjutkan Ujian**.
- Instruksi: statistik 60 soal / 30 menit / 4 dimensi, daftar aturan, **jam
  tanggal** (hari, tanggal, bulan, tahun + jam, bahasa Indonesia, update tiap
  detik) beraksen **amber/emas** supaya beda dari tema ungu, dan kotak info
  timer + tombol **← Kembali** (back ke login) dan **Lanjut →** (next ke ujian).
- Ujian: kartu soal tunggal, label kategori, petunjuk tipe soal
  (`#questionHint`), pilihan berbentuk kartu (radio bulat, checkbox kotak,
  skala 1–5, dropdown bergaya), pemisah putus-putus + navigasi
  **"Berikutnya →"** di kanan bawah.
- **Tombol "Selesaikan Tes ✓"** (`#finishBtn`, warna hijau) selalu tampil di
  samping *Berikutnya* → peserta boleh **mengumpulkan lebih awal** (misal baru
  mengerjakan 5 soal). Muncul konfirmasi berisi jumlah soal terjawab /
  kosong sebelum dikumpulkan; jawaban tersimpan yang sudah ada tetap ikut
  dinilai (`submit.php` menghitung `jumlah_dijawab` dari baris yang ada).
- Navigasi angka `.qnav-card` dengan legenda (aktif / terjawab /
  belum dijawab), smooth-scroll ke kartu soal, dan tombol panah
  **← / →** pada keyboard.
- **Urutan pilihan diacak per sesi** (disimpan di `$_SESSION['opsi_urut']`);
  soal bertipe `scale` tidak diacak urutannya.
- Header ujian menampilkan **info `.exam-note`**: *"Paket soal berbeda
  untuk tiap peserta"* (menggantikan tombol "Soal Baru").
- **Mode terang / gelap** via tombol ikon mata/bulan di halaman login,
  instruksi, dan ujian; preferensi disimpan di `localStorage.theme`,
  fallback ke `prefers-color-scheme`. Dihidupkan dengan class `dark`
  pada `<html>` (override variabel di `style.css`).
- Endpoint `peserta/acak_paket.php` (POST) tetap tersedia untuk mengacak
  ulang paket sesi dan membuang jawaban lama (mis. untuk pengawas).

### Build Tailwind

Hasil build sudah ter-commit (`assets/css/tailwind.css`), jadi langkah ini
hanya perlu diulang jika class baru ditambahkan:

```bat
tailwind\build.bat
:: atau, dari root proyek:
npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/input.css -o assets/css/tailwind.css --minify
```

> Jalankan **dari root proyek** — `content` pada config memakai path relatif.
> Nama class dinamis di JS harus masuk daftar safelist atau ditulis lengkap.

---

## Token

- Tabel `token_ujian` dengan `kode_token`, `batas_penggunaan`,
  `jumlah_dipakai`, `berlaku_sampai`, `status`.
- Semua token di-set **`batas_penggunaan = 4294967295`** (praktisnya
  *unlimited*) — kuota tidak pernah habis.
- Bila tidak ada token aktif, `peserta/login.php` **membuat token baru
  otomatis** (kuota unlimited, `berlaku_sampai = NULL`), sehingga peserta
  selalu bisa login.
- Admin mengelola token di `admin/token.php` + `admin/tambah_token.php`.

---

## Keamanan

- Session PHP untuk peserta & admin
- Prepared statement PDO (anti SQL injection)
- `password_hash()` / `password_verify()` untuk admin
- Validasi token, batas penggunaan, dan masa berlaku di **server**
- Validasi waktu ujian di **server** (`sesi_ujian.batas_waktu`)
- Pencegahan submit ganda (`hasil_ujian.sesi_id` UNIQUE)
- Output di-`htmlspecialchars()` (anti XSS)

---

## Catatan

File `Cloud Computing_Kel.4.html` pada folder tugas adalah **backup /
referensi** versi lama (satu file HTML). File tersebut tidak dihapus
dan tidak diubah.
