# Catatan Keputusan (ADR ringkas) — KITAQ ONLINE

Format: tanggal — keputusan — alasan.

- 2026-11-18 — **KITAQ ONLINE dibuat sebagai aplikasi terpisah** dari KITAQ offline — kebutuhan berbeda (hosting, login, anti-contek). Bank soal & desain dipakai ulang.
- 2026-11-18 — **Laravel 12 + PHP 8.2 minimum** — keputusan pemilik. Catatan: perbaikan keamanan Laravel 12 berakhir 24 Feb 2027; untuk aplikasi online, upgrade ke Laravel 13 (PHP 8.3+) harus dijadwalkan sebelum tanggal itu. Kode menghindari fitur khusus versi agar upgrade mudah.
- 2026-11-18 — **Target shared/cloud hosting** — keputusan pemilik. Konsekuensi: polling HTTP (bukan WebSocket), session/cache/queue di database, cron → schedule:run, aset dibangun di laptop.
- 2026-11-18 — **Login siswa NISN + tanggal lahir (DDMMYYYY)** — permintaan pemilik. Ditambah token ujian berganti, rate limit, pembekuan, satu sesi aktif untuk menutup kelemahan password yang mudah ditebak.
- 2026-11-18 — **Probe internet & penguncian saat putus jaringan dihapus** — aplikasi online memang butuh internet; putus koneksi hanya dicatat (bisa diubah per ujian). Event pelanggaran disimpan di HP agar mode pesawat tidak menyembunyikan pelanggaran.
- 2026-11-18 — **Keyboard Virtual KITAQ; halaman ujian tanpa field teks** — satu-satunya cara web yang andal di Android, iOS, dan HarmonyOS untuk mencegah keyboard HP (dan fitur AI-nya) muncul. Bonus: deteksi split screen jadi andal karena viewport tidak berubah oleh keyboard.
- 2026-11-18 — **Halaman ujian tanpa Livewire** — CSP ketat tanpa `unsafe-eval`, DOM bersih untuk deteksi ekstensi, lebih ringan untuk HP & hosting.
- 2026-11-18 — **Keputusan pelanggaran di server (ViolationPolicy) dengan profil longgar/standar/ketat** — klien bisa dimodifikasi; sekolah butuh fleksibilitas untuk mengurangi alarm palsu.
- 2026-11-18 — **Browser default yang diizinkan: Chrome, Safari, Samsung Internet, Huawei Browser** — Edge/Opera/Brave memiliki asisten AI bawaan; Firefox Android memudahkan ekstensi; webview aplikasi tidak bisa layar penuh.
- 2026-11-18 — **iPhone wajib mode Layar Utama (PWA standalone)** — Safari di iPhone tidak mendukung Fullscreen API untuk halaman web.
- 2026-11-18 — **Aplikasi native (KITAQ Exam Browser) ditunda ke fase opsional** — butuh distribusi APK/App Store dan tidak berjalan di HarmonyOS NEXT tanpa versi ArkTS.

## Rencana implementasi (Prompt 0 — orientasi, 2026-11-18)

**Lokasi proyek**: `kitaq-online/` (hasil `composer create-project laravel/laravel:^12.0`) + seluruh isi starter kit disalin ke dalamnya. Starter kit asli dibiarkan utuh sebagai acuan.

**Paket composer (semua kompatibel Laravel 12 + PHP 8.2, tanpa WebSocket/Redis/Supervisor)** — `composer config platform.php 8.2.12` dipasang agar Composer tidak pernah memilih versi yang butuh PHP 8.3+:
| Paket | Fungsi |
|---|---|
| `livewire/livewire:^3.6` | Halaman admin interaktif (Alpine sudah ikut di bundel ESM Livewire; dibundel Vite, `inject_assets=false`) |
| `spatie/laravel-permission:^6` | Role super_admin, operator, guru, pengawas |
| `openspout/openspout:^4.28` | Baca/tulis XLSX secara streaming (import siswa/pengguna/soal, ekspor rekap). Dipilih langsung (bukan lewat maatwebsite/excel) karena hemat memori di shared hosting dan sel tanggal Excel langsung menjadi `DateTimeImmutable` yang diterima `StudentCredential::parseBirthDate` |
| `barryvdh/laravel-dompdf:^3.1` | Kartu login, berita acara, rekap PDF |
| `bacon/bacon-qr-code:^3` | QR SVG (kartu login, layar proyektor) tanpa imagick |
| `pragmarx/google2fa:^8` | 2FA TOTP opsional staf |
| `symfony/html-sanitizer:^7` | Sanitasi HTML soal dari TipTap/Excel |
| dev: `pestphp/pest:^3.8` + `pest-plugin-laravel` | Test (Pest 4 butuh PHP 8.3 → tidak dipakai) |

