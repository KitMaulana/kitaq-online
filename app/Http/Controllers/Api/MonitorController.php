<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttemptAnswer;
use App\Models\AttemptCommand;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\MonitorAlert;
use App\Models\ShieldEvent;
use App\Services\AttemptService;
use App\Services\ScheduleService;
use App\Services\Shield\ShieldService;
use App\Services\Shield\ViolationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Monitoring pengawas lewat polling HTTP (Spesifikasi 5.10): ringkasan kartu di-cache 2 dtk per jadwal,
 * event & alarm dikirim delta (since). Aksi: buka kunci (wajib alasan), reset login, tambah waktu,
 * kumpulkan paksa, kirim pesan.
 */
class MonitorController extends Controller
{
    private function visible(Request $request, ExamSchedule $schedule): ExamSchedule
    {
        abort_unless(ExamSchedule::query()->visibleTo($request->user())->whereKey($schedule->id)->exists(), 403);

        return $schedule;
    }

    public function show(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $this->visible($request, $schedule);
        $since = max(0, (int) $request->query('since', 0));
        $alertSince = (int) $request->query('alert_since', -1);  // -1 = poll pertama (hanya ambil id terakhir)
        $ttl = (int) config('kitaq.realtime.monitor_cache_sec', 2);

        $snapshot = Cache::remember("kitaq:mon:{$schedule->id}", $ttl, fn () => $this->snapshot($schedule->fresh()));

        $attemptIds = $snapshot['attempt_ids'];
        $first = $since === 0;
        $events = ShieldEvent::query()
            ->whereIn('exam_attempt_id', $attemptIds ?: [0])
            ->whereIn('action', ['log', 'warn', 'lock'])
            ->when($first, fn ($q) => $q->where('received_at', '>=', now()->subHours(6)), fn ($q) => $q->where('id', '>', $since))
            ->orderByDesc('id')->limit($first ? 40 : 100)->get(['id', 'exam_attempt_id', 'type', 'action', 'merged', 'received_at'])
            ->sortBy('id')->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'attempt_id' => $e->exam_attempt_id,
                'name' => $snapshot['names'][$e->exam_attempt_id] ?? '-',
                'type' => $e->type,
                'label' => ShieldEvent::label($e->type),
                'action' => $e->action,
                'merged' => $e->merged,
                'time' => $e->received_at->format('H:i:s'),
            ]);

        $alerts = MonitorAlert::query()->where('exam_schedule_id', $schedule->id)
            ->where('id', '>', max(0, $alertSince))
            ->orderBy('id')->limit(30)->get(['id', 'kind', 'message']);
        $lastAlert = (int) MonitorAlert::query()->where('exam_schedule_id', $schedule->id)->max('id');

        return response()->json([
            'schedule' => $snapshot['schedule'],
            'summary' => $snapshot['summary'],
            'cards' => $snapshot['cards'],
            'events' => $events,
            'alerts' => $alertSince < 0 ? [['id' => $lastAlert, 'kind' => 'init', 'message' => '']] : $alerts->map(fn ($a) => [
                'id' => $a->id, 'kind' => $a->kind, 'message' => $a->message,
                'title' => ['locked' => 'Siswa terkunci', 'second_instance' => 'Login dari perangkat lain', 'heartbeat_gap' => 'Siswa terputus'][$a->kind] ?? 'Perhatian',
            ]),
            'token' => [
                'token' => $schedule->currentToken(),
                'left' => $schedule->secondsUntilTokenRotation(),
                'required' => (bool) $schedule->token_required,
            ],
            'server_time' => now()->format('H:i:s'),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /** Ringkasan kartu siswa (di-cache 2 dtk). */
    private function snapshot(ExamSchedule $schedule): array
    {
        $students = $schedule->participantsQuery()->with('classroom:id,name')->orderBy('name')->get(['id', 'name', 'nisn', 'classroom_id', 'photo']);
        $attempts = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->get()->keyBy('student_id');
        $answered = AttemptAnswer::query()->whereIn('exam_attempt_id', $attempts->pluck('id')->all() ?: [0])->whereNotNull('answer')
            ->selectRaw('exam_attempt_id, COUNT(*) as n')->groupBy('exam_attempt_id')->pluck('n', 'exam_attempt_id');
        $gap = (int) config('kitaq.shield.heartbeat_gap_sec', 60);
        $total = $schedule->exam->effectiveQuestionCount();

        $cards = [];
        $summary = ['all' => 0, 'not_started' => 0, 'in_progress' => 0, 'warned' => 0, 'locked' => 0, 'disconnected' => 0, 'submitted' => 0];
        $names = [];
        foreach ($students as $st) {
            $a = $attempts[$st->id] ?? null;
            $status = $a ? $a->monitorStatus($gap) : 'not_started';
            $summary['all']++;
            $summary[$status]++;
            if ($a) {
                $names[$a->id] = $st->name;
            }
            $cards[] = [
                'id' => $st->id,
                'attempt_id' => $a?->id,
                'name' => $st->name,
                'nisn' => $st->nisn,
                'class' => $st->classroom?->name,
                'initials' => initials($st->name),
                'photo' => $st->photo ? route('admin.monitoring.photo', $st->id) : null,
                'status' => $status,
                'answered' => $a ? (int) ($answered[$a->id] ?? 0) : 0,
                'total' => $a && $a->question_order ? count($a->question_order) : $total,
                'warnings' => (int) ($a->warning_count ?? 0),
                'max_warnings' => (int) $schedule->exam->setting('max_warnings', 2),
                'remaining' => $a && $a->isActive() ? $a->remainingSeconds() : null,
                'device' => $a ? device_label($a->device_info) : null,
                'ip' => $a?->ip_address,
                'last_seen' => $a?->last_heartbeat_at ? (int) $a->last_heartbeat_at->diffInSeconds(now()) : null,
                'lock_reason' => $a && $a->status === 'locked' ? ViolationPolicy::reasonFor((string) $a->lock_reason) : null,
                'score' => $a && $a->isDone() ? $a->score : null,
            ];
        }

        return [
            'schedule' => [
                'id' => $schedule->id, 'status' => $schedule->status, 'status_label' => $schedule->statusLabel(),
                'closes_at' => $schedule->closes_at->format('H:i'), 'closes_in' => max(0, (int) now()->diffInSeconds($schedule->closes_at, false)),
            ],
            'summary' => $summary,
            'cards' => $cards,
            'names' => $names,
            'attempt_ids' => $attempts->pluck('id')->all(),
        ];
    }

    /** Aksi per siswa. */
    public function action(Request $request, ExamAttempt $attempt, string $action): JsonResponse
    {
        $schedule = $this->visible($request, $attempt->schedule);
        $user = $request->user();
        $msg = 'Berhasil';
        switch ($action) {
            case 'unlock':
                $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'], 'reset_warnings' => ['nullable', 'boolean']], [], ['reason' => 'alasan']);
                abort_unless($attempt->isLocked(), 422, 'Siswa tidak sedang terkunci.');
                app(ShieldService::class)->unlock($attempt, $user, $data['reason'], (bool) ($data['reset_warnings'] ?? true));
                $msg = 'Kunci dibuka';
                break;
            case 'reset-login':
                app(ShieldService::class)->resetLogin($attempt, $user);
                $msg = 'Login direset. Siswa bisa masuk di perangkat baru dengan token.';
                break;
            case 'extend':
                $data = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:240']]);
                abort_unless($attempt->isActive(), 422, 'Siswa tidak sedang mengerjakan.');
                app(ScheduleService::class)->extendAttempt($attempt, (int) $data['minutes'], $user);
                $msg = "Waktu ditambah {$data['minutes']} menit";
                break;
            case 'force-submit':
                abort_if($attempt->isDone(), 422, 'Sudah dikumpulkan.');
                AttemptCommand::query()->create(['exam_attempt_id' => $attempt->id, 'type' => 'force_submit', 'user_id' => $user->id]);
                app(AttemptService::class)->submit($attempt, true, $user);
                $msg = 'Dikumpulkan paksa';
                break;
            case 'message':
                $data = $request->validate(['message' => ['required', 'string', 'max:200']], [], ['message' => 'pesan']);
                AttemptCommand::query()->create(['exam_attempt_id' => $attempt->id, 'type' => 'message', 'payload' => ['text' => $data['message']], 'user_id' => $user->id]);
                ActivityLog::record('message_sent', "Pesan ke {$attempt->student->name}: {$data['message']}", $attempt);
                $msg = 'Pesan dikirim (muncul di HP siswa pada heartbeat berikutnya)';
                break;
            default:
                abort(404);
        }
        Cache::forget("kitaq:mon:{$schedule->id}");

        return response()->json(['message' => $msg]);
    }

    /** Aksi massal pada jadwal (jeda semua, lanjutkan, perpanjang semua, tutup paksa). */
    public function scheduleAction(Request $request, ExamSchedule $schedule, string $action): JsonResponse
    {
        $this->visible($request, $schedule);
        $svc = app(ScheduleService::class);
        $user = $request->user();
        $msg = match ($action) {
            'pause' => tap('Semua peserta dijeda', fn () => $svc->pause($schedule, $user)),
            'resume' => tap('Ujian dilanjutkan', fn () => $svc->resume($schedule, $user)),
            'extend' => tap('Jadwal diperpanjang', fn () => $svc->extend($schedule, (int) $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:240']])['minutes'], $user)),
            'open' => tap('Jadwal dibuka', fn () => $svc->open($schedule, $user)),
            'close' => 'Ditutup: ' . $svc->close($schedule, $user) . ' peserta dikumpulkan',
            default => abort(404),
        };
        Cache::forget("kitaq:mon:{$schedule->id}");

        return response()->json(['message' => $msg]);
    }

    /** Linimasa perangkat & log pelanggaran satu siswa. */
    public function detail(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->visible($request, $attempt->schedule);

        return response()->json([
            'events' => $attempt->events()->orderByDesc('id')->limit(150)->get()->map(fn ($e) => [
                'time' => ($e->client_at ?? $e->received_at)->format('H:i:s'), 'label' => ShieldEvent::label($e->type), 'type' => $e->type,
                'action' => $e->action, 'merged' => $e->merged, 'detail' => $e->detail,
            ]),
            'instances' => $attempt->instances()->orderBy('first_seen_at')->get()->map(fn ($i) => [
                'id' => substr($i->instance_id, 0, 8), 'device' => device_label(['os' => $i->os, 'browser' => $i->browser, 'standalone' => $i->standalone]),
                'ip' => $i->ip, 'first' => $i->first_seen_at?->format('H:i:s'), 'last' => $i->last_seen_at?->format('H:i:s'),
                'active' => $i->instance_id === $attempt->active_instance_id,
            ]),
            'unlocks' => $attempt->unlockLogs()->with('user:id,name')->latest('id')->get()->map(fn ($u) => [
                'time' => $u->created_at->format('H:i'), 'by' => $u->user?->name, 'reason' => $u->reason,
            ]),
            'started_at' => $attempt->started_at?->format('H:i:s'),
            'deadline_at' => $attempt->deadline_at?->format('H:i:s'),
        ]);
    }

    public function photo(Request $request, int $student)
    {
        $s = \App\Models\Student::query()->findOrFail($student);
        abort_unless($s->photo && Storage::disk('local')->exists($s->photo), 404);

        return response()->file(Storage::disk('local')->path($s->photo), ['Cache-Control' => 'private, max-age=3600']);
    }
}
