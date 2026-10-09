# Soft Skills Assessment

Aplikasi asesmen soft skills untuk mahasiswa yang mencakup kemampuan interpersonal, komunikasi, kecerdasan emosional, dan profil kecenderungan kepribadian.

Aplikasi ini dibangun menggunakan **PHP Native**, **MySQL**, dan **Apache** melalui Laragon.

---

## Kebutuhan Sistem

| Komponen | Versi / Keterangan |
|---|---|
| PHP | 8.3.30 |
| Web Server | Apache |
| Database | MySQL 8.4 |
| Ekstensi PHP | `pdo_mysql` |
| Browser | Chrome, Edge, atau Firefox |

---

## Menjalankan Aplikasi

1. Buka Laragon, kemudian klik **Start All**.
2. Buka aplikasi melalui browser: `http://localhost:8080/soft-skills-assessment/`
3. Halaman peserta: `http://localhost:8080/soft-skills-assessment/peserta/login.php`
4. Halaman admin: `http://localhost:8080/soft-skills-assessment/admin/login.php`

> Sesuaikan port bila Apache berjalan pada port lain (misalnya `http://localhost/soft-skills-assessment/` untuk port 80).

---

## Status Pengembangan

| Bagian | Status |
|---|---|
| Alur peserta (login → instruksi → ujian → submit → hasil) | **Selesai**, terhubung database. |
| `admin/bank_soal.php` (tambah, edit, aktif/nonaktif, hapus, cari soal) | **Selesai**, terhubung database. |
| `admin/login.php`, `admin/dashboard.php`, `admin/token.php`, `admin/hasil.php` | **Kerangka dasar** — tampilan dan alur halaman sudah ada, akses database/auth masih ditandai `TODO` di dalam file. |

Sisi peserta sudah berjalan penuh, sedangkan penjaga session admin (`if (!isset($_SESSION['admin']))`) belum diaktifkan karena halaman login admin belum memverifikasi password.

---

## Struktur Folder

```text
soft-skills-assessment/
├── index.php
├── config/
│   ├── database.php
│   └── saran.php
├── peserta/
│   ├── login.php
│   ├── instruksi.php
│   ├── ujian.php
│   ├── proses_jawaban.php
│   ├── submit.php
│   ├── logout.php
│   └── hasil.php
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── bank_soal.php
│   ├── token.php
│   └── hasil.php
├── assets/
│   ├── partials/
│   │   ├── head.php
│   │   ├── header_admin.php
│   │   ├── header_peserta.php
│   │   └── theme_toggle.php
│   ├── css/
│   │   └── tailwind.css
│   ├── js/
│   │   ├── ui.js
│   │   └── app.js
│   └── images/
├── tailwind/
│   ├── tailwind.config.js
│   ├── input.css
│   └── build.bat
└── database/
    └── database.sql
```

*Catatan: Struktur di atas merupakan gambaran direktori utama aplikasi.*

### Satu file, banyak mode (query string)

Agar jumlah file tidak meledak, tiap file menangani beberapa layar lewat query string — tampilan tiap layar tetap sama:

| File | Mode |
|---|---|
| `admin/bank_soal.php` | daftar · `?tambah=1` · `?edit=<id>` · `?hapus=<id>` |
| `admin/token.php` | daftar · `?tambah=1` |
| `admin/hasil.php` | daftar · `?detail=<id>` |
| `peserta/ujian.php` | ujian · POST `aksi=acak` (acak ulang paket) |

### Isi setiap file

**Akar proyek**

| File | Fungsi |
|---|---|
| `index.php` | Pintu masuk; mengarahkan ke halaman login peserta. |
| `README.md` | Dokumentasi proyek ini. |

**`config/` — logika bersama**

| File | Fungsi |
|---|---|
| `database.php` | Koneksi PDO ke MySQL (`getDB()`), mulai session, helper kecil. |
| `saran.php` | `build_saran()` — merangkai kartu saran personal di halaman hasil (maks 3 kartu, masing-masing maks 3 langkah). |

**`admin/` — 5 file**

| File | Fungsi |
|---|---|
| `login.php` | Login admin. |
| `dashboard.php` | Ringkasan statistik (peserta, sesi, soal, token). |
| `bank_soal.php` | Daftar + pencarian/filter soal, tambah, edit, aktif/nonaktif, dan hapus soal (4 mode dalam satu file, lihat tabel di atas). |
| `token.php` | Daftar token ujian dan pembuatan token baru. |
| `hasil.php` | Daftar hasil ujian serta detail skor per peserta. |

**`peserta/` — 7 file**

| File | Fungsi |
|---|---|
| `login.php` | Login peserta dengan nama, NIM, dan token; membuka sesi ujian. |
| `instruksi.php` | Aturan dan informasi sebelum ujian dimulai. |
| `ujian.php` | Layar ujian: render soal, timer, progres, navigasi; juga menangani acak ulang paket. |
| `proses_jawaban.php` | Endpoint JSON untuk menyimpan jawaban selama ujian berlangsung (autosave). |
| `submit.php` | Mengumpulkan jawaban, menghitung skor, lalu menyimpan hasil. |
| `hasil.php` | Halaman hasil: skor, radar chart, kelebihan/area kembang, rekomendasi, dan saran personal. |
| `logout.php` | Mengakhiri sesi peserta. |