**Paket npm (dibundel Vite di laptop, tanpa CDN)**: `tailwindcss@4` + `@tailwindcss/vite`, `@fontsource/inter`, `chart.js@4`, `@tiptap/core|starter-kit|extension-image@2`, `katex`.

**Arsitektur**:
- Dua entry Vite: `resources/js/app.js` (admin, Livewire+Alpine) dan `resources/js/exam/exam-app.js` (ujian, vanilla). Entry kecil tambahan `resources/js/student/student.js` untuk halaman siswa non-ujian (papan token KITAQ, tanpa Livewire).
- API ujian berada di grup middleware `web` dengan prefix `/api` (butuh sesi `auth:student` + CSRF), bukan `routes/api.php` (stateless).
- Penilaian, kunci jawaban, pemetaan opsi acak, dan keputusan pelanggaran hanya di server (`ScoringService`, `AttemptService`, `ShieldService` → `ViolationPolicy`).
- Skema database dibuat sekaligus di awal (Bagian 6) agar migrasi stabil antar fase.
- Lokal: MySQL/MariaDB XAMPP; test: SQLite in-memory.

**Urutan**: Fase 1 → 9 sesuai `PROMPTS.md`; setiap fase diakhiri `php artisan test` + centang checklist.

## Keputusan implementasi Fase 1–9 (2026-11-18)

**Fase 1 — Fondasi**
- 2026-11-18 — **`design/kitaq-ui.css` di-port ke `resources/css/admin.css` dan dimuat sebagai `@import … layer(components)`** di Tailwind v4 — tampilan identik dengan prototipe, utilitas Tailwind tetap bisa menimpa. Komponen `x-ui.*` memakai kelas ini + token `@theme`. Kelas `.ring` diganti `.kq-ring` (bentrok dengan utilitas Tailwind `ring`).
- 2026-11-18 — **Ikon: sprite SVG gaya Lucide dari prototipe** (`x-ui.icons` + `x-ui.icon`), bukan paket npm Lucide — lebih ringan, tanpa CDN, sama persis dengan desain.
- 2026-11-18 — **Livewire + Alpine dibundel dari `vendor/livewire/livewire/dist/livewire.esm`** (`inject_assets=false`, `@livewireScriptConfig` ber-nonce). `wire:navigate` tidak dipakai agar polling monitoring & skrip halaman selalu bersih.
- 2026-11-18 — **Halaman siswa non-ujian punya entry ketiga `resources/js/student/student.js`** (tanpa Livewire). Halaman token memakai Keyboard KITAQ mode token; nilai dikirim lewat `<input type="hidden">` (tidak memunculkan keyboard HP).
- 2026-11-18 — **Sesi admin berakhir 2 jam tanpa aktivitas** (`KITAQ_ADMIN_IDLE_MINUTES`), wajib ganti password untuk Super Admin bawaan, 2FA TOTP opsional (`pragmarx/google2fa`). Password staf: min. 10 karakter, huruf + angka, bukan daftar password umum (cek lokal — tidak memanggil layanan luar demi privasi).

**Fase 2 — Data master & login NISN**
- 2026-11-18 — **Satu sesi aktif** memakai `students.current_session_id` + middleware `EnsureStudentSession`. Login baru di luar ujian menghapus sesi lama. Saat ujian aktif → login ditolak, event `second_instance` dicatat dengan aksi **log** (bukan kunci) + alarm pengawas. Alasan: login sudah ditolak sehingga tidak ada keuntungan integritas dari mengunci, dan mengunci memungkinkan teman sekelas yang tahu tanggal lahir "mengerjai" ujian orang lain. Penguncian `second_instance` tetap berlaku di ruang ujian (instance kedua yang benar-benar membuka ujian).
- 2026-11-18 — **Template Excel: sheet PETUNJUK dilewati dan baris contoh berawalan `#` diabaikan** (`App\Support\Xlsx::readAssoc($path, $sheet)`), sesuai petunjuk di template.
- 2026-11-18 — **Unggahan disalin ke path pendek sebelum dibaca ZipArchive** — nama berkas sementara Livewire > 260 karakter gagal dibuka libzip di Windows (XAMPP).
- 2026-11-18 — **Halaman data master sederhana memakai basis `CrudPage`** (Kelas, Mapel, Ruang, Sesi, Tahun Ajaran); Siswa & Pengguna punya komponen khusus (import, aksi massal, akomodasi, drawer detail).

