<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesi staf (admin/guru/pengawas):
 *  - akun nonaktif → keluar;
 *  - tidak aktif > KITAQ_ADMIN_IDLE_MINUTES (default 120) → keluar (KEAMANAN.md §4);
 *  - wajib ganti password (mis. Super Admin bawaan) → diarahkan ke halaman ganti password;
 *  - 2FA aktif tetapi belum diverifikasi di sesi ini → halaman tantangan 2FA.
 */
class EnsureStaffSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }

        $idle = (int) config('kitaq.admin_idle_minutes', 120) * 60;
        $last = (int) $request->session()->get('staff_last_activity', time());
        if (! $user->is_active || (time() - $last) > $idle) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', $user->is_active ? 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk lagi.' : 'Akun Anda dinonaktifkan.');
        }
        $request->session()->put('staff_last_activity', time());

        if ($user->hasTwoFactor() && ! $request->session()->get('two_factor_passed') && ! $request->routeIs('admin.2fa.*', 'admin.logout')) {
            return redirect()->route('admin.2fa.challenge');
        }

        if ($user->must_change_password && ! $request->routeIs('admin.profile', 'admin.profile.*', 'admin.logout', 'admin.2fa.*')) {
            return redirect()->route('admin.profile')->with('warning', 'Demi keamanan, ganti password Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
