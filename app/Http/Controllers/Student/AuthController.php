<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExamAttempt;
use App\Models\MonitorAlert;
use App\Models\Setting;
use App\Models\Student;
use App\Services\Auth\StudentCredential;
use App\Services\Shield\ShieldService;
use App\Services\Shield\UserAgentInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login siswa: NISN + tanggal lahir (DDMMYYYY) — Spesifikasi 5.1.
 *  - format password dirapikan (StudentCredential::normalizePasswordInput);
 *  - rate limit 5/menit per NISN+IP; 10 gagal berturut → dibekukan 15 menit + log;
 *  - satu sesi aktif: login baru menutup sesi lama, KECUALI sedang ujian → ditolak + notifikasi pengawas.
 */
class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        return view('student.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'nisn' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:60'],
        ], [], ['password' => 'tanggal lahir']);

        $cfg = config('kitaq.student_login');
        $nisn = StudentCredential::normalizeNisn((string) $request->input('nisn'));
        if ($nisn === null) {
            throw ValidationException::withMessages(['nisn' => 'NISN harus 10 digit angka.']);
        }

        $key = 'student-login:' . $nisn . '|' . $request->ip();
        $perMinute = (int) ($cfg['max_attempts_per_minute'] ?? 5);
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ValidationException::withMessages(['nisn' => 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.']);
        }

        $student = Student::query()->where('nisn', $nisn)->first();
        if ($student && $student->isFrozen()) {
            throw ValidationException::withMessages(['nisn' => 'Akun dibekukan sementara sampai ' . $student->locked_until->format('H:i') . ' karena terlalu banyak salah. Hubungi pengawas/operator.']);
        }

        $password = StudentCredential::normalizePasswordInput((string) $request->input('password'));
        if (! $student || ! Hash::check($password, $student->password)) {
            RateLimiter::hit($key, 60);
            $left = max(0, $perMinute - RateLimiter::attempts($key));
            if ($student) {
                $student->increment('failed_logins');
                if ($student->failed_logins >= (int) ($cfg['lockout_after_failures'] ?? 10)) {
                    $student->forceFill(['locked_until' => now()->addMinutes((int) ($cfg['lockout_minutes'] ?? 15)), 'failed_logins' => 0])->save();
                    ActivityLog::record('student_frozen', 'Akun dibekukan karena ' . ($cfg['lockout_after_failures'] ?? 10) . ' kali gagal login', $student, ['ip' => $request->ip()], $student->id);

                    throw ValidationException::withMessages(['nisn' => 'Akun dibekukan ' . ($cfg['lockout_minutes'] ?? 15) . ' menit karena terlalu banyak salah. Hubungi pengawas/operator.']);
                }
                $left = min($left, (int) ($cfg['lockout_after_failures'] ?? 10) - $student->failed_logins);
            }

            throw ValidationException::withMessages(['nisn' => 'NISN atau tanggal lahir salah. Sisa percobaan: ' . $left . '.']);
        }

        if (! $student->is_active) {
            throw ValidationException::withMessages(['nisn' => 'Akun dinonaktifkan. Hubungi operator sekolah.']);
        }

        // Satu sesi aktif: sedang ujian di perangkat lain → tolak & beri tahu pengawas.
        $active = ExamAttempt::query()->where('student_id', $student->id)->whereIn('status', ExamAttempt::ACTIVE_STATUSES)
            ->whereNotNull('active_instance_id')->latest('id')->first();
        if ($active && $student->current_session_id && $this->sessionAlive($student->current_session_id)) {
            RateLimiter::hit($key, 60);
            app(ShieldService::class)->recordServerEvent($active, 'second_instance', [
                'source' => 'login', 'ip' => $request->ip(), 'device' => UserAgentInspector::inspect((string) $request->userAgent()),
            ], 'log');
            MonitorAlert::query()->create([
                'exam_schedule_id' => $active->exam_schedule_id, 'exam_attempt_id' => $active->id, 'kind' => 'second_instance',
                'message' => "{$student->name} ({$student->nisn}) mencoba login dari perangkat lain saat ujian berlangsung.",
            ]);

            throw ValidationException::withMessages(['nisn' => 'Akunmu sedang dipakai mengerjakan ujian di perangkat lain. Minta pengawas menekan "Reset Login" bila kamu berganti HP.']);
        }

        RateLimiter::clear($key);
        $oldSession = $student->current_session_id;
        Auth::guard('student')->login($student);
        $request->session()->regenerate();

        $mustChange = $student->must_change_password
            || ((bool) Setting::get('force_change_password', $cfg['force_change_password'] ?? false) && $student->last_login_at === null);
        $student->forceFill([
            'current_session_id' => $request->session()->getId(),
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'must_change_password' => $mustChange,
        ])->save();
        if ($oldSession && $oldSession !== $request->session()->getId()) {
            $this->killSession($oldSession);
        }
        ActivityLog::record('student_login', 'Login siswa', $student, [
            'device' => UserAgentInspector::inspect((string) $request->userAgent()),
        ], $student->id);

        return redirect()->intended(route('student.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $student = Auth::guard('student')->user();
        if ($student && $student->current_session_id === $request->session()->getId()) {
            $student->forceFill(['current_session_id' => null])->save();
        }
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }

    private function sessionAlive(string $id): bool
    {
        if (config('session.driver') !== 'database') {
            return true;
        }
        $lifetime = (int) config('session.lifetime', 240) * 60;

        return DB::table(config('session.table', 'sessions'))->where('id', $id)->where('last_activity', '>=', time() - $lifetime)->exists();
    }

    private function killSession(string $id): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('id', $id)->delete();
        }
    }
}
