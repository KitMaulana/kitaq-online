<?php

namespace App\Services\Shield;

use App\Models\ActivityLog;
use App\Models\AttemptCommand;
use App\Models\AttemptInstance;
use App\Models\ExamAttempt;
use App\Models\MonitorAlert;
use App\Models\ShieldEvent;
use App\Models\UnlockLog;
use App\Models\User;
use App\Services\AttemptService;
use App\Support\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * KITAQ Shield di server (Spesifikasi Bagian 7). Klien mendeteksi & melapor, SERVER memutuskan lewat ViolationPolicy.
 *  - event idempoten per (attempt, instance, seq);
 *  - waktu klien dijepit ke [started_at, now] (jarak antar-event tetap dipakai untuk jendela gabung);
 *  - evaluasi di dalam transaksi + lockForUpdate pada attempt;
 *  - satu instance aktif per attempt (second_instance), nonce heartbeat bergulir.
 * Endpoint ringan: tanpa view, query ter-indeks, satu transaksi pendek.
 */
class ShieldService
{
    public const CLAIM_SESSION_KEY = 'kq_claim_';

    /** Event yang boleh dikirim klien (jenis lain dicatat sebagai 'log' oleh ViolationPolicy). */
    private const TYPE_PATTERN = '/^[a-z_]{2,40}$/';

    /**
     * @param list<array{seq:int,type:string,at?:int,detail?:array}> $events
     * @return array{acked:list<int>,state:array}
     */
    public function processEvents(ExamAttempt $attempt, string $instanceId, ?int $clientNowMs, array $events, Request $request): array
    {
        $acked = array_values(array_unique(array_map(fn ($e) => (int) ($e['seq'] ?? 0), $events)));

        if ($attempt->isDone()) {
            return ['acked' => $acked, 'state' => $this->state($attempt)];
        }

        $gate = $this->gateInstance($attempt, $instanceId, $request);
        if ($gate !== null) {
            return ['acked' => $acked, 'state' => $gate];
        }

        $newWarning = false;
        $reason = null;
        $now = Carbon::now();

        DB::transaction(function () use ($attempt, $instanceId, $clientNowMs, $events, $now, &$newWarning, &$reason) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            $policy = $locked->schedule->exam->policy();
            $state = $locked->shieldState();

            usort($events, fn ($a, $b) => ((int) ($a['seq'] ?? 0)) <=> ((int) ($b['seq'] ?? 0)));
            $seqs = array_filter(array_map(fn ($e) => (int) ($e['seq'] ?? 0), $events), fn ($s) => $s > 0);
            $existing = $seqs ? ShieldEvent::query()->where('exam_attempt_id', $locked->id)->where('instance_id', $instanceId)
                ->whereIn('seq', $seqs)->pluck('seq')->all() : [];
            $existing = array_flip($existing);

            $rows = [];
            $maxSeq = (int) $locked->last_event_seq;
            foreach ($events as $ev) {
                $seq = (int) ($ev['seq'] ?? 0);
                $type = (string) ($ev['type'] ?? '');
                if ($seq < 1 || isset($existing[$seq]) || ! preg_match(self::TYPE_PATTERN, $type)) {
                    continue;
                }
                $existing[$seq] = true;
                $occurredAt = $this->clampTime($locked, $clientNowMs, isset($ev['at']) ? (int) $ev['at'] : null, $now);
                $decision = $policy->evaluate($state, $type, $occurredAt->getTimestamp());
                $state = $decision->state;
                if ($decision->newWarning) {
                    $newWarning = true;
                    $reason = $decision->reasonText;
                }
                if ($decision->locked) {
                    $reason = $decision->reasonText;
                    $locked->locked_at = $now;
                    $this->alert($locked, 'locked', "{$locked->student->name} terkunci: {$decision->reasonText}");
                }
                if ($type === 'exam_started' && is_array($ev['detail'] ?? null)) {
                    $this->updateInstanceInfo($locked, $instanceId, $ev['detail']);
                }
                $rows[] = [
                    'exam_attempt_id' => $locked->id,
                    'seq' => $seq,
                    'instance_id' => $instanceId,
                    'type' => $type,
                    'action' => $decision->action,
                    'merged' => $decision->merged,
                    'detail' => json_encode(array_slice((array) ($ev['detail'] ?? []), 0, 20), JSON_UNESCAPED_UNICODE),
                    'client_at' => $occurredAt,
                    'received_at' => $now,
                ];
                $maxSeq = max($maxSeq, $seq);
            }
            if ($rows) {
                ShieldEvent::query()->insert($rows);
            }
            $locked->applyShieldState($state);
            $locked->last_event_seq = $maxSeq;
            $locked->save();
            $attempt->setRawAttributes($locked->getAttributes(), true);
        });

