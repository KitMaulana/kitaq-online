<p align="center"><img src="../branding/kitaq-online-logo.png" alt="KITAQ ONLINE" width="320"></p>

# KITAQ ONLINE Design System
**Dashboard admin custom (tanpa Filament) · Layar siswa · Monitoring pengawas**
CiptaKita.id — *Solusi Digitalmu, dari kita!*

> Dokumen ini adalah acuan **wajib** untuk AI agent saat membangun antarmuka KITAQ ONLINE. Token, komponen, dan pola sama dengan KITAQ offline agar kedua produk terasa satu keluarga.
> Acuan visual interaktif: buka `design/admin-prototype.html` dan `design/exam-mobile-prototype.html` di browser.
> Pustaka CSS acuan: `design/kitaq-ui.css` (token + komponen dalam CSS biasa). Terjemahkan ke Tailwind v4 + komponen Blade `x-ui.*`; **tampilan akhir harus setara atau lebih baik dari prototipe**.

---

## 1. Prinsip desain

| Prinsip | Artinya di KITAQ |
|---|---|
| **Tenang & jelas** | Latar terang kebiruan, banyak ruang kosong, satu aksi utama per layar. Admin sering bekerja berjam-jam. |
| **Status terbaca sekali lirik** | Pengawas memantau 40 siswa. Warna status konsisten di seluruh aplikasi (lihat 3.3), selalu disertai ikon/teks, tidak hanya warna. |
| **Aman dari salah klik** | Aksi destruktif (hapus, tutup paksa, paksa submit) selalu lewat dialog konfirmasi yang menyebut objeknya. Ada *undo* via toast bila memungkinkan. |
| **Cepat** | Tabel server-side, debounce pencarian 300 ms, skeleton saat memuat, tanpa reload halaman penuh (Livewire). |
| **Satu bahasa visual** | Semua halaman hanya memakai komponen `x-ui.*` dan token. Dilarang CSS ad-hoc per halaman. |
| **Mandiri** | Tanpa CDN. Font Inter dan ikon Lucide dibundel lewat Vite. |

---

## 2. Token desain

Sumber kebenaran: `resources/css/kitaq-theme.css` (Tailwind v4 `@theme`) dan `design/kitaq-ui.css`.

### 2.1 Warna

| Token | Terang | Gelap | Pakai untuk |
|---|---|---|---|
| `--primary` | `#022684` | `#6C95FF` | Tombol utama, link, item aktif |
| `--primary-hover` | `#011A5C` | `#8AAEFF` | Hover tombol utama |
| `--accent` (merah) | `#F92019` | `#FF5A52` | Peringatan keras, huruf "Q", aksi bahaya |
| `--info` (biru) | `#0271D3` | `#4DA3FF` | Info, progres, grafik sekunder |
| `--bg` | `#F4F7FC` | `#0B1220` | Latar aplikasi |
| `--surface` | `#FFFFFF` | `#111A2E` | Kartu, tabel, modal |
| `--surface-2` | `#F1F5FB` | `#16213A` | Latar header tabel, input |
| `--border` | `#E3E9F4` | `#223054` | Garis pemisah |
| `--text` | `#0F172A` | `#E6ECF8` | Teks utama |
| `--text-muted` | `#5B6B88` | `#93A3C4` | Teks sekunder |
| Sidebar | gradasi `#011A5C → #022684` | `#0A1430 → #0E1D4A` | Navigasi (selalu gelap) |

### 2.2 Status ujian/siswa (dipakai di seluruh aplikasi)

| Status | Warna | Ikon | Label |
|---|---|---|---|
| Belum login | `#64748B` abu | `circle` | Belum masuk |
| Mengerjakan | `#16A34A` hijau | `pencil` | Mengerjakan |
| Peringatan | `#F59E0B` kuning | `triangle-alert` | Peringatan 1/2 atau 2/2 |
| **Terkunci** | `#F92019` merah | `lock` | Terkunci |
| Terputus | `#475569` abu tua | `wifi-off` | Terputus |
| Selesai | `#0271D3` biru | `check-circle` | Selesai |

