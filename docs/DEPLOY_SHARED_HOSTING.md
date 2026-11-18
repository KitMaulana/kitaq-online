# Deploy KITAQ ONLINE ke Shared/Cloud Hosting

> Contoh menu memakai istilah umum panel hosting (hPanel, cPanel, dsb). Nama menu di hosting Anda bisa berbeda.
> Prinsip: **build di laptop, unggah hasil, jalankan perintah artisan lewat SSH, pasang cron.**

---

## 1. Siapkan di panel hosting
1. **Domain/subdomain**: buat `ujian.sekolahanda.sch.id` (atau domain lain).
2. **SSL**: aktifkan sertifikat SSL gratis untuk subdomain tersebut, lalu aktifkan "Paksa HTTPS".
3. **Versi PHP**: pilih **8.3 atau 8.4** bila tersedia (minimal 8.2). Aktifkan ekstensi: `pdo_mysql, mbstring, openssl, intl, gd, zip, fileinfo, bcmath`, dan **OPcache**.
4. **Database**: buat database MySQL + user + password. Catat nama database, user, password, host.
5. **SSH**: aktifkan akses SSH (sangat disarankan). Tanpa SSH, migrasi harus lewat rute khusus sekali pakai (lihat Bagian 6).

## 2. Siapkan paket rilis di laptop
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# atau cukup jalankan:
bash scripts/build-release.sh      # Linux/macOS/Git Bash
powershell -File scripts/build-release.ps1   # Windows
```
Hasil: `release/kitaq-online-<versi>.zip` berisi kode + `vendor/` + `public/build/`, tanpa `node_modules`, `.env`, `tests/`.

## 3. Unggah
Struktur yang disarankan (kode di luar folder publik):
```
/home/u123456/
├── kitaq-online/          ← isi ZIP rilis (app, bootstrap, config, vendor, public, ...)
└── domains/ujian.sekolahanda.sch.id/public_html/   ← document root subdomain
```
**Pilihan A (disarankan):** arahkan *document root* subdomain ke `/home/u123456/kitaq-online/public` dari panel (menu Subdomain/Domain → Document root).

**Pilihan B (bila document root tidak bisa diubah):** salin isi `kitaq-online/public/` ke `public_html/`, lalu ubah `public_html/index.php`:
```php
require __DIR__.'/../../../kitaq-online/vendor/autoload.php';
$app = require_once __DIR__.'/../../../kitaq-online/bootstrap/app.php';
```
(sesuaikan jumlah `../` dengan struktur folder Anda), dan tambahkan di `kitaq-online/bootstrap/app.php` atau `AppServiceProvider`:
```php
$this->app->usePublicPath(base_path('../domains/ujian.sekolahanda.sch.id/public_html'));
```

## 4. Konfigurasi
```bash
cd ~/kitaq-online
cp .env.example .env          # lalu isi DB_*, APP_URL, MAIL_*
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan storage:link       # bila hosting melarang symlink, aktifkan rute penyaji file (FILESYSTEM_SERVE=true)
php artisan config:cache && php artisan route:cache && php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

## 5. Cron (wajib)
Tambahkan di menu Cron Job, **setiap menit**:
```
* * * * * cd /home/u123456/kitaq-online && php artisan schedule:run >> /dev/null 2>&1
```
Scheduler KITAQ menjalankan: buka/tutup jadwal otomatis, kumpul otomatis saat waktu habis, deteksi heartbeat hilang, `queue:work --stop-when-empty --max-time=50` (ekspor Excel/PDF, analitik integritas), backup harian, pembersihan log lama.
Gunakan path PHP yang benar bila `php` di cron berbeda versi (mis. `/opt/alt/php83/usr/bin/php` atau `/usr/bin/php8.3` — lihat dokumentasi hosting).

## 6. Tanpa SSH (darurat)
Aktifkan sementara `KITAQ_INSTALL_TOKEN=<acak panjang>` di `.env`, buka `https://ujian.../install?token=...` (rute yang dibuat agent: menjalankan migrate + seed sekali, lalu menonaktifkan dirinya). Hapus token setelah selesai.

