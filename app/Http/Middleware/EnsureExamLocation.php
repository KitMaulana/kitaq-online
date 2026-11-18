<?php

namespace App\Http\Middleware;

use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi lokasi ujian ke IP publik sekolah (daftar IP/CIDR per jadwal, Spesifikasi 5.6).
 * Daftar kosong → boleh dari mana saja.
 */
class EnsureExamLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        $schedule = $request->route('schedule');
        $attempt = $request->route('attempt');
        if (! $schedule instanceof ExamSchedule && $attempt instanceof ExamAttempt) {
            $schedule = $attempt->schedule;
        }
        if (! $schedule instanceof ExamSchedule) {
            return $next($request);
        }

        $list = $schedule->effectiveIpAllowlist();
        if ($list === [] || self::allowed((string) $request->ip(), $list)) {
            return $next($request);
        }

        $msg = 'Ujian ini hanya bisa dikerjakan dari jaringan (WiFi) sekolah.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $msg, 'code' => 'location_blocked'], 403);
        }

        return response()->view('student.location-blocked', ['message' => $msg, 'ip' => $request->ip()], 403);
    }

    /** @param list<string> $list */
    public static function allowed(string $ip, array $list): bool
    {
        try {
            return IpUtils::checkIp($ip, array_values(array_filter(array_map('trim', $list))));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
