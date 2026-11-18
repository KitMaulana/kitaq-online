<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\PicksSchedule;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\ScoringService;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Koreksi uraian: guru memberi skor 0..bobot per jawaban; nilai siswa dihitung ulang (Spesifikasi 7.4). */
#[Title('Koreksi Uraian')]
class Grading extends Component
{
    use PicksSchedule, WithToast;

    public bool $onlyPending = true;
    public array $scores = [];

    public function grade(int $answerId): void
    {
        $this->resetErrorBag("scores.{$answerId}");
        $ans = AttemptAnswer::query()->with(['question', 'attempt.schedule'])->findOrFail($answerId);
        abort_unless($ans->question->type === 'uraian' && $ans->attempt->exam_schedule_id === $this->scheduleId, 404);
        $max = (float) $ans->question->weight;
        $val = $this->scores[$answerId] ?? null;
        if (! is_numeric($val) || $val < 0 || $val > $max) {
            $this->addError("scores.{$answerId}", "Skor 0 sampai {$max}.");

            return;
        }
        $old = $ans->score;
        $ans->forceFill(['score' => round((float) $val, 2), 'is_correct' => (float) $val >= $max, 'graded_by' => auth()->id()])->save();
        $attempt = $ans->attempt;
        app(ScoringService::class)->score($attempt);
        ActivityLog::record('essay_graded', "Koreksi uraian {$attempt->student->name}: {$old} → {$val}", $attempt);
        $this->toast('success', 'Skor tersimpan', 'Nilai akhir siswa dihitung ulang.');
    }

    public function render()
    {
        $s = $this->pickedSchedule();
        $answers = collect();
        $stats = ['pending' => 0, 'done' => 0];
        if ($s) {
            $attemptIds = ExamAttempt::query()->where('exam_schedule_id', $s->id)->whereIn('status', ExamAttempt::DONE_STATUSES)->pluck('id');
            $essayIds = Question::query()->where('question_bank_id', $s->exam->question_bank_id)->where('type', 'uraian')->pluck('id');
            $base = AttemptAnswer::query()->whereIn('exam_attempt_id', $attemptIds)->whereIn('question_id', $essayIds)->whereNotNull('answer');
            $stats = ['pending' => (clone $base)->whereNull('score')->count(), 'done' => (clone $base)->whereNotNull('score')->count()];
            $answers = $base->when($this->onlyPending, fn ($q) => $q->whereNull('score'))
                ->with(['question', 'attempt.student'])->orderBy('question_id')->limit(60)->get();
            foreach ($answers as $a) {
                $this->scores[$a->id] = $this->scores[$a->id] ?? ($a->score !== null ? (string) $a->score : '');
            }
        }

        return view('livewire.admin.grading', ['options' => $this->scheduleOptions(), 'schedule' => $s, 'answers' => $answers, 'stats' => $stats]);
    }
}
