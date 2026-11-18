<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Satu sesi aktif per siswa (Spesifikasi 5.1): bila siswa login di perangkat lain,
 * sesi lama tidak lagi cocok dengan `students.current_session_id` → dikeluarkan.
 * Juga mewajibkan ganti password bila opsi sekolah aktif.
 */
class EnsureStudentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();
        if (! $student) {
            return $this->out($request, 'Silakan masuk terlebih dahulu.');
        }

        if (! $student->is_active) {
            return $this->logout($request, 'Akun dinonaktifkan. Hubungi operator sekolah.');
        }

        if (config('kitaq.student_login.single_session', true)
            && $student->current_session_id !== null
            && ! hash_equals((string) $student->current_session_id, $request->session()->getId())) {
            return $this->logout($request, 'Akunmu masuk dari perangkat lain, jadi sesi di perangkat ini ditutup.');
        }

        if ($student->must_change_password && ! $request->routeIs('student.password*', 'student.logout') && ! $request->is('api/*')) {
            return redirect()->route('student.password');
        }

        return $next($request);
    }

    private function logout(Request $request, string $msg): Response
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->out($request, $msg);
    }

    private function out(Request $request, string $msg): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $msg, 'state' => ['status' => 'session_expired']], 401);
        }

        return redirect()->route('student.login')->with('error', $msg);
    }
}
