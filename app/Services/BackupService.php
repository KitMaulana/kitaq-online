<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Backup database harian (Spesifikasi 5.13, KEAMANAN.md §5):
 *  - mysqldump bila tersedia & diizinkan hosting, selain itu ekspor Laravel per tabel (INSERT SQL);
 *  - dikemas ZIP terenkripsi AES-256 dengan password turunan APP_KEY;
 *  - disimpan di storage/app/backups, retensi KITAQ_BACKUP_RETENTION_DAYS (default 14 hari).
 */
class BackupService
{
    public const DIR = 'backups';

    /** Password ZIP (turunan APP_KEY) — tampilkan ke Super Admin agar bisa membuka arsip. */
    public static function password(): string
    {
        return substr(hash_hmac('sha256', 'kitaq-backup', (string) config('app.key')), 0, 24);
    }

    public function run(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        $stamp = now()->format('Ymd-His');
        $base = $disk->path(self::DIR);
        $sqlPath = $base . "/db-{$stamp}.sql";

        if (! $this->mysqldump($sqlPath)) {
            $this->phpDump($sqlPath);
        }

        $zipPath = $base . "/kitaq-backup-{$stamp}.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip backup.');
        }
        $zip->addFile($sqlPath, basename($sqlPath));
        if (method_exists($zip, 'setEncryptionName')) {
            $zip->setEncryptionName(basename($sqlPath), ZipArchive::EM_AES_256, self::password());
        }
        $zip->close();
        @unlink($sqlPath);

        $this->prune();

        return self::DIR . '/' . basename($zipPath);
    }

    /** @return list<array{name:string,size:int,time:int}> */
    public function list(): array
    {
        $disk = Storage::disk('local');
        $out = [];
        foreach ($disk->files(self::DIR) as $f) {
            if (str_ends_with($f, '.zip')) {
                $out[] = ['name' => basename($f), 'size' => $disk->size($f), 'time' => $disk->lastModified($f)];
            }
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public function prune(): int
    {
        $days = max(1, (int) config('kitaq.backup_retention_days', 14));
        $n = 0;
        foreach ($this->list() as $f) {
            if ($f['time'] < now()->subDays($days)->getTimestamp()) {
                Storage::disk('local')->delete(self::DIR . '/' . $f['name']);
                $n++;
            }
        }

        return $n;
    }

    private function mysqldump(string $target): bool
    {
        $c = config('database.connections.' . config('database.default'));
        if (! in_array($c['driver'] ?? '', ['mysql', 'mariadb'], true) || ! function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $disabled, true)) {
            return false;
        }
        $cmd = [
            'mysqldump', '--single-transaction', '--quick', '--skip-lock-tables', '--default-character-set=utf8mb4',
            '-h', (string) $c['host'], '-P', (string) $c['port'], '-u', (string) $c['username'], (string) $c['database'],
        ];
        $env = array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) $c['password']]);
        try {
            $proc = @proc_open($cmd, [1 => ['file', $target, 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
            if (! is_resource($proc)) {
                return false;
            }
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $code = proc_close($proc);

            return $code === 0 && is_file($target) && filesize($target) > 100;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Ekspor per tabel lewat Laravel (fallback bila mysqldump tidak tersedia di hosting). */
    private function phpDump(string $target): void
    {
        $fh = fopen($target, 'w');
        fwrite($fh, "-- KITAQ ONLINE backup " . now()->toDateTimeString() . "\nSET FOREIGN_KEY_CHECKS=0;\n");
        $driver = DB::connection()->getDriverName();
        $tables = match ($driver) {
            'sqlite' => array_map(fn ($r) => $r->name, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")),
            default => array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES')),
        };
        $pdo = DB::connection()->getPdo();
        foreach ($tables as $t) {
            if (in_array($t, ['sessions', 'cache', 'cache_locks', 'jobs'], true)) {
                continue; // data sementara
            }
            fwrite($fh, "\n-- Tabel {$t}\nDELETE FROM `{$t}`;\n");
            DB::table($t)->orderByRaw('1')->chunk(500, function ($rows) use ($fh, $t, $pdo) {
                foreach ($rows as $row) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                    fwrite($fh, "INSERT INTO `{$t}` (`" . implode('`,`', array_keys((array) $row)) . '`) VALUES (' . implode(',', $vals) . ");\n");
                }
            });
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }
}
