<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\PicksSchedule;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\IntegrityFlag;
use App\Models\Student;
use App\Services\IntegrityAnalyzer;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Tinjauan analitik integritas — "perlu ditinjau", bukan vonis otomatis. */
#[Title('Analitik Integritas')]
class Integrity extends Component
{
    use PicksSchedule, WithToast;

    public array $verdicts = [];
    public array $notes = [];

    public function analyze(IntegrityAnalyzer $analyzer): void
    {
        $s = $this->pickedSchedule();
        abort_unless($s, 404);
        $r = $analyzer->analyze($s);
        ActivityLog::record('integrity_analyzed', $s->title() . ": {$r['total']} temuan", $s);
        $this->toast('success', 'Analisis selesai', "{$r['total']} temuan perlu ditinjau.");
    }

    public function review(int $id): void
    {
        $flag = IntegrityFlag::query()->where('exam_schedule_id', $this->scheduleId)->findOrFail($id);
        $v = $this->verdicts[$id] ?? '';
        if (! array_key_exists($v, IntegrityFlag::VERDICTS)) {
            $this->addError("verdicts.{$id}", 'Pilih hasil tinjauan.');

            return;
        }
        $flag->forceFill(['verdict' => $v, 'note' => mb_substr((string) ($this->notes[$id] ?? ''), 0, 500), 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
        ActivityLog::record('integrity_reviewed', IntegrityFlag::KINDS[$flag->kind] . ' → ' . IntegrityFlag::VERDICTS[$v], $flag);
        $this->toast('success', 'Tinjauan tersimpan');
    }

    public function render()
    {
        $s = $this->pickedSchedule();
        $flags = $s ? IntegrityFlag::query()->where('exam_schedule_id', $s->id)->orderByRaw('reviewed_at IS NOT NULL')->orderByDesc('score')->get() : collect();
        $students = Student::query()->whereIn('id', $flags->pluck('student_ids')->flatten()->unique())->get()->keyBy('id');
        foreach ($flags as $f) {
            $this->verdicts[$f->id] = $this->verdicts[$f->id] ?? (string) $f->verdict;
            $this->notes[$f->id] = $this->notes[$f->id] ?? (string) $f->note;
        }

        return view('livewire.admin.integrity', ['options' => $this->scheduleOptions(), 'schedule' => $s, 'flags' => $flags, 'students' => $students]);
    }
}
