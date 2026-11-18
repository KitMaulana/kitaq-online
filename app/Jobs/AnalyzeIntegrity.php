<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\IntegrityAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Analitik kerja sama setelah jadwal ditutup (antrean → cron). */
class AnalyzeIntegrity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 45;

    public function __construct(public int $scheduleId)
    {
    }

    public function handle(IntegrityAnalyzer $analyzer): void
    {
        if ($s = ExamSchedule::query()->find($this->scheduleId)) {
            $analyzer->analyze($s);
        }
    }
}
