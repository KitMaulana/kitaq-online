<?php

namespace App\Livewire\Admin;

use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\MonitorAlert;
use App\Models\ShieldEvent;
use App\Models\Student;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $today = ExamSchedule::query()->visibleTo($user)->with(['exam.subject', 'classroom'])
            ->whereDate('date', today())->orderBy('opens_at')->get();
        $scheduleIds = $today->pluck('id');

        $violationsToday = ShieldEvent::query()->whereIn('action', ['warn', 'lock'])->where('received_at', '>=', today())->count();

        $perHour = ShieldEvent::query()->whereIn('action', ['warn', 'lock', 'log'])->where('received_at', '>=', today())
            ->get(['received_at'])->groupBy(fn ($e) => (int) $e->received_at->format('H'))->map->count();
        $hours = range(6, 17);

        $scores = DB::table('exam_attempts')
            ->join('exam_schedules', 'exam_schedules.id', '=', 'exam_attempts.exam_schedule_id')
            ->join('exams', 'exams.id', '=', 'exam_schedules.exam_id')
            ->join('subjects', 'subjects.id', '=', 'exams.subject_id')
            ->whereIn('exam_attempts.status', ExamAttempt::DONE_STATUSES)
            ->whereNotNull('exam_attempts.score')
            ->groupBy('subjects.name')
            ->selectRaw('subjects.name as name, AVG(exam_attempts.score) as avg_score')
            ->orderBy('subjects.name')->limit(12)->get();

        $attention = ExamAttempt::query()->with(['student', 'schedule.exam'])
            ->whereIn('exam_schedule_id', $scheduleIds)->where('status', 'locked')->latest('updated_at')->limit(8)->get();
        $alerts = MonitorAlert::query()->whereIn('exam_schedule_id', $scheduleIds)->where('kind', 'second_instance')
            ->latest('id')->limit(5)->get();

        $counts = ExamAttempt::query()->whereIn('exam_schedule_id', $scheduleIds)
            ->selectRaw('exam_schedule_id, COUNT(*) as total, SUM(CASE WHEN started_at IS NOT NULL THEN 1 ELSE 0 END) as started')
            ->groupBy('exam_schedule_id')->get()->keyBy('exam_schedule_id');

        return view('livewire.admin.dashboard', [
            'stats' => [
                'students' => Student::query()->where('is_active', true)->count(),
                'today' => $today->count(),
                'running' => $today->where('status', 'open')->count(),
                'online' => SystemHealth::onlineStudents(),
                'violations' => $violationsToday,
            ],
            'today' => $today,
            'counts' => $counts,
            'hourLabels' => array_map(fn ($h) => sprintf('%02d', $h), $hours),
            'hourData' => array_map(fn ($h) => (int) ($perHour[$h] ?? 0), $hours),
            'scores' => $scores,
            'attention' => $attention,
            'alerts' => $alerts,
            'cronOk' => SystemHealth::cronHealthy(),
            'cronLast' => SystemHealth::cronLastRun(),
            'apiMs' => SystemHealth::avgApiMs(),
            'queue' => SystemHealth::queueSize(),
        ]);
    }
}
