<?php

namespace App\Http\Middleware;

use App\Models\ExamSchedule;
use App\Services\Shield\UserAgentInspector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang browser (Spesifikasi 5.1 & 7): webview aplikasi (WhatsApp, Instagram, …) atau browser
 * di luar daftar yang diizinkan → halaman "Buka di browser".
 * Daftar diambil dari pengaturan ujian bila rute punya {schedule}, selain itu dari config kitaq.shield.browsers.
 * UA bisa dipalsukan → ini lapisan tambahan, bukan satu-satunya pengaman.
 */
class EnsureBrowserAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('kitaq.load_test')) {
            return $next($request);
        }

        $info = UserAgentInspector::inspect((string) $request->userAgent());
        $allow = (array) config('kitaq.shield.browsers', ['chrome', 'safari', 'samsung', 'huawei']);

        $schedule = $request->route('schedule');
        if ($schedule instanceof ExamSchedule) {
            $allow = (array) $schedule->exam->setting('browsers', $allow);
        }

        // iPadOS modern menyamar sebagai Safari macOS; Chrome iOS ("CriOS") tetap chrome.
        if (UserAgentInspector::isAllowed($info, $allow)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Browser tidak diizinkan.', 'browser' => $info['browser']], 403);
        }

        return response()->view('student.open-in-browser', [
            'info' => $info,
            'allow' => $allow,
            'url' => $request->fullUrl(),
        ], 403);
    }
}
