<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman Monitoring Live (data dari polling /api/monitor/{schedule}). */
class MonitoringController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $schedules = ExamSchedule::query()->visibleTo($request->user())->with(['exam.subject', 'classroom'])
            ->where(fn ($q) => $q->whereIn('status', ['open', 'paused'])->orWhereDate('date', today()))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'paused' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END")->orderBy('opens_at')->get();
        if ($schedules->count() === 1) {
            return redirect()->route('admin.monitoring.show', $schedules->first());
        }

        return view('admin.monitoring-index', ['schedules' => $schedules]);
    }

    public function show(Request $request, ExamSchedule $schedule): View
    {
        abort_unless(ExamSchedule::query()->visibleTo($request->user())->whereKey($schedule->id)->exists(), 403);
        $schedule->load('exam.subject', 'classroom', 'supervisors');

        return view('admin.monitoring', ['schedule' => $schedule]);
    }
}
