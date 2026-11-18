<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Exam;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Support\ExamSettings;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Form ujian + pengaturan JSON `exams.settings` (Spesifikasi 5.6). */
#[Title('Pengaturan Ujian')]
class ExamForm extends Component
{
    use WithToast;

    public ?Exam $exam = null;
    public array $form = [];
    public array $settings = [];
    public string $tab = 'umum';

    public function mount(?Exam $exam = null): void
    {
        $user = auth()->user();
        if ($exam && $exam->exists) {
            abort_unless(Exam::query()->visibleTo($user)->whereKey($exam->id)->exists(), 403);
            $this->exam = $exam;
            $this->form = $exam->only(['name', 'type', 'subject_id', 'question_bank_id', 'question_count', 'duration_minutes', 'kkm']);
            $this->settings = $exam->resolvedSettings();
        } else {
            $this->form = ['name' => '', 'type' => 'UH', 'subject_id' => '', 'question_bank_id' => '', 'question_count' => 0, 'duration_minutes' => 60, 'kkm' => 75];
            $this->settings = ExamSettings::defaults();
        }
        $this->settings['ip_list_text'] = implode("\n", (array) ($this->settings['ip_list'] ?? []));
        $this->settings['packages_text'] = implode(',', (array) $this->settings['packages']);
        foreach (ExamSettings::EVENT_LABELS as $type => $_) {
            $this->settings['overrides'][$type] = $this->settings['overrides'][$type] ?? '';
        }
    }

    public function updatedFormSubjectId(): void
    {
        $this->form['question_bank_id'] = '';
    }

    private function subjects()
    {
        $user = auth()->user();
        $q = Subject::query()->orderBy('name');
        if (! $user->isAdminLike()) {
            $q->whereIn('id', $user->subjects()->pluck('subjects.id'));
        }

        return $q->pluck('name', 'id');
    }

    private function banks()
    {
        return QuestionBank::query()->visibleTo(auth()->user())->when($this->form['subject_id'], fn ($q, $s) => $q->where('subject_id', $s))
            ->withCount('questions')->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => "{$b->name} ({$b->questions_count} soal)"]);
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.type' => ['required', 'in:' . implode(',', array_keys(Exam::TYPES))],
            'form.subject_id' => ['required', 'in:' . $this->subjects()->keys()->join(',')],
            'form.question_bank_id' => ['required', 'in:' . $this->banks()->keys()->join(',')],
            'form.question_count' => ['required', 'integer', 'min:0', 'max:500'],
            'form.duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'form.kkm' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.show_score' => ['required', 'in:immediately,after_close,never'],
            'settings.min_minutes_before_submit' => ['required', 'integer', 'min:0', 'max:600'],
            'settings.preset' => ['required', 'in:longgar,standar,ketat'],
            'settings.max_warnings' => ['required', 'integer', 'min:0', 'max:10'],
            'settings.browsers' => ['required', 'array', 'min:1'],
            'settings.physical_keyboard_mobile' => ['required', 'in:block,allow'],
            'settings.physical_keyboard_desktop' => ['required', 'in:block,allow'],
            'settings.offline_mode' => ['required', 'in:log,lock'],
            'settings.offline_lock_after_sec' => ['required', 'integer', 'min:60', 'max:3600'],
            'settings.timer_when_locked' => ['required', 'in:run,pause'],
        ], [], [
            'form.name' => 'nama ujian', 'form.subject_id' => 'mapel', 'form.question_bank_id' => 'bank soal', 'form.duration_minutes' => 'durasi',
            'settings.browsers' => 'browser yang diizinkan',
        ]);

        $s = $this->settings;
        $s['overrides'] = array_filter((array) $s['overrides'], fn ($v) => in_array($v, ['ignore', 'log', 'warn', 'lock'], true));
        $s['packages'] = array_values(array_intersect(array_map('trim', explode(',', strtoupper((string) ($s['packages_text'] ?? '')))), ['A', 'B', 'C', 'D', 'E']));
        unset($s['ip_list_text'], $s['packages_text'], $s['ip_list']);
        $resolved = ExamSettings::resolve($s);

        $exam = $this->exam ?? new Exam(['user_id' => auth()->id()]);
        $exam->fill($this->form + ['settings' => $resolved]);
        $exam->settings = $resolved;
        $exam->save();
        ActivityLog::record($this->exam ? 'exam_updated' : 'exam_created', $exam->name, $exam, ['preset' => $resolved['preset']]);
        $this->toast('success', 'Ujian tersimpan');
        if (! $this->exam) {
            $this->redirectRoute('admin.exams.edit', $exam);
        }
        $this->exam = $exam;
    }

    public function render()
    {
        $banks = $this->banks();
        $summary = ExamSettings::presetSummary($this->settings['preset'] ?? 'standar', array_filter((array) ($this->settings['overrides'] ?? [])));

        return view('livewire.admin.exam-form', [
            'subjects' => $this->subjects(),
            'banks' => $banks,
            'summary' => $summary,
        ]);
    }
}
