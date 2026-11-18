<?php

namespace App\Livewire\Admin;

use App\Jobs\ExportReport;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\ReportExport;
use App\Services\Reports\RecapService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Rekap pelanggaran per siswa/ujian/jenis + grafik per jam (Spesifikasi 5.11). */
#[Title('Pelanggaran')]
class Violations extends Component
{
    use WithToast;

    #[Url(as: 'jadwal')]
    public ?int $scheduleId = null;
    #[Url(as: 'tanggal')]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->format('Y-m-d');
    }

    public function export(): void
    {
        $exp = ReportExport::query()->create(['user_id' => auth()->id(), 'type' => 'violations_xlsx', 'params' => ['schedule' => $this->scheduleId, 'date' => $this->date]]);
        ActivityLog::record('export', $exp->label(), $exp);
        ExportReport::dispatch($exp->id);
        $this->toast('info', 'Ekspor diproses di antrean', 'Unduh dari menu Unduhan.');
    }

    public function render(RecapService $recap)
    {
        $user = auth()->user();
        $schedules = ExamSchedule::query()->visibleTo($user)->with(['exam', 'classroom'])->whereDate('date', $this->date)->orderBy('opens_at')->get();
        $schedule = $this->scheduleId ? $schedules->firstWhere('id', $this->scheduleId) : null;
        $ids = $schedule ? [$schedule->id] : $schedules->pluck('id')->all();

        $attempts = ExamAttempt::query()->with(['student.classroom', 'schedule.exam'])->whereIn('exam_schedule_id', $ids ?: [0])->get()->keyBy('id');
        $data = ['byType' => [], 'byHour' => [], 'byAttempt' => collect(), 'total' => 0];
        if ($schedule) {
            $data = $recap->violations($schedule);
        } elseif ($ids) {
            $parts = array_map(fn ($sid) => $recap->violations($schedules->firstWhere('id', $sid)), $ids);
            $byType = [];
            $byHour = [];
            $byAttempt = collect();
            foreach ($parts as $p) {
                foreach ($p['byType'] as $t) {
                    $byType[$t['type']] = isset($byType[$t['type']]) ? array_merge($t, ['total' => $byType[$t['type']]['total'] + $t['total'], 'warn' => $byType[$t['type']]['warn'] + $t['warn'], 'lock' => $byType[$t['type']]['lock'] + $t['lock']]) : $t;
                }
                foreach ($p['byHour'] as $h => $n) {
                    $byHour[$h] = ($byHour[$h] ?? 0) + $n;
                }
                $byAttempt = $byAttempt->union($p['byAttempt']);
                $data['total'] += $p['total'];
            }
            $data['byType'] = collect($byType)->sortByDesc('total')->values()->all();
            ksort($byHour);
            $data['byHour'] = $byHour;
            $data['byAttempt'] = $byAttempt;
        }
        $hours = range(6, 17);

        return view('livewire.admin.violations', [
            'schedules' => $schedules,
            'data' => $data,
            'hourLabels' => array_map(fn ($h) => sprintf('%02d', $h), $hours),
            'hourData' => array_map(fn ($h) => (int) ($data['byHour'][sprintf('%02d', $h)] ?? 0), $hours),
            'students' => collect($data['byAttempt'])->map(fn ($v, $aid) => $v + ['attempt' => $attempts[$aid] ?? null])->filter(fn ($r) => $r['attempt'])->sortByDesc('total')->values(),
        ]);
    }
}
