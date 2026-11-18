# Membuat paket rilis KITAQ ONLINE untuk shared hosting (Windows PowerShell)
# Jalankan: powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1
# Hasil: release\kitaq-online-<versi>.zip berisi kode + vendor (produksi) + public\build, tanpa node_modules, tests, .env.
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")
$version = ((Get-Content .env.example | Where-Object { $_ -match "^KITAQ_VERSION=" }) -split "=")[1]
$out = "release\kitaq-online-$version"

Write-Host "==> uji otomatis"
php artisan test
if ($LASTEXITCODE -ne 0) { throw "Test gagal - rilis dibatalkan." }

Write-Host "==> npm build (aset dibangun di laptop, tanpa Node di server)"
npm ci
npm run build

Write-Host "==> composer install (produksi, PHP 8.2 kompatibel)"
composer install --no-dev --optimize-autoloader --no-interaction

if (Test-Path $out) { Remove-Item $out -Recurse -Force }
if (Test-Path "$out.zip") { Remove-Item "$out.zip" -Force }
New-Item -ItemType Directory -Path $out | Out-Null
$items = "app","bootstrap","config","database","lang","public","resources","routes","storage","templates","vendor","artisan","composer.json","composer.lock",".env.example","docs"
foreach ($i in $items) { if (Test-Path $i) { Copy-Item $i -Destination $out -Recurse } }

# Bersihkan data lokal/sementara (jangan pernah mengunggah .env, log, sesi, backup, ekspor, unggahan).
$clean = "$out\storage\logs\*.log","$out\storage\framework\sessions\*","$out\storage\framework\views\*","$out\storage\framework\cache\data\*",
         "$out\storage\app\private\*","$out\storage\app\private-import","$out\storage\app\livewire-tmp","$out\storage\app\install.lock",
         "$out\public\hot","$out\public\storage","$out\bootstrap\cache\*.php","$out\database\database.sqlite"
foreach ($c in $clean) { Remove-Item $c -Recurse -Force -ErrorAction SilentlyContinue }

Compress-Archive -Path $out -DestinationPath "$out.zip"
Write-Host "==> selesai: $out.zip"
Write-Host "    Kembalikan dependensi dev di laptop: composer install"
Write-Host "    Langkah di server: docs/RILIS.md dan docs/DEPLOY_SHARED_HOSTING.md"