## 7. Uji setelah deploy
- [ ] `https://ujian.../` tampil dengan gembok HTTPS.
- [ ] `https://ujian.../cek-perangkat` dari HP siswa: semua centang hijau.
- [ ] Login Super Admin, ganti password.
- [ ] Halaman `Admin → Kesehatan`: cron terakhir jalan < 2 menit lalu.
- [ ] Buat ujian uji 5 soal, coba dari 3 HP berbeda.
- [ ] **Uji beban** dari laptop: `k6 run -e BASE=https://ujian... -e VUS=300 tests/load/k6-exam.js` (lihat `docs/UJI_BEBAN.md`).

## 8. Update versi
1. Build rilis baru di laptop. 2. Aktifkan mode perawatan: `php artisan down --secret=kitaq-admin`. 3. Unggah & timpa (kecuali `.env` dan `storage/`). 4. `php artisan migrate --force && php artisan optimize`. 5. `php artisan up`.
**Jangan update saat ada ujian berlangsung.**

## 9. Masalah umum
| Gejala | Solusi |
|---|---|
| Error 500 setelah unggah | Periksa `storage/logs/laravel.log`; izin folder `storage` & `bootstrap/cache`; `APP_KEY` terisi |
| CSS/JS tidak muncul | `public/build` belum diunggah, atau `APP_URL` salah (http vs https) |
| Ujian tidak otomatis mulai/tutup | Cron belum terpasang atau path PHP cron salah |
| "419 Page Expired" | Domain di `APP_URL` beda dengan yang dibuka; `SESSION_SECURE_COOKIE=true` tapi dibuka via http |
| Lambat saat banyak siswa | Uji beban, naikkan paket, bagi sesi, naikkan `KITAQ_HEARTBEAT_SEC` ke 20–25 |
| Gambar soal tidak tampil | `storage:link` gagal → aktifkan rute penyaji file |

---

## 10. Hostinger: pasang lewat Git + `.htaccess` root

Cocok bila **document root tidak bisa diarahkan ke `public/`** (mis. subdomain `kitaq.domain.id` → folder `domains/domain.id/public_html/kitaq`). Kode ditarik langsung ke folder itu, dan `.htaccess` di root mengarahkan semua permintaan ke `public/` serta memblokir `.env`, `app/`, `vendor/`, `storage/`, dll.

1. **Subdomain & SSL**: hPanel → Domain → Subdomain (folder `public_html/kitaq`), aktifkan SSL + "Force HTTPS". Versi PHP **8.2+** (Advanced → PHP Configuration). Folder harus **kosong** sebelum `git clone`.
2. **Database**: hPanel → Databases → MySQL. Catat nama DB, user, dan password.
3. **Tarik kode** (SSH: Advanced → SSH Access):
   ```bash
   cd ~/domains/<domain>/public_html/kitaq
   git clone https://github.com/<akun>/kitaq-online.git .
   ```
   Alternatif tanpa terminal: hPanel → Advanced → **Git** → isi URL repositori, branch `main`, folder `kitaq` → Create → Deploy.
4. **`.env`**: `cp .env.example .env`, lalu isi `APP_URL=https://<subdomain>`, `APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST=localhost`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_SECURE_COOKIE=true`.
5. **Pasang**: `bash scripts/deploy-hostinger.sh` (composer install produksi, `key:generate`, `migrate --force`, `optimize`).
   Pertama kali saja: `php artisan db:seed --class=SuperAdminSeeder --force`.
6. **Cron** (hPanel → Advanced → Cron Jobs, tiap menit):
   `/usr/bin/php /home/<user>/domains/<domain>/public_html/kitaq/artisan schedule:run >> /dev/null 2>&1`
7. **Uji**: `https://<subdomain>/` tampil, `/.env` dan `/composer.json` harus **404/403**, login `/admin/login`, lalu ganti password Super Admin.

**Memperbarui**: `git pull && bash scripts/deploy-hostinger.sh` (jangan saat ada ujian berlangsung).

Catatan: `public/build/` (hasil `npm run build`) ikut di repositori karena tidak ada Node di hosting. Jalankan `npm run build` di laptop dan commit sebelum push bila JS/CSS berubah. Bila `composer` tidak tersedia di paket hosting Anda, unggah ZIP dari `scripts/build-release.ps1` (sudah berisi `vendor/`).