**Fase 3 — Bank soal**
- 2026-11-18 — **Kunci jawaban tunggal di `questions.answer_key` (JSON)**; `question_options` hanya teks/gambar (`side` = left/right untuk menjodohkan). Lihat docblock `App\Models\Question`.
- 2026-11-18 — **Rumus ditulis `$…$` / `$$…$$` di TipTap dan dirender KaTeX di klien** (pratinjau admin & ruang ujian) — tetap tersimpan sebagai teks biasa yang aman disanitasi.
- 2026-11-18 — **Gambar soal disimpan privat (`storage/app/private/media`)**: staf via `/admin/media/…`, siswa via URL bertanda tangan 30 menit (`/m/{attempt}/{path}`) yang hanya berlaku untuk pemilik attempt yang belum selesai. HTML soal ditulis ulang di server saat dikirim.
- 2026-11-18 — **Menjodohkan dari Excel**: pernyataan kiri diambil dari pola `(1) … (2) …` di teks soal (sesuai contoh template), pilihan kanan dari opsi A–E.
- 2026-11-18 — **Sanitasi HTML memakai `symfony/html-sanitizer`** (allowlist tag format + `<img>` hanya dari `/admin/media/`).

**Fase 4 — Ujian & jadwal**
- 2026-11-18 — **Scheduler memakai satu perintah `kitaq:tick` tiap menit** (buka/tutup jadwal, auto-submit, deteksi heartbeat hilang, penanda cron) + `queue:work --stop-when-empty --max-time=50`. Job `AutoSubmitExpiredAttempts` & `DetectHeartbeatGaps` dipanggil sinkron di `kitaq:tick` agar tidak tertunda antrean.
- 2026-11-18 — **Jeda semua** mengembalikan waktu yang hilang ke `deadline_at` peserta dan `closes_at` jadwal saat dilanjutkan. Tutup jadwal → semua peserta aktif dikumpulkan + `AnalyzeIntegrity` diantrikan.
- 2026-11-18 — **Secret token ujian diturunkan dari `APP_KEY`** (HMAC) — tanpa variabel .env baru; konsekuensi: jangan mengganti `APP_KEY` di tengah semester.
- 2026-11-18 — **Profil keketatan default** dapat diatur di menu Pengaturan Sekolah (menimpa `KITAQ_SHIELD_PRESET` untuk ujian baru).

**Fase 5 — Ruang ujian (perbaikan di tempat pada kode referensi)**
- 2026-11-18 — `exam-app.js`: soal dirender dari **HTML tersanitasi server + KaTeX** (sebelumnya `textContent`); mode kanvas tetap teks polos. Ditambah: **Benar/Salah multi-pernyataan** dan **Menjodohkan** dengan ketukan (referensi memperlakukan BS seperti PG), prefetch 1 soal, gabungan jawaban lokal (offline-first) saat merender & membuka peta, overlay **dijeda** dan **instance lain**, tombol ukuran huruf, audio berbatas putar, info soal belum dijawab di modal kumpul, dan **sinkron jawaban dilanjutkan otomatis setelah kunci dibuka** (referensi menghentikan `AnswerSync` permanen setelah HTTP 423).
- 2026-11-18 — Overlay peringatan tidak lagi ditutup oleh heartbeat berikutnya; hanya oleh tombol "Saya mengerti".
- 2026-11-18 — `exam.blade.php`: menerima objek tampilan `$exam` (title, subject, durasi, jumlah soal), ditambah ringkasan identitas, pemberitahuan pemantauan (transparansi UU PDP), dan overlay baru. Tetap tanpa `<input>/<textarea>/contenteditable`/Livewire (diuji otomatis).
- 2026-11-18 — **Opsi dikirim dengan kunci tampilan (A, B, …) yang dipetakan ke kunci asli di server** (`exam_attempts.option_order`, tersembunyi). Soal berstimulus sama tetap berdekatan saat diacak.