        return ['acked' => $acked, 'state' => $this->state($attempt->fresh(), $newWarning, $reason)];
    }

    /**
     * @param array<string,mixed> $p  instance_id, nonce, visible, focused, fullscreen, vw, vh, sw, sh, online, blockers[], pending_events
     * @return array<string,mixed>
     */
    public function heartbeat(ExamAttempt $attempt, array $p, Request $request): array
    {
        $instanceId = (string) ($p['instance_id'] ?? '');
        $base = ['commands' => [], 'next_in_sec' => SystemHealth::heartbeatInterval(), 'server_now' => time()];

        if ($attempt->isDone()) {
            return $base + ['state' => $this->state($attempt), 'nonce' => null];
        }
        $gate = $this->gateInstance($attempt, $instanceId, $request);
        if ($gate !== null) {
            return $base + ['state' => $gate, 'nonce' => null];
        }

        // Waktu habis → kumpulkan otomatis oleh server.
        if ($attempt->deadline_at && $attempt->deadline_at->isPast() && $attempt->schedule->status !== 'paused') {
            app(AttemptService::class)->submit($attempt, false);

            return $base + ['state' => $this->state($attempt->fresh()), 'nonce' => null];
        }

        $sent = $p['nonce'] ?? null;
        if ($sent !== null && $attempt->heartbeat_nonce !== null && ! hash_equals((string) $attempt->heartbeat_nonce, (string) $sent)) {
            $recent = ShieldEvent::query()->where('exam_attempt_id', $attempt->id)->where('type', 'heartbeat_invalid')
                ->where('received_at', '>=', now()->subMinute())->exists();
            if (! $recent) {
                $this->recordServerEvent($attempt, 'heartbeat_invalid', ['sent' => substr((string) $sent, 0, 8)], ViolationPolicy::LOG);
            }
        }

        $nonce = Str::random(24);
        $attempt->forceFill([
            'heartbeat_nonce' => $nonce,
            'last_heartbeat_at' => now(),
            'gap_reported_at' => null,
            'ip_address' => $request->ip(),
        ])->save();
        AttemptInstance::query()->where('exam_attempt_id', $attempt->id)->where('instance_id', $instanceId)
            ->update(['last_seen_at' => now(), 'ip' => $request->ip()]);

        $commands = [];
        $pending = AttemptCommand::query()->where('exam_attempt_id', $attempt->id)->whereNull('delivered_at')->orderBy('id')->limit(10)->get();
        if ($pending->isNotEmpty()) {
            AttemptCommand::query()->whereIn('id', $pending->pluck('id'))->update(['delivered_at' => now()]);
            foreach ($pending as $c) {
                $commands[] = ['type' => $c->type] + (array) $c->payload;
            }
        }

        return ['state' => $this->state($attempt), 'commands' => $commands, 'nonce' => $nonce] + $base;
    }

    /**
     * Event dari server (heartbeat_gap, second_instance saat login, heartbeat_invalid, offline_lock).
     * $forceAction: simpan dengan aksi tertentu tanpa mengubah status; null = dievaluasi ViolationPolicy.
     */
    public function recordServerEvent(ExamAttempt $attempt, string $type, array $detail = [], ?string $forceAction = null): ?ShieldDecision
    {
        return DB::transaction(function () use ($attempt, $type, $detail, $forceAction) {
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if (! $locked || $locked->isDone()) {
                return null;
            }
            $decision = null;
            $action = $forceAction;
            $merged = false;
            if ($forceAction === null) {
                $decision = $locked->schedule->exam->policy()->evaluate($locked->shieldState(), $type, time());
                $action = $decision->action;
                $merged = $decision->merged;
                $locked->applyShieldState($decision->state);
                if ($decision->locked) {
                    $locked->locked_at = now();
                    $this->alert($locked, 'locked', "{$locked->student->name} terkunci: {$decision->reasonText}");
                }
                $locked->save();
                $attempt->setRawAttributes($locked->getAttributes(), true);
            }
            ShieldEvent::query()->create([
                'exam_attempt_id' => $locked->id, 'seq' => null, 'instance_id' => null, 'type' => $type, 'action' => $action,
                'merged' => $merged, 'detail' => $detail ?: null, 'client_at' => now(), 'received_at' => now(),
            ]);

            return $decision;
        });
    }

    /** Buka kunci oleh pengawas — alasan WAJIB. */
    public function unlock(ExamAttempt $attempt, User $by, string $reason, bool $resetWarnings = true): void
    {
        DB::transaction(function () use ($attempt, $by, $reason, $resetWarnings) {
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($locked->isDone()) {
                return;
            }
            $policy = $locked->schedule->exam->policy();
            $state = $policy->unlock($locked->shieldState(), $resetWarnings);
            // Waktu dijeda saat terkunci (opsi ujian) → tambahkan durasi terkunci ke batas waktu.
            if ($locked->locked_at && $locked->schedule->exam->setting('timer_when_locked') === 'pause' && $locked->deadline_at) {
                $secs = (int) $locked->locked_at->diffInSeconds(now());
                $locked->deadline_at = app(AttemptService::class)->capDeadline($locked, $locked->deadline_at->copy()->addSeconds($secs));
            }
            $locked->applyShieldState($state);
            $locked->locked_at = null;
            $locked->last_incident_at = null;
            $locked->save();
            UnlockLog::query()->create(['exam_attempt_id' => $locked->id, 'user_id' => $by->id, 'reason' => mb_substr($reason, 0, 500), 'reset_warnings' => $resetWarnings]);
            ActivityLog::record('unlock', "Membuka kunci ujian {$locked->student->name}: {$reason}", $locked, ['reset_warnings' => $resetWarnings]);
            $attempt->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** Reset Login: kosongkan instance aktif & sesi siswa → perangkat berikutnya (dengan token) menjadi aktif. */
    public function resetLogin(ExamAttempt $attempt, User $by): void
    {
        DB::transaction(function () use ($attempt, $by) {
            $attempt->forceFill(['active_instance_id' => null, 'heartbeat_nonce' => null])->save();
            $student = $attempt->student;
            if ($student->current_session_id && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('id', $student->current_session_id)->delete();
            }
            $student->forceFill(['current_session_id' => null])->save();
            ActivityLog::record('reset_login', "Reset login {$student->name} ({$student->nisn})", $attempt);
        });
    }

    /** Instance yang memulai lewat form token (klaim di sesi) menjadi aktif. */
    public function claimFor(int $attemptId): string
    {
        return self::CLAIM_SESSION_KEY . $attemptId;
    }

    /**
     * Gerbang satu instance aktif. null = boleh lanjut; array = state yang harus dibalas.
     */
    private function gateInstance(ExamAttempt $attempt, string $instanceId, Request $request): ?array
    {
        if ($instanceId === '' || strlen($instanceId) > 64) {
            return array_merge($this->state($attempt), ['status' => 'blocked_instance', 'reason_text' => 'Perangkat tidak dikenali. Muat ulang halaman.']);
        }
        $active = $attempt->active_instance_id;
        $session = $request->hasSession() ? $request->session() : null;
        $claimKey = $this->claimFor($attempt->id);

        if ($active === null) {
            if ($session && $session->pull($claimKey)) {
                $attempt->forceFill(['active_instance_id' => $instanceId])->save();
                $this->registerInstance($attempt, $instanceId, $request);

                return null;
            }

            return array_merge($this->state($attempt), ['status' => 'blocked_instance', 'reason_text' => 'Login direset pengawas. Masukkan token lagi untuk melanjutkan.']);
        }

        if (hash_equals($active, $instanceId)) {
            return null;
        }

        // Tab lama mati (HP menutup tab karena RAM, browser dibuka ulang) → ambil alih bila instance lama sudah diam.
        $silent = ! $attempt->last_heartbeat_at || $attempt->last_heartbeat_at->lt(now()->subSeconds(max(30, (int) config('kitaq.shield.heartbeat_sec', 15) * 2 + 10)));
        if ($silent && $session && ($session->pull($claimKey) || $request->user('student')?->current_session_id === $session->getId())) {
            $attempt->forceFill(['active_instance_id' => $instanceId, 'heartbeat_nonce' => null])->save();
            $this->registerInstance($attempt, $instanceId, $request);
            $this->recordServerEvent($attempt, 'instance_takeover', ['from' => substr($active, 0, 12), 'to' => substr($instanceId, 0, 12)], ViolationPolicy::LOG);

            return null;
        }

        // Instance kedua saat yang pertama masih hidup → second_instance (sekali per instance), diputuskan ViolationPolicy.
        $already = ShieldEvent::query()->where('exam_attempt_id', $attempt->id)->where('type', 'second_instance')
            ->where('received_at', '>=', now()->subMinutes(10))->where('detail->instance', $instanceId)->exists();
        if (! $already) {
            $this->registerInstance($attempt, $instanceId, $request);
            $this->recordServerEvent($attempt, 'second_instance', ['instance' => $instanceId, 'ip' => $request->ip(), 'device' => UserAgentInspector::inspect((string) $request->userAgent())]);
            $this->alert($attempt, 'second_instance', "{$attempt->student->name} membuka ujian di perangkat/tab lain.");
        }

        return array_merge($this->state($attempt->fresh()), ['status' => 'blocked_instance', 'reason_text' => ViolationPolicy::reasonFor('second_instance')]);
    }

    public function registerInstance(ExamAttempt $attempt, string $instanceId, Request $request): void
    {
        $info = UserAgentInspector::inspect((string) $request->userAgent());
        $inst = AttemptInstance::query()->firstOrNew(['exam_attempt_id' => $attempt->id, 'instance_id' => $instanceId]);
        $inst->fill([
            'os' => $inst->os ?? $info['os'],
            'browser' => $inst->browser ?? $info['browser'],
            'device' => $inst->device ?? $info['device'],
            'ip' => $request->ip(),
            'fingerprint' => $inst->fingerprint ?? hash('sha256', (string) $request->userAgent()),
            'first_seen_at' => $inst->first_seen_at ?? now(),
            'last_seen_at' => now(),
        ])->save();
    }

    /** Info perangkat dari klien (platform.js) saat exam_started: os, browser, device, standalone. */
    private function updateInstanceInfo(ExamAttempt $attempt, string $instanceId, array $d): void
    {
        $allowed = fn ($v) => is_string($v) ? substr(preg_replace('/[^a-z_]/', '', strtolower($v)) ?? '', 0, 20) : null;
        $info = ['os' => $allowed($d['os'] ?? null), 'browser' => $allowed($d['browser'] ?? null), 'device' => $allowed($d['device'] ?? null), 'standalone' => (bool) ($d['standalone'] ?? false)];
        AttemptInstance::query()->where('exam_attempt_id', $attempt->id)->where('instance_id', $instanceId)->update($info);
        $attempt->device_info = $info;
    }

    private function clampTime(ExamAttempt $attempt, ?int $clientNowMs, ?int $atMs, Carbon $now): Carbon
    {
        $t = $now->copy();
        if ($clientNowMs !== null && $atMs !== null && $atMs <= $clientNowMs) {
            $t = $now->copy()->subMilliseconds(min($clientNowMs - $atMs, 86_400_000));
        }
        $start = $attempt->started_at ?? $now;

        return $t->lt($start) ? $start->copy() : ($t->gt($now) ? $now->copy() : $t);
    }

    private function alert(ExamAttempt $attempt, string $kind, string $message): void
    {
        MonitorAlert::query()->create([
            'exam_schedule_id' => $attempt->exam_schedule_id,
            'exam_attempt_id' => $attempt->id,
            'kind' => $kind,
            'message' => mb_substr($message, 0, 255),
        ]);
    }

    /** State untuk klien (format sesuai resources/js/exam/exam-app.js renderState). */
    public function state(ExamAttempt $attempt, bool $newWarning = false, ?string $reason = null): array
    {
        $exam = $attempt->schedule->exam;
        $status = match (true) {
            $attempt->isDone() => 'done',
            $attempt->status === 'locked' => 'locked',
            $attempt->schedule->status === 'paused' => 'paused',
            $attempt->status === 'warned' => 'warned',
            default => 'active',
        };

        return [
            'status' => $status,
            'warnings' => (int) $attempt->warning_count,
            'max_warnings' => (int) $exam->setting('max_warnings', 2),
            'remaining_sec' => $attempt->remainingSeconds(),
            'reason_text' => $reason ?? ($status === 'locked' ? ViolationPolicy::reasonFor((string) $attempt->lock_reason) : null),
            'new_warning' => $newWarning,
        ];
    }
}
