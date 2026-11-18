<p align="center"><img src="branding/kitaq-online-logo.png" width="440" alt="KITAQ ONLINE"></p>

# KITAQ ONLINE — Starter Kit
**Ujian online anti-contek untuk HP & laptop** · oleh **CiptaKita.id** — *Solusi Digitalmu, dari kita!*

Aplikasi terpisah dari KITAQ (offline). Berjalan di **shared/cloud hosting**, siswa login dengan **NISN + tanggal lahir**, anti-contek **KITAQ Shield** + **Keyboard Virtual KITAQ** untuk Android, iOS/iPadOS, dan Huawei HarmonyOS.

## Isi starter kit
| Berkas/folder | Isi |
|---|---|
| `SPESIFIKASI_KITAQ_ONLINE.md` | **Dokumen induk** untuk AI agent: fitur, database, API, fase, kriteria selesai |
| `PROMPTS.md` | Prompt siap tempel per fase untuk Claude Code |
| `CLAUDE.md`, `AGENTS.md`, `.github/copilot-instructions.md` | Aturan wajib untuk agent |
| `docs/ANTI_CONTEK.md` | KITAQ Shield: lapisan, profil keketatan, matriks platform, checklist uji |
| `docs/DEPLOY_SHARED_HOSTING.md` | Langkah deploy ke hosting (SSL, PHP, database, cron) |
| `docs/KEAMANAN.md` | Keamanan & pelindungan data pribadi siswa |
| `docs/DESIGN_SYSTEM.md` | Panduan desain dashboard admin custom & layar siswa |
| `docs/UJI_BEBAN.md`, `tests/load/k6-exam.js` | Uji beban sebelum ujian massal |
| `docs/PANDUAN_*.md`, `docs/HASIL_UJI_PLATFORM.md` | Panduan siswa/pengawas, lembar hasil uji lapangan |
| `design/` | **Prototipe klik**: `admin-prototype.html`, `exam-mobile-prototype.html` (buka di browser) |
| `resources/js/shield/` | **Kode KITAQ Shield** (fokus, split screen, multi-tab, terjemahan, ekstensi AI, input, layar penuh, antrean event, heartbeat, mode kanvas) |
| `resources/js/keyboard/` | **Keyboard Virtual KITAQ** (keyboard HP tidak pernah muncul saat ujian) |
| `resources/js/exam/`, `resources/views/layouts/exam.blade.php`, `resources/css/` | Klien ruang ujian, sinkronisasi jawaban, tema |
| `app/Services/...`, `app/Http/Middleware/SecurityHeaders.php` | Aturan pelanggaran, login NISN, token ujian, deteksi browser, header keamanan |
| `tests/Unit/` | Test Pest (67 kasus) untuk kode di atas |
| `public/` | PWA (manifest, service worker, offline), `cek-perangkat.html`, ikon, logo |
| `config/kitaq.php`, `.env.example` | Konfigurasi default untuk shared hosting |
| `templates/` | Template Excel siswa (NISN + tanggal lahir), pengguna, soal + contoh ZIP gambar |
| `scripts/` | Skrip paket rilis untuk diunggah ke hosting |
| `branding/` | Logo KITAQ ONLINE, KITAQ, CiptaKita.id + panduan brand |

## Status implementasi
Fase 1–9 di `SPESIFIKASI_KITAQ_ONLINE.md` sudah dikerjakan (Laravel 12, PHP 8.2+, Livewire 3, Tailwind v4). `php artisan test` → **161 test lulus** (67 test bawaan starter kit + 94 test fitur). Yang tersisa butuh hosting & perangkat sungguhan: deploy, uji beban k6, dan uji lapangan per platform — ikuti `docs/RILIS.md`. Keputusan teknis tercatat di `docs/DECISIONS.md`.

## Menjalankan di laptop (XAMPP/Laragon)
```bash
composer install
npm ci && npm run build
cp .env.example .env            # lalu sesuaikan: APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8000, DB_*
php artisan key:generate
php artisan migrate --seed      # APP_ENV=local → ikut membuat data contoh (DemoSeeder)
php artisan serve
# opsional, terminal kedua: jalankan scheduler seperti cron
php artisan schedule:work
```
Akun contoh (hanya `APP_ENV=local`):

| Peran | Login | Password |
|---|---|---|
| Super Admin | `superadmin` (di `/admin/login`) | `KitaQ#2026` (wajib ganti saat login pertama) |
| Operator | `operator` | `Operator#2026kitaq` |
| Guru (Matematika) | `guru` | `Guru#2026kitaq` |
| Pengawas | `pengawas` | `Pengawas#2026kitaq` |
| Siswa | NISN `0081234501` s.d. `0081234530` (di `/`) | `20102008` |

Token ujian contoh tampil di **Admin → Sesi & Token**. Uji siswa dari browser HP (Chrome/Safari/Samsung Internet/Huawei Browser) atau Chrome desktop.

Perintah penting: `php artisan kitaq:tick` (buka/tutup jadwal, kumpul otomatis), `kitaq:backup`, `kitaq:prune`, `kitaq:seed-load 300` (uji beban).

## Mulai di VS Code
1. Pasang PHP 8.2+ (XAMPP/Laragon cukup), Composer, Node.js 20+, Git.
2. `composer create-project laravel/laravel:^12.0 kitaq-online`
3. Salin **seluruh isi** starter kit ini ke folder `kitaq-online` (timpa bila ditanya).
4. Buka folder di VS Code, buka Claude Code, tempel **Prompt 0** dari `PROMPTS.md`.
5. Lanjutkan prompt per fase. Setelah Fase 9, ikuti `docs/DEPLOY_SHARED_HOSTING.md`.

## Coba cepat tanpa Laravel
- Buka `design/admin-prototype.html` dan `design/exam-mobile-prototype.html` di Chrome.
- Di prototipe HP, buka soal isian untuk mencoba **Keyboard KITAQ** (kode yang sama dengan `resources/js/keyboard`).
- Buka `public/cek-perangkat.html` di HP untuk melihat pemeriksaan perangkat.

---
*KITAQ ONLINE v1.0 · © 2026 CiptaKita.id — Solusi Digitalmu, dari kita!*
