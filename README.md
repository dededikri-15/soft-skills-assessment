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
2. Buka aplikasi melalui browser: `http://localhost/soft-skills-assessment/`
3. Halaman peserta: `http://localhost/soft-skills-assessment/peserta/login.php`
4. Halaman admin: `http://localhost/soft-skills-assessment/admin/login.php`

---

## Struktur Folder

```text
soft-skills-assessment/
├── index.php
├── config/
│   └── database.php
├── peserta/
│   ├── login.php
│   ├── instruksi.php
│   ├── ujian.php
│   ├── proses_jawaban.php
│   ├── submit.php
│   ├── acak_paket.php
│   └── hasil.php
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── bank_soal.php
│   ├── tambah_soal.php
│   ├── edit_bank_soal.php
│   ├── hapus_bank_soal.php
│   ├── token.php
│   ├── tambah_token.php
│   ├── hasil.php
│   └── detail_hasil.php
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── tailwind.css
│   ├── js/
│   │   └── app.js
│   └── images/
├── tailwind/
│   ├── tailwind.config.js
│   ├── input.css
│   └── build.bat
└── database/
    ├── database.sql
    ├── seed_soal.sql
    └── migrasi_bank_soal.sql
```

*Catatan: Struktur di atas merupakan gambaran direktori utama aplikasi.*

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

Setiap peserta mendapatkan paket berisi 60 soal yang diacak saat sesi ujian dimulai. Urutan soal tersimpan selama sesi berlangsung sehingga tidak berubah ketika halaman dimuat ulang.

Admin dapat menambah, mengedit, mengaktifkan, menonaktifkan, dan menghapus soal melalui halaman pengelolaan bank soal.

### Build Tailwind CSS

File hasil build Tailwind CSS sudah disertakan dalam proyek. Build ulang hanya diperlukan jika terdapat penambahan atau perubahan class CSS.

Jalankan perintah berikut dari direktori utama proyek:

```bash
npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/input.css -o assets/css/tailwind.css --minify
```

---

## Tampilan Ujian

Aplikasi menggunakan Tailwind CSS dan CSS khusus dengan tema ungu serta dukungan mode terang dan gelap.

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
- Tampilan hasil asesmen dengan radar chart.
- Mode terang dan gelap dengan preferensi tema tersimpan.

Peserta dapat melanjutkan sesi ujian yang masih berjalan. Jawaban yang sudah tersimpan akan digunakan dalam proses penilaian ketika ujian dikumpulkan.

---

## Token Ujian

Token digunakan untuk mengatur akses peserta ke sesi asesmen.

Fitur pengelolaan token meliputi:

- Penambahan token oleh admin.
- Pengaturan batas penggunaan token.
- Pengaturan masa berlaku token.
- Pengaktifan dan penonaktifan token.
- Pembuatan token otomatis apabila tidak tersedia token aktif, sesuai implementasi aplikasi.

---

## Keamanan

Aplikasi menerapkan beberapa mekanisme keamanan, antara lain:

- Pengelolaan sesi PHP untuk peserta dan admin.
- Prepared statement PDO untuk membantu mencegah SQL injection.
- `password_hash()` dan `password_verify()` untuk pengelolaan kata sandi admin.
- Validasi token dan waktu ujian pada sisi server.
- Pencegahan pengumpulan hasil ujian ganda.
- `htmlspecialchars()` untuk membantu mencegah serangan XSS.

---

## Catatan

File `Cloud Computing_Kel.4.html` yang terdapat pada folder tugas merupakan salinan cadangan atau referensi versi lama aplikasi. File tersebut tetap dipertahankan dan tidak diubah.
