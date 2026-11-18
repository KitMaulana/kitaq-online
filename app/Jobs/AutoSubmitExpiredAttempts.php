<?php

namespace App\Jobs;

use App\Models\ExamAttempt;
use App\Services\AttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Waktu habis → kumpulkan otomatis dari SERVER walau HP siswa mati (Spesifikasi 5.6 & 7.4).
 * Dijalankan scheduler tiap menit (cron → schedule:run).
 */
class AutoSubmitExpiredAttempts implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(AttemptService $attempts): int
    {
        $n = 0;
        ExamAttempt::query()
            ->whereIn('status', ExamAttempt::ACTIVE_STATUSES)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->whereHas('schedule', fn ($q) => $q->where('status', '!=', 'paused'))
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($attempts, &$n) {
                foreach ($chunk as $a) {
                    $attempts->submit($a, false);
                    $n++;
                }
            });

        return $n;
    }
}