### 2.3 Tipografi
- Font: **Inter** (400/500/600/700/800), dibundel via `@fontsource/inter`.
- Skala: 12 (caption) · 13 (tabel, label) · 14 (isi) · 16 (isi soal siswa, input mobile) · 20 (judul kartu) · 24 (judul halaman) · 32 (angka statistik).
- Judul halaman 24/700, `letter-spacing:-0.01em`. Angka tabel pakai `font-variant-numeric: tabular-nums`.
- Isi soal siswa minimal **16px**, `line-height:1.65`.

### 2.4 Bentuk, jarak, bayangan
- Radius: kartu 16 · kontrol (input/tombol) 10 · badge penuh (pill) · modal 18.
- Skala jarak 4 px: 4, 8, 12, 16, 20, 24, 32, 40.
- Bayangan: kartu `0 1px 2px rgba(2,38,132,.06), 0 4px 16px rgba(2,38,132,.06)`; modal/popover `0 12px 40px rgba(2,22,84,.22)`. Mode gelap: kurangi bayangan, andalkan border.
- Gerak: 150 ms `ease-out` untuk hover/fokus, 220 ms untuk modal/drawer. Hormati `prefers-reduced-motion`.

---

## 3. Layout aplikasi admin

```
┌───────────┬──────────────────────────────────────────────────────┐
│           │ Topbar: [☰]  Pencarian (Ctrl+K)   ● 128 online  🔔  🌙  👤 │
│  Sidebar  ├──────────────────────────────────────────────────────┤
│  264 px   │ Breadcrumb                                           │
│  (76 px   │ Judul halaman                       [Aksi utama]     │
│  dilipat) │ ──────────────────────────────────────────────────── │
│           │ Konten (maks 1440 px, padding 24 px)                 │
└───────────┴──────────────────────────────────────────────────────┘
```

- **Sidebar**: logo `kitaq-logo-dark.png`, grup menu berlabel (UTAMA, AKADEMIK, UJIAN, LAPORAN, SISTEM), item aktif = pil putih transparan + garis merah di kiri. Dapat dilipat (ikon saja + tooltip). Di HP/tablet menjadi **drawer**. Footer sidebar: versi + "CiptaKita.id".
- **Topbar** lengket (`backdrop-blur`): tombol lipat sidebar, pencarian global/command palette, **chip siswa online** (hijau "128 siswa online", kuning bila cron/antrean terlambat, klik → Kesehatan Sistem), lonceng notifikasi (pelanggaran baru), pengalih tema, menu pengguna (profil, ganti password, keluar).
- **Konten**: breadcrumb → judul halaman + deskripsi singkat + aksi utama di kanan → isi.
- Menu mengikuti **role**: Guru hanya Bank Soal, Ujian miliknya, Hasil mapelnya; Pengawas hanya Monitoring & Jadwal hari ini.

### 3.1 Navigasi (inventaris menu)

| Grup | Menu |
|---|---|
| UTAMA | Dashboard · Monitoring Live · Sesi & Token |
| AKADEMIK | Siswa · Kelas · Mata Pelajaran · Pengguna (admin/guru/pengawas) · Ruang & Sesi |
| UJIAN | Bank Soal · Ujian · Jadwal · Koreksi Uraian |
| LAPORAN | Rekap Nilai · Analisis Butir · Pelanggaran · Berita Acara |
| SISTEM | Pengaturan Sekolah · Backup · Log Aktivitas · Tentang KITAQ |

### 3.2 Command palette (Ctrl+K)
Pencarian cepat ke halaman, siswa (NISN/nama), ujian, serta aksi ("Tambah siswa", "Mulai ujian", "Unduh template"). Navigasi panah + Enter. Tersedia di semua halaman admin.

### 3.3 Responsif
- ≥1280 px: sidebar penuh. 768–1279 px (tablet pengawas): sidebar dilipat otomatis, grid monitoring 3–4 kolom. <768 px: sidebar menjadi drawer, tabel berubah menjadi daftar kartu.
- Layar siswa: mobile-first, lebar efektif 320–480 px.

---

## 4. Pustaka komponen Blade `x-ui.*`

Letakkan di `resources/views/components/ui/`. Komponen anonim dengan props bertipe, slot jelas, dan varian lewat atribut. Setiap komponen punya contoh di halaman internal `/admin/_ui` (hanya `APP_ENV=local`) sebagai katalog visual.

