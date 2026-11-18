<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Exam;
use App\Models\ExamSchedule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Ujian')]
class Exams extends Component
{
    use WithDataTable, WithToast;

    protected array $sortable = ['name', 'type', 'duration_minutes', 'updated_at'];

    #[Url(as: 'create')]
    public ?int $create = null;

    public bool $confirmDelete = false;
    public ?int $deleteId = null;

    public function mount(): void
    {
        if ($this->create) {
            $this->redirectRoute('admin.exams.create');
        }
        $this->sortField = 'updated_at';
    }

    public function duplicate(int $id): void
    {
        $e = Exam::query()->visibleTo(auth()->user())->findOrFail($id);
        $copy = $e->replicate();
        $copy->name = $e->name . ' (salinan)';
        $copy->user_id = auth()->id();
        $copy->save();
        $this->toast('success', 'Ujian digandakan');
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        $e = Exam::query()->visibleTo(auth()->user())->findOrFail($this->deleteId);
        if (ExamSchedule::query()->where('exam_id', $e->id)->whereHas('attempts', fn ($q) => $q->whereNotNull('started_at'))->exists()) {
            $this->toast('error', 'Tidak dapat dihapus', 'Ujian sudah dikerjakan siswa. Arsipkan saja.');
        } else {
            ActivityLog::record('exam_deleted', $e->name, $e);
            $e->delete();
            $this->toast('success', 'Ujian dihapus');
        }
        $this->confirmDelete = false;
    }

    public function render()
    {
        $q = Exam::query()->visibleTo(auth()->user())->with(['subject', 'bank'])->withCount('schedules');

        return view('livewire.admin.exams', ['exams' => $this->applyTable($q, ['name'])->paginate($this->perPage)]);
    }
}
