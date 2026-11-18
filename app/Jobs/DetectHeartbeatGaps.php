<?php

namespace App\Jobs;

use App\Models\ExamAttempt;
use App\Models\MonitorAlert;
use App\Services\Shield\ShieldService;
use App\Services\Shield\ViolationPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Attempt aktif tanpa heartbeat > heartbeat_gap_sec (60) → event `heartbeat_gap` (log) + kartu "terputus".
 * Tidak mengunci, KECUALI ujian diatur "kunci setelah N detik" (offline_lock).
 */
class DetectHeartbeatGaps implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(ShieldService $shield): int
    {
        $gap = (int) config('kitaq.shield.heartbeat_gap_sec', 60);
        $n = 0;
        ExamAttempt::query()
            ->with('schedule.exam', 'student')
            ->whereIn('status', ['in_progress', 'warned'])
            ->whereNotNull('started_at')
            ->where(function ($w) use ($gap) {
                $w->where('last_heartbeat_at', '<', now()->subSeconds($gap))
                    ->orWhere(fn ($x) => $x->whereNull('last_heartbeat_at')->where('started_at', '<', now()->subSeconds($gap)));
            })
            ->whereHas('schedule', fn ($q) => $q->where('status', 'open'))
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($shield, $gap, &$n) {
                foreach ($chunk as $a) {
                    $silentSince = $a->last_heartbeat_at ?? $a->started_at;
                    $silent = (int) $silentSince->diffInSeconds(now());
                    if ($a->gap_reported_at === null) {
                        $shield->recordServerEvent($a, 'heartbeat_gap', ['silent_sec' => $silent], ViolationPolicy::LOG);
                        $a->forceFill(['gap_reported_at' => now()])->save();
                        MonitorAlert::query()->create([
                            'exam_schedule_id' => $a->exam_schedule_id, 'exam_attempt_id' => $a->id, 'kind' => 'heartbeat_gap',
                            'message' => "{$a->student->name} terputus (tanpa sinyal {$silent} dtk).",
                        ]);
                        $n++;
                    }
                    $s = $a->schedule->exam->resolvedSettings();
                    if ($s['offline_mode'] === 'lock' && $silent >= (int) $s['offline_lock_after_sec']) {
                        $shield->recordServerEvent($a, 'offline_lock', ['silent_sec' => $silent]);
                    }
                }
            });

        return $n;
    }
}