| Komponen | Varian / props | Catatan |
|---|---|---|
| `x-ui.button` | `variant`: primary, secondary, ghost, danger, success · `size`: sm, md, lg · `icon`, `loading`, `href` | Tinggi md 40 px, mobile 44 px. `loading` menampilkan spinner dan menonaktifkan klik. |
| `x-ui.card` | `title`, `subtitle`, slot `actions`, `padding` | Pembungkus semua blok konten. |
| `x-ui.stat` | `label`, `value`, `icon`, `tone`, `delta`, `spark` (array) | Kartu statistik dashboard. |
| `x-ui.badge` | `tone`: neutral, success, warning, danger, info, brand · `dot` | Pil kecil. Dipakai untuk **semua** status. |
| `x-ui.status-chip` | `status`: not_started, in_progress, warned, locked, disconnected, submitted | Memetakan status attempt ke warna+ikon+label (2.2). |
| `x-ui.table` | slot `head`, `body`, `empty` · `sticky`, `selectable` | Header lengket, baris hover, zebra opsional. |
| `x-ui.data-table` | Livewire + `WithDataTable`: `columns`, `filters`, `bulk-actions`, `search` | Cari, filter, urut, paginasi server-side, pilih massal, ekspor. |
| `x-ui.input` / `textarea` / `select` / `checkbox` / `switch` / `radio` | `label`, `hint`, `error`, `prefix/suffix icon` | Label di atas, pesan galat di bawah dalam Bahasa Indonesia. |
| `x-ui.file-drop` | `accept`, `max`, `multiple` | Area seret-lepas untuk Excel dan ZIP gambar, dengan progres. |
| `x-ui.tabs` | `tabs`, `active` | Navigasi dalam halaman. |
| `x-ui.modal` | `size`: sm, md, lg · `title` | Fokus terkunci di dalam, `Esc` menutup. |
| `x-ui.confirm` | `title`, `message`, `confirm-label`, `tone` | Dialog konfirmasi aksi destruktif. |
| `x-ui.drawer` | sisi kanan, lebar 480 px | Detail cepat siswa, log pelanggaran. |
| `x-ui.dropdown` | menu aksi baris | Untuk "⋯" di tabel. |
| `x-ui.toast` + `WithToast` | success, error, warning, info; opsi *undo* | Muncul kanan-atas, hilang 4 detik, `aria-live`. |
| `x-ui.empty-state` | `icon`, `title`, `text`, slot aksi | Wajib untuk tabel/daftar kosong. |
| `x-ui.skeleton` | baris, kartu | Saat Livewire memuat. |
| `x-ui.progress` | `value`, `tone` | Progres import, progres siswa. |
| `x-ui.avatar` | `name`, `src`, `size` | Inisial bila tanpa foto. |
| `x-ui.alert` | `tone`, `title` | Banner info/peringatan di halaman (mis. "Cron terakhir jalan 9 menit lalu"). |
| `x-ui.stepper` | `steps`, `current` | Wizard import (Unggah → Pratinjau → Hasil). |
| `x-ui.chart` | `type`, `labels`, `datasets` (Chart.js, bawaan warna token) | Garis, batang, donat. |
| `x-ui.kbd` | `Ctrl`, `K` | Penanda pintasan. |
| `x-ui.page-header` | `title`, `subtitle`, breadcrumb, slot `actions` | Dipakai di setiap halaman. |

Aturan implementasi:
- Tailwind v4 dengan token `@theme`; tidak ada warna hex mentah di Blade.
- Komponen interaktif memakai Alpine; data dan aksi server memakai Livewire 3.
- Semua teks lewat file bahasa `lang/id/*.php` (tidak ditulis keras di Blade).
- Setiap tombol ikon wajib `aria-label`. Setiap input wajib `label` terhubung.

---

## 5. Pola halaman

### 5.1 Halaman daftar (Siswa, Pengguna, Kelas, Mapel, Bank Soal, Ujian, Jadwal)
1. `page-header` dengan tombol utama **Tambah** dan tombol sekunder **Import** serta **Unduh Template**.
2. Baris alat: kolom pencarian, filter (kelas, status, mapel), jumlah hasil, tombol ekspor.
3. `data-table` dengan kotak centang. Saat ada baris dipilih, muncul **bar aksi massal** melayang di bawah ("12 dipilih · Reset password · Pindah kelas · Nonaktifkan · Hapus").
4. Aksi baris di menu "⋯" (Lihat, Ubah, Reset password, Hapus). Klik baris membuka **drawer** detail.
5. Paginasi 10/25/50/100 per halaman. State kosong dan memuat selalu ada.

