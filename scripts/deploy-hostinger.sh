#!/usr/bin/env bash
# Pembaruan KITAQ ONLINE di shared hosting setelah `git pull` (jalankan lewat SSH di folder aplikasi).
#   cd ~/domains/<domain>/public_html/kitaq && bash scripts/deploy-hostinger.sh
# Tidak menyimpan kredensial apa pun. .env harus sudah ada (lihat docs/DEPLOY_SHARED_HOSTING.md §10).
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "ERROR: .env belum ada. Salin .env.example → .env lalu isi DB_* dan APP_URL."; exit 1; }

PHP_BIN="${PHP_BIN:-php}"
echo "==> PHP: $($PHP_BIN -v | head -1)"
$PHP_BIN -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "ERROR: butuh PHP 8.2+. Ganti versi PHP di hPanel."; exit 1; }

[ -f public/build/manifest.json ] || { echo "ERROR: public/build tidak ada. Build di laptop (npm run build) lalu commit/unggah."; exit 1; }

echo "==> composer install (produksi)"
composer install --no-dev --optimize-autoloader --no-interaction

grep -q '^APP_KEY=base64' .env || { echo "==> membuat APP_KEY (sekali saja)"; $PHP_BIN artisan key:generate --force; }

echo "==> migrasi database"
$PHP_BIN artisan migrate --force

echo "==> cache konfigurasi, rute, view"
$PHP_BIN artisan optimize

chmod -R 775 storage bootstrap/cache 2>/dev/null || true
echo "==> selesai. Pastikan cron schedule:run aktif (hPanel → Cron Jobs) dan buka halaman Admin → Kesehatan Sistem."
