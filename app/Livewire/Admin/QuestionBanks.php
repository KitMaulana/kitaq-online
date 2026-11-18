<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\QuestionBank;
use App\Models\Subject;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bank Soal')]
class QuestionBanks extends Component
{
    use WithDataTable, WithToast;

    protected array $sortable = ['name', 'subject_id', 'questions_count', 'updated_at'];

    public bool $showForm = false;
    public bool $confirmDelete = false;
    public ?int $editingId = null;
    public ?int $deleteId = null;
    public array $form = ['name' => '', 'subject_id' => '', 'grade' => '', 'description' => ''];

    public function mount(): void
    {
        $this->sortField = 'updated_at';
        $this->filters = ['subject' => ''];
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

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'subject_id' => '', 'grade' => '', 'description' => ''];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $b = QuestionBank::query()->visibleTo(auth()->user())->findOrFail($id);
        $this->editingId = $id;
        $this->form = ['name' => $b->name, 'subject_id' => (string) $b->subject_id, 'grade' => (string) $b->grade, 'description' => (string) $b->description];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.subject_id' => ['required', 'in:' . $this->subjects()->keys()->join(',')],
            'form.grade' => ['nullable', 'string', 'max:10'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ], [], ['form.name' => 'nama bank soal', 'form.subject_id' => 'mata pelajaran']);
        $bank = $this->editingId ? QuestionBank::query()->visibleTo(auth()->user())->findOrFail($this->editingId) : new QuestionBank(['user_id' => auth()->id()]);
        $bank->fill(['name' => $this->form['name'], 'subject_id' => (int) $this->form['subject_id'], 'grade' => $this->form['grade'] ?: null, 'description' => $this->form['description'] ?: null])->save();
        ActivityLog::record($this->editingId ? 'bank_updated' : 'bank_created', $bank->name, $bank);
        $this->showForm = false;
        $this->toast('success', 'Bank soal tersimpan');
        if (! $this->editingId) {
            $this->redirectRoute('admin.banks.show', $bank);
        }
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        $bank = QuestionBank::query()->visibleTo(auth()->user())->findOrFail($this->deleteId);
        if ($bank->questions()->exists() && \App\Models\Exam::query()->where('question_bank_id', $bank->id)->exists()) {
            $this->toast('error', 'Tidak dapat dihapus', 'Bank soal dipakai oleh ujian.');
        } else {
            ActivityLog::record('bank_deleted', $bank->name, $bank);
            $bank->delete();
            $this->toast('success', 'Bank soal dihapus');
        }
        $this->confirmDelete = false;
    }

    public function render()
    {
        $q = QuestionBank::query()->visibleTo(auth()->user())->with(['subject', 'owner'])->withCount('questions');
        if ($this->filters['subject'] ?? '') {
            $q->where('subject_id', $this->filters['subject']);
        }

        return view('livewire.admin.question-banks', [
            'banks' => $this->applyTable($q, ['name'])->paginate($this->perPage),
            'subjects' => $this->subjects(),
        ]);
    }
}
