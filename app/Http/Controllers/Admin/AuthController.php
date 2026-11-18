<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/** Login staf (username/email + password) + tantangan 2FA TOTP opsional. */
class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
        ], [], ['login' => 'username/email']);

        $key = 'staff-login:' . mb_strtolower($data['login']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $user = User::query()->where($field, $data['login'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => trans('auth.failed')]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.']);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->put('staff_last_activity', time());
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        ActivityLog::record('staff_login', 'Masuk ke dashboard admin', $user);

        if ($user->hasTwoFactor()) {
            return redirect()->route('admin.2fa.challenge');
        }
        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Anda sudah keluar.');
    }

    public function challenge(): View|RedirectResponse
    {
        if (session('two_factor_passed')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user('web');
        $key = '2fa:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! (new Google2FA())->verifyKey((string) $user->two_factor_secret, (string) $request->input('code'), 1)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'Kode 2FA salah.']);
        }
        RateLimiter::clear($key);
        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }
}
