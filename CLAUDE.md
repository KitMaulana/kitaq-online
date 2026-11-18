# Instruksi Proyek — KITAQ ONLINE (CiptaKita.id)

Baca `SPESIFIKASI_KITAQ_ONLINE.md` (dokumen induk) sebelum menulis kode. Kerjakan per fase, ikuti `PROMPTS.md`.

## Aturan wajib
- **Laravel 12, `"php": "^8.2"`.** Dilarang memakai sintaks/fitur PHP 8.3+ dan jangan menaikkan syarat PHP.
- **Target shared/cloud hosting**: tanpa WebSocket, Redis, Supervisor, Node di server. Realtime = polling HTTP; proses latar = cron → `schedule:run`; session/cache/queue = `database`.
- **Dilarang memasang Filament** atau paket panel admin jadi. Dashboard admin custom (Blade + Livewire 3 + Alpine + Tailwind v4) mengikuti `docs/DESIGN_SYSTEM.md` dan `design/admin-prototype.html`.
- **Halaman ujian siswa**: Blade + JavaScript murni. **Tanpa Livewire, tanpa `<input>`/`<textarea>`/contenteditable, tanpa skrip pihak ketiga, tanpa `alert()`/`confirm()`.** Jawaban isian lewat Keyboard KITAQ.
- **Pakai & adaptasi kode referensi** di `resources/js/shield`, `resources/js/keyboard`, `resources/js/exam`, `app/Services/**`, `app/Http/Middleware/SecurityHeaders.php`. Jangan menulis ulang dari nol; perbaiki di tempat bila perlu dan catat di `docs/DECISIONS.md`.
- **Klien mendeteksi, server memutuskan.** Semua keputusan peringatan/kunci lewat `ViolationPolicy`. Kunci jawaban tidak pernah dikirim ke browser.
- Login siswa: **NISN + tanggal lahir (DDMMYYYY)** via `StudentCredential` + token ujian via `ExamTokenService`.
- Tanpa CDN. Semua aset dibundel Vite dan dibangun di laptop.
- UI Bahasa Indonesia; kode Bahasa Inggris. Branding: KITAQ ONLINE × CiptaKita.id (`branding/BRAND_GUIDE.md`).
- Data pribadi siswa: ikuti `docs/KEAMANAN.md`.

## Sebelum menyelesaikan tugas
- `php artisan test` harus lulus (termasuk `tests/Unit` bawaan starter kit).
- Centang checklist fase di spesifikasi; catat keputusan di `docs/DECISIONS.md`.