**`assets/` — dipakai semua halaman**

| File | Fungsi |
|---|---|
| `partials/head.php` | `<head>` bersama: meta, judul, link `tailwind.css` dengan cache-buster `filemtime`. |
| `partials/header_admin.php` | Topbar admin. |
| `partials/header_peserta.php` | Topbar peserta (termasuk info timer). |
| `partials/theme_toggle.php` | Tombol mode terang/gelap. |
| `css/tailwind.css` | File CSS hasil build — satu-satunya stylesheet yang dipakai. |
| `js/ui.js` | Tema terang/gelap (tersimpan di `localStorage`) dan jam live. |
| `js/app.js` | Khusus halaman ujian: render soal, timer mundur, autosave, auto-submit, progres. |
| `images/` | Aset gambar. |

**`tailwind/` — sumber CSS**

| File | Fungsi |
|---|---|
| `input.css` | Variabel warna tema (terang/gelap) dan komponen kustom, misalnya kartu saran. |
| `tailwind.config.js` | Palet warna dan daftar file yang di-scan. |
| `build.bat` | Menjalankan build Tailwind dalam satu klik. |

**`database/` — berkas SQL (satu file saja)**

| File | Fungsi |
|---|---|
| `database.sql` | Satu file untuk semua: skema 10 tabel + data awal (1 akun admin, 4 dimensi, 5 kategori soal, 16 tipe profil, 5 token demo, dan 175 baris `bank_soal` / 170 soal aktif). |

---

## Database

Database digunakan untuk menyimpan data peserta, akun admin, token ujian, bank soal, sesi ujian, jawaban, dan hasil asesmen.

Tabel utama meliputi:

- `pengguna_admin`
- `peserta`
- `token_ujian`
- `dimensi`
- `kategori_soal`
- `tipe_profil`
- `bank_soal`
- `sesi_ujian`
- `jawaban_peserta`
- `hasil_ujian`

### Menyiapkan database

Kredensial diatur di `config/database.php` (Laragon): host `localhost`, port `3307`, nama database `softskill`, user `root` tanpa password.

Jalankan **satu file saja**: `database/database.sql` — misalnya lewat phpMyAdmin (tab Import) atau terminal:

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -P 3307 -u root softskill < database/database.sql
```

File ini membuat seluruh tabel sekaligus mengisinya: 1 akun admin, 4 dimensi, 5 kategori soal, 16 tipe profil, 5 token demo (pemakaian 0), dan **175 baris bank soal (170 soal aktif)**. Perintahnya untuk database baru — file ini sengaja tidak memuat `DROP TABLE` agar tidak menghapus data yang sudah ada.

Token demo: `EQ2026A`–`EQ2026D` (aktif) dan `EQ2026X` (nonaktif, untuk uji validasi).

---

## Bank Soal dan Tipe Jawaban

Soal disimpan dalam tabel `bank_soal`, yang memuat kategori, pertanyaan, tipe soal, pilihan jawaban, kunci jawaban, tingkat kesulitan, status, dan waktu pembuatan.

Kategori asesmen meliputi:

- Situational Judgment Test (SJT)
- Emotional Intelligence
- Communication & Interpersonal Skills
- Profil Kepribadian

Tipe jawaban yang tersedia:

- `multiple_choice` — pilihan ganda menggunakan radio button.
- `dropdown` — pilihan melalui menu dropdown.
- `checkbox` — memilih lebih dari satu jawaban.
- `scale` — penilaian menggunakan skala 1–5.

Setiap peserta mendapatkan paket berisi 60 soal yang diacak saat sesi ujian dimulai. Karena tersedia 170 soal aktif, paket yang diterima tiap peserta berbeda — rata-rata hanya sekitar 20 soal yang sama antar peserta. Urutan soal tersimpan selama sesi berlangsung sehingga tidak berubah ketika halaman dimuat ulang.

Admin dapat menambah, mengedit, mengaktifkan, menonaktifkan, dan menghapus soal melalui halaman pengelolaan bank soal.

### Build Tailwind CSS

File hasil build Tailwind CSS sudah disertakan dalam proyek. Build ulang hanya diperlukan jika terdapat penambahan atau perubahan class CSS.

Jalankan perintah berikut dari direktori utama proyek:

```bash
npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/input.css -o assets/css/tailwind.css --minify
```

---

## Tampilan Ujian

Seluruh halaman (admin maupun peserta) memakai **satu sistem desain yang sama**: stylesheet `assets/css/tailwind.css`, partial bersama di `assets/partials/`, dan JavaScript umum `assets/js/ui.js`.

### Konsistensi antarhalaman

- `assets/partials/head.php` — doctype, meta, anti-kedip tema, dan link CSS untuk semua halaman.
- `assets/partials/header_admin.php` — topbar admin (judul + menu aktif + jam + tombol tema).
- `assets/partials/header_peserta.php` — topbar peserta (judul + identitas + timer/info + jam + tombol tema).
- `assets/partials/theme_toggle.php` — tombol terang/gelap.
- `assets/js/ui.js` — ganti tema (tersimpan di `localStorage`) dan jam live berbahasa Indonesia.

Tambahkan class Tailwind `sm:`/`md:`/`lg:` pada markup baru, lalu build ulang CSS agar utilitasnya ikut terbit.

### Mode terang dan gelap

- Mode gelap memakai palet **biru–ungu pekat**: latar `#0a0a1f`, kartu `#141136`, panel `#1b1750`, aksen `#8f8ff7`/`#b195ff`.
- Variabel warna didefinisikan di `tailwind/input.css` pada blok `:root` (terang) dan `.dark` (gelap), sehingga mengubah satu tempat langsung menyebar ke semua halaman.
- Preferensi tersimpan di `localStorage` dengan kunci `theme`; sistem `prefers-color-scheme` dipakai saat pengguna belum memilih.
- Chart pada halaman hasil ikut berganti warna mengikuti event `themechange`.

