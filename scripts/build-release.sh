#!/usr/bin/env bash
# Membuat paket rilis KITAQ ONLINE untuk diunggah ke shared hosting.
# Hasil: release/kitaq-online-<versi>.zip (kode + vendor produksi + public/build; tanpa node_modules, tests, .env)
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(grep -E '^KITAQ_VERSION=' .env.example | cut -d= -f2)
OUT="release/kitaq-online-${VERSION}"

echo "==> uji otomatis"
php artisan test

echo "==> npm build (aset dibangun di laptop, tanpa Node di server)"
npm ci
npm run build

echo "==> composer install (produksi, PHP 8.2 kompatibel)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> menyalin berkas"
rm -rf "$OUT" "$OUT.zip"; mkdir -p "$OUT"
for d in app bootstrap config database lang public resources routes storage templates vendor artisan composer.json composer.lock .env.example docs; do
  [ -e "$d" ] && cp -R "$d" "$OUT/"
done
# Jangan pernah mengunggah .env, log, sesi, backup, ekspor, unggahan sementara.
rm -rf "$OUT/storage/logs/"*.log "$OUT/storage/framework/cache/data/"* "$OUT/storage/framework/sessions/"* "$OUT/storage/framework/views/"* \
       "$OUT/storage/app/private/"* "$OUT/storage/app/private-import" "$OUT/storage/app/livewire-tmp" "$OUT/storage/app/install.lock" \
       "$OUT/public/hot" "$OUT/public/storage" "$OUT/bootstrap/cache/"*.php "$OUT/database/database.sqlite" 2>/dev/null || true
(cd release && zip -qr "kitaq-online-${VERSION}.zip" "kitaq-online-${VERSION}")
echo "==> selesai: $OUT.zip"
echo "    Kembalikan dependensi dev di laptop: composer install"
echo "    Langkah di server: docs/RILIS.md dan docs/DEPLOY_SHARED_HOSTING.md"
