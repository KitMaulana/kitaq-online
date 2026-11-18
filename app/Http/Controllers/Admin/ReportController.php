<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExamSchedule;
use App\Models\ReportExport;
use App\Models\UnlockLog;
use App\Services\Reports\RecapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Berita acara + daftar hadir (PDF), daftar unduhan ekspor. */
class ReportController extends Controller
{
    private function visible(Request $request, ExamSchedule $schedule): ExamSchedule
    {
        abort_unless(ExamSchedule::query()->visibleTo($request->user())->whereKey($schedule->id)->exists(), 403);

        return $schedule->load('exam.subject', 'classroom', 'room', 'session', 'supervisors');
    }

    public function minutesIndex(Request $request): View
    {
        $schedules = ExamSchedule::query()->visibleTo($request->user())->with(['exam', 'classroom'])->orderByDesc('opens_at')->paginate(25);

        return view('admin.minutes-index', ['schedules' => $schedules]);
    }

    public function minutesShow(Request $request, ExamSchedule $schedule): View
    {
        $this->visible($request, $schedule);

        return view('admin.minutes', $this->minutesData($schedule) + ['schedule' => $schedule]);
    }

    public function minutesPdf(Request $request, ExamSchedule $schedule)
    {
        $this->visible($request, $schedule);
        $data = $this->minutesData($schedule) + [
            'schedule' => $schedule,
            'notes' => mb_substr((string) $request->query('catatan', ''), 0, 1000),
            'supervisor' => mb_substr((string) $request->query('pengawas', $request->user()->name), 0, 120),
        ];
        ActivityLog::record('print_minutes', 'Mencetak berita acara ' . $schedule->title(), $schedule);

        return Pdf::loadView('pdf.minutes', $data)->setPaper('a4')->stream('berita-acara-' . $schedule->id . '.pdf');
    }

    private function minutesData(ExamSchedule $schedule): array
    {
        $rows = app(RecapService::class)->recap($schedule)->sortBy('name')->values();
        $unlocks = UnlockLog::query()->with(['user:id,name'])->whereIn('exam_attempt_id', $rows->pluck('attempt_id')->filter())->orderBy('created_at')->get();

        return [
            'rows' => $rows,
            'present' => $rows->where('present', true)->count(),
            'absent' => $rows->where('present', false)->count(),
            'locked' => $rows->filter(fn ($r) => $r['warnings'] > 0 || $r['violations'] > 0)->count(),
            'unlocks' => $unlocks,
            'names' => $rows->pluck('name', 'attempt_id'),
        ];
    }

    public function exports(Request $request): View
    {
        $q = ReportExport::query()->latest('id');
        if (! $request->user()->isSuperAdmin()) {
            $q->where('user_id', $request->user()->id);
        }

        return view('admin.exports', ['exports' => $q->paginate(25)]);
    }

    public function download(Request $request, ReportExport $export)
    {
        abort_unless($export->user_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_unless($export->status === 'done' && $export->path && Storage::disk('local')->exists($export->path), 404);
        ActivityLog::record('export_downloaded', $export->label(), $export);

        return Storage::disk('local')->download($export->path, basename($export->path));
    }
}
