# KITAQ ONLINE — Prompt Siap Tempel per Fase (Claude Code)

> Tempel satu prompt, tunggu selesai, periksa hasil, baru lanjut. Jika agent berhenti: **"Lanjutkan Fase X dari checklist yang belum tercentang."**

### 🟦 Prompt 0 — Orientasi
```
Baca SPESIFIKASI_KITAQ_ONLINE.md, CLAUDE.md, docs/ANTI_CONTEK.md, docs/DESIGN_SYSTEM.md, docs/DEPLOY_SHARED_HOSTING.md,
docs/KEAMANAN.md, config/kitaq.php, semua file di resources/js (shield, keyboard, exam), resources/views/layouts/exam.blade.php,
app/Services, app/Http/Middleware, dan tests/Unit. Buka juga design/admin-prototype.html dan design/exam-mobile-prototype.html.
Jangan menulis kode dulu. Ringkas pemahamanmu, sebutkan paket composer & npm yang kompatibel dengan Laravel 12 dan PHP 8.2
(tanpa fitur PHP 8.3+) dan bisa jalan di shared hosting (tanpa WebSocket/Redis/Supervisor), lalu tulis rencana ke docs/DECISIONS.md.
```

### 🟦 Prompt 1 — Fondasi
```
Kerjakan FASE 1. Laravel 12 dengan "php": "^8.2". Tailwind v4 + resources/css/kitaq-theme.css, Inter lokal.
Vite punya dua entry: resources/js/app.js (admin) dan resources/js/exam/exam-app.js (ujian siswa).
Bangun layout dashboard admin CUSTOM (tanpa Filament) sesuai docs/DESIGN_SYSTEM.md dan design/admin-prototype.html:
sidebar, topbar, terang/gelap, Ctrl+K, dan pustaka komponen Blade x-ui.*. Pasang spatie/laravel-permission
(super_admin, operator, guru, pengawas), guard `student` berbasis kolom nisn, daftarkan middleware SecurityHeaders,
session/cache/queue driver database. Seeder Super Admin (superadmin / KitaQ#2026, wajib ganti).
Footer "KITAQ ONLINE v{versi} · © {tahun} CiptaKita.id — Solusi Digitalmu, dari kita!". Tanpa CDN.
Pastikan tests/Unit bawaan lulus (php artisan test), tambah test fase ini, centang checklist Fase 1.
```

### 🟦 Prompt 2 — Data Master, Siswa & Login NISN
```
Kerjakan FASE 2. Halaman memakai x-ui.* dan trait WithDataTable/WithBulkActions/WithImport.
Import siswa dari templates/template_siswa.xlsx memakai App\Services\Auth\StudentCredential
(normalizeNisn, parseBirthDate, passwordFromBirthDate), pratinjau + laporan galat + mode lewati/perbarui.
Login siswa NISN + tanggal lahir (normalizePasswordInput), rate limit 5/menit per NISN+IP, pembekuan 15 menit setelah 10 gagal,
satu sesi aktif, gerbang browser (UserAgentInspector + config kitaq.shield.browsers) dengan halaman "buka di browser" untuk webview.
Tanggal lahir disamarkan di UI. Kartu login PDF tanpa tanggal lahir. Test lengkap, centang checklist.
```

### 🟦 Prompt 3 — Bank Soal
```
Kerjakan FASE 3 (sama dengan KITAQ offline): 6 tipe soal, stimulus bersama, gambar di soal/opsi, TipTap & KaTeX lokal,
import templates/template_soal.xlsx + ZIP gambar (cegah zip-slip, WebP maks 1600px, sanitasi HTML), opsi "isian angka".
Pratinjau soal di bingkai HP memakai komponen yang sama dengan ruang ujian. Test, centang checklist.
```

### 🟦 Prompt 4 — Ujian, Jadwal & Token
```
Kerjakan FASE 4. Pengaturan ujian JSON sesuai Spesifikasi 5.6 (termasuk profil keketatan longgar/standar/ketat dan
pengecualian per event, browser yang diizinkan, mode kanvas, batasan IP, perilaku offline). Jadwal per kelas + scheduler
buka/tutup otomatis. Token memakai App\Services\ExamTokenService (token_key acak per jadwal), tampil di halaman Sesi & Token
dan Layar Proyektor dengan hitung mundur. Test, centang checklist.
```

### 🟦 Prompt 5 — Ruang Ujian Siswa
```
Kerjakan FASE 5. Integrasikan resources/views/layouts/exam.blade.php, resources/js/exam/exam-app.js, answer-sync.js,
resources/js/keyboard dan resources/js/shield. Buat API soal satu per satu (tanpa kunci jawaban), peta soal, simpan jawaban
idempoten dengan rev, submit, dan halaman hasil. Halaman ujian TANPA Livewire, TANPA input/textarea/contenteditable,
TANPA alert/confirm. Tampilan mengikuti design/exam-mobile-prototype.html. Pasang PWA (manifest, public/sw.js, offline.html)
dan halaman /pasang (panduan Layar Utama iPhone/Android/Huawei). Test: respons API tidak pernah memuat kunci jawaban.
```

### 🟦 Prompt 6 — KITAQ Shield di Server
```
Kerjakan FASE 6 sesuai Spesifikasi Bagian 7: ShieldService, endpoint events & heartbeat, tabel shield_events,
attempt_instances, unlock_logs, monitor_alerts. Evaluasi event memakai ViolationPolicy dalam transaksi + lockForUpdate,
seq idempoten, waktu klien dijepit ke [started_at, now], satu sesi aktif (second_instance), nonce heartbeat bergulir,
job DetectHeartbeatGaps & AutoSubmitExpiredAttempts lewat scheduler, middleware EnsureExamLocation & EnsureBrowserAllowed.
Endpoint harus ringan (tanpa view, query ter-indeks). Test semua kriteria di Spesifikasi Bagian 13.
```

### 🟦 Prompt 7 — Monitoring Live
```
Kerjakan FASE 7: halaman monitoring sesuai design/admin-prototype.html, polling 4 detik dengan cache 2 detik dan payload delta,
kartu siswa (status, progres, pelanggaran, perangkat OS/browser/mode aplikasi, IP), feed pelanggaran dengan jenis baru,
panel token, aksi buka kunci (wajib alasan), reset login, tambah waktu, kumpulkan paksa, kirim pesan, linimasa perangkat,
alarm suara + notifikasi browser. Test, centang checklist.
```

### 🟦 Prompt 8 — Rekap, Laporan & Analitik Integritas
```
Kerjakan FASE 8: rekap nilai, ekspor Excel/PDF via queue (cron), analisis butir, koreksi uraian, berita acara,
rekap pelanggaran + grafik per jam, dan IntegrityAnalyzer (jawaban salah identik, kecepatan tidak wajar, perangkat dipakai
banyak NISN) dengan halaman tinjauan "perlu ditinjau" (bukan vonis otomatis). Test, centang checklist.
```

### 🟦 Prompt 9 — Siap Deploy
```
Kerjakan FASE 9: halaman Kesehatan Sistem, backup harian + retensi, pembersihan log lama, perintah kitaq:seed-load untuk uji beban,
rute install darurat bertoken (Bagian 6 DEPLOY), sesuaikan scripts/build-release.*, pastikan php artisan optimize aman,
lalu tulis daftar periksa rilis di docs/RILIS.md. Jalankan semua test.
```
