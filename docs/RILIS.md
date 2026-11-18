# Daftar Periksa Rilis — KITAQ ONLINE

> Untuk operator/teknisi sekolah yang memasang KITAQ ONLINE di shared/cloud hosting.
> Langkah teknis lengkap ada di `docs/DEPLOY_SHARED_HOSTING.md`. Uji beban: `docs/UJI_BEBAN.md`.

## A. Di laptop (sebelum unggah)
- [ ] `php artisan test` lulus (semua test, termasuk `tests/Unit` bawaan starter kit).
- [ ] `npm ci && npm run build` berhasil; folder `public/build/` ada.
- [ ] Jalankan skrip rilis: `powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1` (Windows) atau `bash scripts/build-release.sh`.
- [ ] Hasil `release/kitaq-online-<versi>.zip` **tidak** berisi `.env`, `node_modules/`, `tests/`, log, backup, atau ekspor.
- [ ] `KITAQ_VERSION` di `.env.example` sudah dinaikkan bila ini pembaruan.

## B. Panel hosting
- [ ] Subdomain (mis. `ujian.sekolah.sch.id`) + **SSL aktif** + "Paksa HTTPS".
- [ ] PHP **8.3/8.4** bila ada (minimal 8.2). Ekstensi: `pdo_mysql, mbstring, openssl, gd, zip, fileinfo, bcmath` (+ `intl` disarankan). **OPcache aktif**.
- [ ] Database MySQL/MariaDB (`utf8mb4`) + user dibuat.
- [ ] Document root diarahkan ke `kitaq-online/public` (atau Pilihan B di DEPLOY §3).

## C. Konfigurasi server
- [ ] `.env` dari `.env.example`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `DB_*`, `SESSION_SECURE_COOKIE=true`.
- [ ] `php artisan key:generate` (sekali saja — **jangan diganti** setelah ada data: token ujian & password backup diturunkan dari `APP_KEY`).
- [ ] `php artisan migrate --force` lalu `php artisan db:seed --class=SuperAdminSeeder --force`.
  - Tanpa SSH: isi `KITAQ_INSTALL_TOKEN` (≥ 24 karakter acak), buka `https://…/install?token=…` **sekali**, lalu **hapus token** dari `.env`.
- [ ] `php artisan storage:link` (boleh gagal di hosting yang melarang symlink — gambar soal disajikan lewat rute aplikasi).
- [ ] `php artisan optimize` (config, route, view, event cache). Ulangi setiap mengubah `.env`.
- [ ] Izin folder: `chmod -R 775 storage bootstrap/cache`.
- [ ] **Cron tiap menit**: `* * * * * cd /home/…/kitaq-online && php artisan schedule:run >> /dev/null 2>&1` (pakai path PHP yang benar).

## D. Setelah online
- [ ] Login `/admin/login` sebagai `superadmin` / `KitaQ#2026` → **ganti password** → aktifkan **2FA**.
- [ ] Menu **Pengaturan Sekolah**: nama, logo, kepala sekolah, **alamat situs** (untuk kartu login & proyektor).
- [ ] Menu **Kesehatan Sistem**: semua hijau, terutama **Cron < 3 menit lalu**, APP_DEBUG mati, HTTPS.
- [ ] `https://…/cek-perangkat` dibuka dari HP siswa: semua centang hijau.
- [ ] Buat data uji: 1 kelas, 3 siswa, bank soal 5 soal (semua tipe), ujian, jadwal → coba dari Android Chrome, Samsung Internet, iPhone (mode Layar Utama), laptop.
- [ ] Isi `docs/HASIL_UJI_PLATFORM.md` (checklist `docs/ANTI_CONTEK.md` §6).

## E. Uji beban (wajib sebelum ujian massal pertama)
- [ ] Di luar jam ujian: set `KITAQ_LOAD_TEST=true`, `php artisan config:cache`, lalu `php artisan kitaq:seed-load 300`.
- [ ] Dari laptop: `k6 run -e BASE=https://… -e VUS=300 -e SCHEDULE=<id> tests/load/k6-exam.js`.
- [ ] Target: gagal < 1%, p95 heartbeat & jawaban < 800 ms, p95 soal < 1.200 ms, CPU/entry process tidak mentok.
- [ ] Bersihkan: `php artisan kitaq:seed-load --hapus`, kembalikan `KITAQ_LOAD_TEST=false`, `php artisan config:cache`.
- [ ] Bila gagal: naikkan `KITAQ_HEARTBEAT_SEC` (20–25), bagi sesi bergiliran, naik paket hosting, atau pindah VPS.

## F. Hari ujian
- [ ] Kesehatan Sistem hijau; antrean kosong; mode uji beban **mati**.
- [ ] Pengawas sudah ditugaskan ke jadwal; tahu cara membuka **Monitoring** & **Layar Proyektor**.
- [ ] Siswa iPhone sudah memasang ke Layar Utama (`/pasang`).
- [ ] **Jangan update aplikasi saat ujian berlangsung.**

## G. Rutin
- [ ] Backup otomatis tiap 01.30 (retensi 14 hari) — **unduh** dari menu Backup ke penyimpanan sekolah tiap pekan.
- [ ] `composer audit` & pembaruan dependensi tiap semester.
- [ ] Rencanakan upgrade ke **Laravel 13 (PHP 8.3+) sebelum 24 Feb 2027** (akhir perbaikan keamanan Laravel 12).

## Pembaruan versi
1. Build rilis baru di laptop. 2. `php artisan down --secret=kitaq-admin`. 3. Unggah & timpa (kecuali `.env` dan `storage/`).
4. `php artisan migrate --force && php artisan optimize`. 5. `php artisan up`.