**Fase 6 — KITAQ Shield server**
- 2026-11-18 — **Idempotensi event per `(attempt, instance_id, seq)`**: `seq` dihitung per tab di localStorage sehingga perangkat baru setelah Reset Login mulai lagi dari 1. Event dari server memakai `seq = null`.
- 2026-11-18 — **Waktu kejadian = jam server dikurangi selisih (`client_now − at`)**, lalu dijepit ke `[started_at, now]`.
- 2026-11-18 — **Ambil alih instance**: tab baru di sesi yang sama boleh menggantikan instance lama bila instance lama sudah diam > max(30 dtk, 2×heartbeat+10) — mencegah kunci palsu saat browser HP menutup tab karena RAM. Bila instance lama masih hidup → `second_instance` (diputuskan `ViolationPolicy`). Setelah Reset Login, perangkat baru wajib token (klaim di sesi) sebelum menjadi aktif.
- 2026-11-18 — **Nonce heartbeat null diterima** (muat ulang halaman); nonce salah → `heartbeat_invalid` (log, maks. 1/menit).
- 2026-11-18 — `ViolationPolicy::REASONS` ditambah alasan event server (`heartbeat_invalid`, `offline_lock`, `location_blocked`, dll.). `offline_lock` & `heartbeat_gap` dimasukkan sebagai pengecualian otomatis di `Exam::policy()` (log, atau kunci bila ujian diatur "kunci setelah N detik").
- 2026-11-18 — Bug yang ditemukan saat pengujian: `state() + [...]` di PHP tidak menimpa kunci `status` → diganti `array_merge` (instance kedua kini benar-benar menerima `blocked_instance`).

**Fase 7 — Monitoring**
- 2026-11-18 — **Halaman monitoring = Blade + Alpine (polling JSON)**, bukan Livewire — satu request ringan per 4 dtk. Ringkasan kartu di-cache 2 dtk per jadwal (`kitaq:mon:{id}`), event delta lewat `since`, alarm lewat `alert_since` (−1 pada poll pertama). Alarm suara Web Audio (tanpa berkas), notifikasi browser diminta sekali, judul tab berkedip bila ada yang terkunci.
- 2026-11-18 — Perintah ke HP siswa (pesan, tambah waktu, kumpulkan paksa) lewat tabel `attempt_commands`, diambil heartbeat berikutnya.

**Fase 8 — Laporan & integritas**
- 2026-11-18 — **Penilaian**: PG penuh; PG Kompleks harus tepat semua (mencegah "centang semua"); Benar/Salah & Menjodohkan parsial; isian cocok salah satu variasi (abaikan besar-kecil/spasi; isian angka menerima format Indonesia 1.500,5); uraian menunggu guru lalu nilai dihitung ulang.
- 2026-11-18 — **Analisis butir klasik**: p = proporsi benar; D = p(27% atas) − p(27% bawah); sebaran pengecoh PG.
- 2026-11-18 — **`IntegrityAnalyzer`**: jawaban salah identik ditandai bila ≥ 4 soal dan (z ≥ 2 terhadap semua pasangan **atau** ≥ 80% jawaban salah keduanya identik — z-score kurang peka di kelas kecil); kecepatan: ≥ 3 soal panjang (> 400 karakter) benar dalam < 8 dtk; perangkat bersama: hash UA **dan** IP sama dipakai ≥ 2 NISN. Semua hasil berstatus "perlu ditinjau"; tinjauan tidak mengubah nilai.
- 2026-11-18 — Ekspor Excel/PDF (rekap, e-Rapor, pelanggaran, analisis butir, data siswa) lewat job `ExportReport` di antrean database; unduhan hanya pemilik/Super Admin dan tercatat di log aktivitas.

**Fase 9 — Siap deploy**
- 2026-11-18 — **Backup**: mysqldump bila `proc_open` diizinkan, selain itu ekspor per tabel lewat PDO; ZIP AES-256 dengan password turunan `APP_KEY`; retensi 14 hari; jadwal 01.30. Pembersihan log 02.00 (`kitaq:prune`, `KITAQ_LOG_RETENTION_DAYS` = 365).
- 2026-11-18 — **`kitaq:seed-load`** membuat siswa NISN `9000000001…` (cocok dengan `tests/load/k6-exam.js`) + jadwal terbuka tanpa token. **`KITAQ_LOAD_TEST=true`** melewati gerbang browser agar k6 bisa login — wajib dimatikan saat ujian sungguhan (Kesehatan Sistem memberi tanda merah).
- 2026-11-18 — **Rute `/install?token=`** hanya aktif bila `KITAQ_INSTALL_TOKEN` ≥ 24 karakter; token salah → 404 + rate limit; berjalan sekali lalu terkunci oleh `storage/app/install.lock`.
- 2026-11-18 — Rute tanpa closure → `php artisan optimize` (config/route/view cache) aman dan diverifikasi. Skrip rilis menjalankan test dulu dan tidak menyertakan `storage/app/private` (backup, ekspor, media lokal).
