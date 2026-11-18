<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Dashboard siswa: ujian hari ini (tombol Mulai aktif hanya pada jam ujian), mendatang, riwayat & nilai. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = $request->user('student')->load('classroom');

        $today = ExamSchedule::query()->forStudent($student)->with(['exam.subject'])
            ->whereDate('date', today())->where('status', '!=', 'closed')->orderBy('opens_at')->get();
        $upcoming = ExamSchedule::query()->forStudent($student)->with(['exam.subject'])
            ->whereDate('date', '>', today())->orderBy('date')->orderBy('opens_at')->limit(10)->get();
        $attempts = ExamAttempt::query()->where('student_id', $student->id)
            ->whereIn('exam_schedule_id', $today->pluck('id'))->get()->keyBy('exam_schedule_id');
        $history = ExamAttempt::query()->with('schedule.exam.subject')->where('student_id', $student->id)
            ->whereIn('status', ExamAttempt::DONE_STATUSES)->latest('submitted_at')->limit(20)->get();

        return view('student.dashboard', compact('student', 'today', 'upcoming', 'attempts', 'history'));
    }

    /** Nilai boleh dilihat? (pengaturan ujian: langsung / setelah ditutup / tidak) */
    public static function scoreVisible(ExamAttempt $a): bool
    {
        if (! $a->isDone() || $a->needs_grading) {
            return false;
        }
        $mode = $a->schedule->exam->setting('show_score', 'after_close');

        return $mode === 'immediately' || ($mode === 'after_close' && ($a->schedule->status === 'closed' || $a->schedule->closes_at->isPast()));
    }
}
