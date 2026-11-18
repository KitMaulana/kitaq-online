<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Services\AttemptService;
use App\Services\Shield\ShieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Alur siswa: /ujian/{schedule} (token) → POST mulai → /ujian/{schedule}/kerjakan (konfirmasi + ruang ujian) → hasil.
 * Token diminta saat MEMULAI dan MELANJUTKAN dari sesi/perangkat baru (Spesifikasi 5.1).
 */
class ExamController extends Controller
{
    public const ACCESS_KEY = 'kq_access_';

    public function __construct(private AttemptService $attempts)
    {
    }

    public function show(Request $request, ExamSchedule $schedule): View|RedirectResponse
    {
        $student = $request->user('student');
        abort_unless($schedule->isAccessibleBy($student), 404);
        $schedule->load('exam.subject');
        $attempt = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->where('student_id', $student->id)->first();

        if ($attempt && $attempt->isDone()) {
            return redirect()->route('student.exam.result', $schedule);
        }
        if (! $schedule->isOpenNow()) {
            return redirect()->route('student.dashboard')->with('error', $schedule->status === 'paused' ? 'Ujian sedang dijeda pengawas.' : 'Ujian belum dibuka atau sudah ditutup.');
        }
        if ($attempt && $attempt->started_at && $request->session()->get(self::ACCESS_KEY . $attempt->id)) {
            return redirect()->route('student.exam.room', $schedule);
        }

        return view('student.token', [
            'schedule' => $schedule,
            'exam' => $schedule->exam,
            'student' => $student,
            'attempt' => $attempt,
            'tokenRequired' => $this->tokenRequired($schedule),
            'tokenLength' => (int) config('kitaq.exam_token.length', 6),
        ]);
    }

    public function start(Request $request, ExamSchedule $schedule): RedirectResponse
    {
        $student = $request->user('student');
        abort_unless($schedule->isAccessibleBy($student), 404);
        if (! $schedule->isOpenNow()) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian belum dibuka atau sudah ditutup.');
        }
        $existing = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->where('student_id', $student->id)->first();
        if ($existing && $existing->isDone()) {
            return redirect()->route('student.exam.result', $schedule);
        }

        if ($this->tokenRequired($schedule)) {
            $key = 'exam-token:' . $student->id;
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return back()->withErrors(['token' => 'Terlalu banyak token salah. Tunggu ' . RateLimiter::availableIn($key) . ' detik.']);
            }
            if (! $schedule->verifyToken((string) $request->input('token', ''))) {
                RateLimiter::hit($key, 60);

                return back()->withErrors(['token' => 'Token salah atau sudah berganti. Lihat token terbaru di layar pengawas.']);
            }
            RateLimiter::clear($key);
        }

        $attempt = $this->attempts->start($schedule, $student, $request->ip(), (string) $request->userAgent());
        $request->session()->put(self::ACCESS_KEY . $attempt->id, true);
        $request->session()->put(app(ShieldService::class)->claimFor($attempt->id), true);

        return redirect()->route('student.exam.room', $schedule);
    }

    /** Konfirmasi + ruang ujian (layouts/exam.blade.php): TANPA Livewire, input, textarea, contenteditable. */
    public function room(Request $request, ExamSchedule $schedule): View|RedirectResponse
    {
        $student = $request->user('student');
        abort_unless($schedule->isAccessibleBy($student), 404);
        $attempt = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->where('student_id', $student->id)->first();
        if (! $attempt || ! $attempt->started_at || ! $request->session()->get(self::ACCESS_KEY . $attempt->id)) {
            return redirect()->route('student.exam.show', $schedule);
        }
        if ($attempt->isDone()) {
            return redirect()->route('student.exam.result', $schedule);
        }

        $exam = $schedule->exam;
        $s = $exam->resolvedSettings();
        $shield = config('kitaq.shield');
        $canvas = (bool) $s['canvas_mode'] && ! $student->accommodation('disable_canvas', false);
        $answered = $attempt->answers()->whereNotNull('answer')->count();

        $config = [
            'attemptId' => $attempt->id,
            'csrf' => csrf_token(),
            'startNo' => 1,
            'total' => $attempt->questionCount(),
            'answered' => $answered,
            'remainingSec' => $attempt->remainingSeconds(),
            'minSubmitSec' => max(0, (int) $s['min_minutes_before_submit'] * 60 - ($attempt->started_at ? (int) $attempt->started_at->diffInSeconds(now()) : 0)),
            'largeFont' => (bool) $student->accommodation('large_font', false),
            'endpoints' => [
                'question' => route('api.attempts.question', [$attempt, '__NO__']),
                'map' => route('api.attempts.map', $attempt),
                'answers' => route('api.attempts.answers', $attempt),
                'events' => route('api.attempts.events', $attempt),
                'heartbeat' => route('api.attempts.heartbeat', $attempt),
                'submit' => route('api.attempts.submit', $attempt),
                'result' => route('student.exam.result', $schedule),
                'login' => route('student.login'),
                'token' => route('student.exam.show', $schedule),
            ],
            'shield' => [
                'blurGraceMs' => (int) $shield['blur_grace_ms'],
                'splitMinWidthRatio' => (float) $shield['split_min_width_ratio'],
                'splitMinHeightRatio' => (float) $shield['split_min_height_ratio'],
                'requireFullscreen' => (bool) $s['require_fullscreen'],
                'iosRequireStandalone' => (bool) $s['ios_require_standalone'],
                'physicalKeyboard' => ['mobile' => $s['physical_keyboard_mobile'], 'desktop' => $s['physical_keyboard_desktop']],
                'browsers' => config('kitaq.load_test') ? array_keys(\App\Support\ExamSettings::BROWSERS) : $s['browsers'],
                'aiSignatures' => array_values((array) $shield['ai_signatures']),
                'heartbeatSec' => \App\Support\SystemHealth::heartbeatInterval(),
                'canvasMode' => $canvas,
                'maxWarnings' => (int) $s['max_warnings'],
            ],
        ];

        $view = (object) [
            'title' => $exam->name,
            'subject' => $exam->subject?->name,
            'duration_minutes' => $exam->duration_minutes,
            'question_count' => $attempt->questionCount(),
        ];

        return view('layouts.exam', ['exam' => $view, 'student' => $student, 'examConfig' => $config, 'schedule' => $schedule]);
    }

    public function result(Request $request, ExamSchedule $schedule): View|RedirectResponse
    {
        $student = $request->user('student');
        abort_unless($schedule->isAccessibleBy($student), 404);
        $attempt = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->where('student_id', $student->id)->first();
        if (! $attempt || ! $attempt->isDone()) {
            return redirect()->route('student.dashboard');
        }

        return view('student.result', [
            'schedule' => $schedule->load('exam.subject'),
            'attempt' => $attempt,
            'student' => $student,
            'visible' => DashboardController::scoreVisible($attempt),
        ]);
    }

    private function tokenRequired(ExamSchedule $schedule): bool
    {
        return (bool) $schedule->token_required && (bool) $schedule->exam->setting('token_required', true);
    }
}