Fitur tampilan ujian meliputi:

- Halaman login peserta dengan nama, NIM, dan token.
- Halaman instruksi berisi aturan serta informasi ujian.
- Timer dan indikator progres pengerjaan.
- Navigasi antarsoal dan nomor soal.
- Pilihan jawaban sesuai tipe soal.
- Pengacakan soal dan pilihan jawaban per sesi.
- Tombol untuk menyelesaikan ujian lebih awal.
- Konfirmasi sebelum pengumpulan jawaban.
- Penyimpanan jawaban selama sesi berlangsung.
- Tampilan hasil asesmen dengan radar chart, kelebihan/area kembang, dan rekomendasi pengembangan.
- **Saran personal**: maksimal tiga kartu ringkas (tag, judul, tiga langkah latihan).
- Tombol **Cetak Hasil** serta **Kembali ke Login** (`peserta/logout.php`) di halaman hasil.
- Mode terang dan gelap dengan preferensi tema tersimpan.

### Saran personal di halaman hasil

Saran disusun otomatis oleh `config/saran.php` (`build_saran()`) dari dua sumber data:

- **Skor soft skills** — kartu muncul bila Interpersonal/Communication/Emotional Intelligence < 70 (dua terlemah saja).
- **Skor dimensi** (`skor_dimensi`: EI/SN/TF/JP) — kartu muncul bila kecenderungan menyimpang ≥ 15 poin dari tengah (50), misalnya Introvert dominan, Thinking dominan, atau Perceiving dominan.

Agar ringkas, keluaran dibatasi **maksimal 3 kartu** (2 soft skill + 1 dimensi paling menyimpang) dan **maksimal 3 langkah per kartu**. Kalau semua skor sehat, tampil satu kartu "Pertahankan".

Sifat sarannya: kecenderungan seperti introvert **tidak dianggap salah** — yang diberi latihan adalah agar sisi itu tidak berlebihan dan kepentingan peserta tetap tersampaikan. Ubah teks/aturan di `config/saran.php`; tidak perlu build ulang CSS kecuali menambah class baru.

Peserta dapat melanjutkan sesi ujian yang masih berjalan. Jawaban yang sudah tersimpan akan digunakan dalam proses penilaian ketika ujian dikumpulkan.

---

## Token Ujian

Token mengatur akses peserta ke sesi asesmen. Bagian yang sudah berjalan:

- `peserta/login.php` hanya menerima token berstatus **aktif** dan belum lewat masa berlaku, dengan pembanding waktu dari MySQL (`NOW()`), bukan dari browser.
- Batas penggunaan diperiksa saat login (`jumlah_dipakai < batas_penggunaan`), lalu jumlah pemakaian ditambah satu.
- Bila tidak ada token aktif sama sekali, aplikasi **membuat token baru secara otomatis** agar peserta tetap bisa masuk.

`admin/token.php` (daftar dan `?tambah=1`) berisi tampilan untuk mengelola kode token, batas penggunaan, masa berlaku, dan status — bagian akses database-nya masih kerangka (`TODO`).

---

## Keamanan

Mekanisme yang sudah berjalan pada sisi peserta:

- Session PHP untuk menjaga sesi ujian; umur session dibuat lebih lama dari durasi ujian.
- Prepared statement PDO pada seluruh query aplikasi.
- Validasi token (status, batas pakai, masa berlaku) dan batas waktu ujian di sisi server, memakai waktu dari MySQL.
- Pencegahan pengumpulan ganda: `peserta/submit.php` menolak permintaan bila sesi sudah `sudah_submit`.
- `htmlspecialchars()` untuk output berisiko terhadap XSS.

Masih berupa rencana (ditandai `TODO`): verifikasi kata sandi admin dengan `password_hash()`/`password_verify()` serta penjaga session di halaman admin.

---

## Catatan

Proyek ini tidak lagi memuat berkas `.html` — seluruh halaman ditulis sebagai PHP, ditata dengan CSS hasil build Tailwind, dan data disimpan di MySQL.