### 5.2 Halaman form (tambah/ubah)
Kartu bertitle per kelompok isian, dua kolom di layar lebar, bar aksi lengket di bawah (**Simpan**, **Batal**), validasi inline, peringatan bila meninggalkan halaman dengan perubahan belum tersimpan.

### 5.3 Wizard import (Siswa, Pengguna, Soal)
`stepper` 3 langkah:
1. **Unggah**: `file-drop` Excel (+ ZIP gambar untuk soal), tombol unduh template, opsi (lewati/perbarui bila ada duplikat, buat kelas otomatis).
2. **Pratinjau & validasi**: tabel baris dengan penanda ✓ valid / ⚠ peringatan / ✕ galat, ringkasan "480 valid · 12 galat", filter "hanya galat", unduh laporan galat.
3. **Hasil**: berhasil X, dilewati Y, gagal Z, tombol "Unduh baris gagal" dan "Selesai". Import berat berjalan di antrean dengan `progress` langsung.

### 5.4 Editor soal
Tata letak dua panel: kiri **editor** (tipe soal, stimulus, teks soal TipTap, unggah gambar, opsi A–E dengan gambar, kunci, bobot, level, pembahasan), kanan **pratinjau tampilan siswa** (bingkai HP) yang diperbarui langsung. Navigasi nomor soal di kiri-atas, tombol *Simpan & Berikutnya*. Pintasan: `Ctrl+S` simpan, `Ctrl+Enter` simpan & berikutnya.

### 5.5 Monitoring Live (pengawas)
- Ringkasan status di atas (jumlah per status, sebagai chip yang bisa diklik untuk memfilter).
- **Grid kartu siswa** (auto-fill 220 px): foto/inisial, nama, kelas, status-chip, progres soal (12/40), sisa waktu, jumlah pelanggaran, **perangkat (OS · browser · mode aplikasi)**, IP. Garis kiri kartu mengikuti warna status. Kartu **Terkunci** berdenyut halus dan diurutkan ke atas.
- Panel kanan **Feed pelanggaran** (polling 4 dtk: waktu, siswa, jenis — termasuk split screen, terjemahan, ekstensi AI, dua perangkat, keyboard fisik) + tombol bisu alarm.
- Aksi per kartu: **Buka Kunci**, **Reset Login**, **Tambah Waktu**, **Paksa Submit**, **Lihat Log**. Aksi massal bertingkat (Jeda semua, Perpanjang semua, Tutup paksa) selalu minta konfirmasi.
- Bunyi alarm + toast merah saat ada kunci baru; bisa dibisukan.

### 5.6 Sesi & Token
Daftar sesi hari ini (jam, kelas, status, peserta masuk/total). Kartu **Token aktif** berukuran besar (monospace 48 px, dikelompokkan 3-3), **cincin hitung mundur** sampai token berganti, tombol *Layar Proyektor* (alamat situs + QR + token raksasa), *Salin*, dan sakelar *Wajib token*. Panel **Kesehatan sistem**: cron terakhir, antrean, rata-rata waktu respons API, siswa online. Panel **Profil keketatan** sesi (Longgar/Standar/Ketat) dengan ringkasan aturan.

### 5.7 Dashboard
Baris statistik (Siswa aktif, Ujian hari ini, Sedang berlangsung, Siswa online, Pelanggaran hari ini), grafik rata-rata nilai per mapel, grafik aktivitas pelanggaran per jam, daftar **Ujian hari ini** (jam, kelas, status, tombol Mulai/Pantau), daftar **Perlu perhatian** (siswa terkunci, login dari perangkat lain, cron terlambat).

### 5.8 Rekap & laporan
Filter di atas (ujian, kelas), kartu ringkas (rata-rata, tertinggi, terendah, % tuntas), histogram distribusi nilai, tabel peringkat, tombol ekspor Excel/PDF/format e-Rapor. Analisis butir: tabel soal dengan bilah tingkat kesukaran dan daya beda berwarna.

