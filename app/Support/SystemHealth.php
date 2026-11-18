<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Data panel Kesehatan Sistem & chip "siswa online" di topbar. */
final class SystemHealth
{
    public const CRON_KEY = 'kitaq:cron_last_run';
    public const API_KEY = 'kitaq:api_timing';

    public static function markCron(): void
    {
        Cache::forever(self::CRON_KEY, time());
    }

    public static function cronLastRun(): ?int
    {
        $v = Cache::get(self::CRON_KEY);

        return $v ? (int) $v : null;
    }

    /** Cron dianggap sehat bila jalan < 3 menit lalu. */
    public static function cronHealthy(): bool
    {
        $t = self::cronLastRun();

        return $t !== null && (time() - $t) < 180;
    }

    public static function recordApiTiming(float $ms): void
    {
        try {
            $bucket = Cache::get(self::API_KEY, ['sum' => 0.0, 'n' => 0, 'since' => time()]);
            if (time() - (int) $bucket['since'] > 300) {   // jendela 5 menit
                $bucket = ['sum' => 0.0, 'n' => 0, 'since' => time()];
            }
            $bucket['sum'] += $ms;
            $bucket['n']++;
            Cache::put(self::API_KEY, $bucket, 900);
        } catch (\Throwable $e) {
            // pengukuran tidak boleh mengganggu ujian
        }
    }

    public static function avgApiMs(): ?float
    {
        $b = Cache::get(self::API_KEY);
        if (! $b || ! $b['n']) {
            return null;
        }

        return round($b['sum'] / $b['n'], 1);
    }

    public static function queueSize(): int
    {
        try {
            return (int) DB::table('jobs')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function failedJobs(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function onlineStudents(): int
    {
        return Cache::remember('kitaq:online_students', 10, function () {
            $gap = (int) config('kitaq.shield.heartbeat_gap_sec', 60);

            return (int) DB::table('exam_attempts')
                ->whereIn('status', ['in_progress', 'warned', 'locked'])
                ->where('last_heartbeat_at', '>=', now()->subSeconds($gap))
                ->count();
        });
    }

    /** Ukuran database dalam byte (MySQL/MariaDB; SQLite = ukuran berkas). */
    public static function databaseSize(): ?int
    {
        try {
            $driver = DB::connection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne('SELECT SUM(data_length + index_length) AS s FROM information_schema.tables WHERE table_schema = ?', [DB::connection()->getDatabaseName()]);

                return $row ? (int) $row->s : null;
            }
            if ($driver === 'sqlite') {
                $path = DB::connection()->getDatabaseName();

                return is_file($path) ? (int) filesize($path) : null;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Adaptive heartbeat (Spesifikasi 9.3): naikkan interval bila respons API melambat.
     */
    public static function heartbeatInterval(): int
    {
        $base = max(5, (int) config('kitaq.shield.heartbeat_sec', 15));
        $avg = self::avgApiMs();
        if ($avg === null) {
            return $base;
        }
        if ($avg > 1500) {
            return max($base, 30);
        }
        if ($avg > 800) {
            return max($base, 25);
        }

        return $base;
    }

    public static function humanBytes(?int $b): string
    {
        if ($b === null) {
            return '-';
        }
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = (float) $b;
        while ($v >= 1024 && $i < 3) {
            $v /= 1024;
            $i++;
        }

        return fmt_num($v, $i ? 1 : 0) . ' ' . $u[$i];
    }
}
