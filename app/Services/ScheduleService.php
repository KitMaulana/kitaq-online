<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AttemptCommand;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siklus jadwal: buka/tutup otomatis (scheduler) dan manual (mulai, jeda semua, lanjutkan, perpanjang, tutup paksa).
 */
class ScheduleService
{
    public function __construct(private AttemptService $attempts)
    {
    }

    /** Dipanggil scheduler tiap menit. @return array{opened:int,closed:int} */
    public function tick(): array
    {
        $opened = 0;
        $closed = 0;
        ExamSchedule::query()->where('status', 'scheduled')->where('opens_at', '<=', now())->where('closes_at', '>', now())
            ->each(function (ExamSchedule $s) use (&$opened) {
                $this->open($s, null, true);
                $opened++;
            });
        ExamSchedule::query()->whereIn('status', ['scheduled', 'open'])->where('closes_at', '<=', now())
            ->each(function (ExamSchedule $s) use (&$closed) {
                $this->close($s, null, true);
                $closed++;
            });

        return ['opened' => $opened, 'closed' => $closed];
    }

    public function open(ExamSchedule $s, ?User $by = null, bool $auto = false): void
    {
        if ($s->closes_at->isPast()) {
            return;
        }
        $s->forceFill(['status' => 'open', 'opened_at' => $s->opened_at ?? now(), 'paused_at' => null])->save();
        if (! $auto) {
            if ($s->opens_at->isFuture()) {
                $s->forceFill(['opens_at' => now()])->save();
            }
            ActivityLog::record('schedule_opened', 'Membuka jadwal ' . $s->title(), $s);
        }
    }

    public function pause(ExamSchedule $s, User $by): void
    {
        if ($s->status !== 'open') {
            return;
        }
        $s->forceFill(['status' => 'paused', 'paused_at' => now()])->save();
        ActivityLog::record('schedule_paused', 'Menjeda semua peserta ' . $s->title(), $s);
    }

    /** Lanjutkan: waktu yang hilang selama jeda dikembalikan ke semua peserta aktif. */
    public function resume(ExamSchedule $s, User $by): void
    {
        if ($s->status !== 'paused') {
            return;
        }
        $secs = $s->paused_at ? (int) $s->paused_at->diffInSeconds(now()) : 0;
        DB::transaction(function () use ($s, $secs) {
            if ($secs > 0) {
                $s->closes_at = $s->closes_at->copy()->addSeconds($secs);
                foreach ($s->attempts()->whereIn('status', ExamAttempt::ACTIVE_STATUSES)->get() as $a) {
                    if ($a->deadline_at) {
                        $a->forceFill(['deadline_at' => $a->deadline_at->copy()->addSeconds($secs)])->save();
                    }
                }
            }
            $s->forceFill(['status' => 'open', 'paused_at' => null])->save();
        });
        ActivityLog::record('schedule_resumed', 'Melanjutkan ' . $s->title() . " (+{$secs} dtk)", $s);
    }

    /** Perpanjang jadwal (dan batas waktu peserta aktif) N menit. */
    public function extend(ExamSchedule $s, int $minutes, User $by): void
    {
        $minutes = max(1, min(240, $minutes));
        DB::transaction(function () use ($s, $minutes, $by) {
            $s->forceFill(['closes_at' => $s->closes_at->copy()->addMinutes($minutes)])->save();
            foreach ($s->attempts()->whereIn('status', ExamAttempt::ACTIVE_STATUSES)->get() as $a) {
                $this->extendAttempt($a, $minutes, $by, false);
            }
        });
        ActivityLog::record('schedule_extended', "Memperpanjang {$s->title()} {$minutes} menit", $s);
    }

    public function extendAttempt(ExamAttempt $a, int $minutes, User $by, bool $log = true): void
    {
        $minutes = max(1, min(240, $minutes));
        $schedule = $a->schedule;
        if ($a->deadline_at) {
            $newDeadline = $a->deadline_at->copy()->addMinutes($minutes);
            if ($newDeadline->gt($schedule->closes_at)) {
                $schedule->forceFill(['closes_at' => $newDeadline])->save();
            }
            $a->forceFill(['deadline_at' => $newDeadline, 'extra_minutes' => $a->extra_minutes + $minutes])->save();
        }
        AttemptCommand::query()->create(['exam_attempt_id' => $a->id, 'type' => 'extend', 'payload' => ['seconds' => $minutes * 60], 'user_id' => $by->id]);
        if ($log) {
            ActivityLog::record('attempt_extended', "Tambah waktu {$minutes} menit untuk {$a->student->name}", $a);
        }
    }

    /** Tutup: semua percobaan aktif dikumpulkan otomatis. */
    public function close(ExamSchedule $s, ?User $by = null, bool $auto = false): int
    {
        $n = 0;
        foreach ($s->attempts()->whereIn('status', ExamAttempt::ACTIVE_STATUSES)->get() as $a) {
            $this->attempts->submit($a, ! $auto, $by);
            $n++;
        }
        $s->forceFill(['status' => 'closed', 'closed_at' => now(), 'paused_at' => null])->save();
        // Analitik kerja sama dijalankan antrean setelah jadwal ditutup.
        \App\Jobs\AnalyzeIntegrity::dispatch($s->id);
        if (! $auto) {
            ActivityLog::record('schedule_closed', "Tutup paksa {$s->title()} ({$n} dikumpulkan)", $s);
        }

        return $n;
    }

    /** Ganti kunci token (semua token lama langsung tidak berlaku). */
    public function rotateTokenKey(ExamSchedule $s, User $by): void
    {
        $s->forceFill(['token_key' => Str::random(40)])->save();
        ActivityLog::record('token_rotated', 'Mengganti kunci token ' . $s->title(), $s);
    }
}