---

## 6. Layar siswa (mobile-first)

Acuan: `design/exam-mobile-prototype.html`.

- **Login**: logo KITAQ ONLINE + logo sekolah, kolom **NISN** (keyboard angka), kolom **Tanggal lahir** dengan petunjuk `DDMMYYYY · contoh 20102008` dan tombol lihat/sembunyi, tombol Masuk, tautan *Cek Perangkat*. Galat ramah ("NISN atau tanggal lahir salah. Sisa percobaan: 3"). Keyboard HP boleh muncul di halaman login (belum ada soal).
- **Dashboard siswa**: sapaan, kartu **Ujian Hari Ini** (tombol Mulai aktif hanya saat jam ujian), mendatang, riwayat & nilai. Banner "Pasang ke Layar Utama" untuk iPhone yang belum mode aplikasi.
- **Token**: 6 kotak karakter besar yang diisi lewat **papan token KITAQ** (huruf & angka tanpa I/O/0/1).
- **Konfirmasi mulai**: identitas, aturan bergambar, **daftar prapemeriksaan perangkat** (centang hijau/merah: browser, layar penuh/mode aplikasi, tidak split screen), tombol "Saya Mengerti & Mulai".
- **Ruang ujian**: header (mapel, status simpan, **timer server**), soal ≥ 16 px, opsi kartu besar (≥ 56 px), Ragu-ragu, peta soal (*bottom sheet*), watermark nama + NISN, bilah kuning "Koneksi terputus — jawaban aman di HP" bila offline.
- **Soal isian/uraian + Keyboard KITAQ**: kotak jawaban berbingkai yang menyala saat aktif dengan kursor berkedip; keyboard menempel di bawah (bilah atas "KEYBOARD KITAQ · Selesai"), tombol 44 px, warna abu muda/gelap seperti keyboard HP agar familier; navigasi bawah disembunyikan saat keyboard terbuka.
- **Penutup lokal** (otomatis hilang saat kondisi normal): *Layar terpisah terdeteksi*, *Kembali ke layar penuh* (dengan tombol), *Ujian terbuka di tab lain*. Latar navy gelap 96%.
- **Overlay peringatan** (kuning untuk 1, merah untuk terakhir) dan **layar terkunci** (merah gelap, gembok, alasan spesifik mis. "Fitur terjemahan browser terdeteksi", nama + NISN).
- **Hasil**: nilai besar, ringkasan benar/salah/kosong, status tuntas.

---

## 7. Aksesibilitas & kualitas
- Kontras teks minimal WCAG AA (4.5:1) di terang dan gelap.
- Semua elemen interaktif bisa dijangkau keyboard, fokus terlihat (`outline 2px` warna `--info`), urutan tab logis.
- Target sentuh ≥ 44 px di layar siswa dan tombol ikon mobile.
- Status tidak hanya dengan warna: selalu ikon + teks.
- `aria-live="polite"` untuk toast dan indikator autosave; modal memakai `role="dialog"` dan menjebak fokus.
- Hormati `prefers-reduced-motion` dan `prefers-color-scheme` (default mengikuti sistem, pilihan pengguna disimpan).
- Uji tampilan di 360×640, 768×1024, 1366×768, 1920×1080.

---

## 8. Struktur berkas yang diharapkan

```
resources/
  css/        app.css (impor tailwind + kitaq-theme.css), kitaq-theme.css
  js/         app.js, admin/ (command-palette.js, charts.js, sidebar.js, theme.js, toast.js), exam/ (...)
  views/
    layouts/  admin.blade.php, student.blade.php, projector.blade.php, guest.blade.php
    components/ui/   button, card, stat, badge, status-chip, table, input, select, modal, confirm, drawer, ...
    livewire/admin/  ...        livewire/student/  ...
  lang/id/    ui.php, validation.php, exam.php
```

Definisi selesai untuk antarmuka: setiap halaman di inventaris menu memakai `x-ui.*`, punya state kosong/memuat/galat, lolos cek responsif 4 ukuran layar, mode terang dan gelap, tanpa CDN, dan tampilannya sebanding dengan prototipe di `design/`.
