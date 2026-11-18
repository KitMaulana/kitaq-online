<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\ReportExport;
use App\Models\Student;
use App\Services\Auth\StudentCredential;
use App\Jobs\ExportReport;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Siswa')]
class Students extends Component
{
    use WithBulkActions, WithDataTable, WithFileUploads, WithToast;

    protected array $sortable = ['name', 'nisn', 'classroom_id', 'last_login_at', 'id'];

    #[Url(as: 'create')]
    public ?int $create = null;

    public bool $showForm = false;
    public bool $showDetail = false;
    public bool $confirmDelete = false;
    public bool $showMove = false;
    public ?int $editingId = null;
    public ?int $detailId = null;
    public ?int $deleteId = null;
    public ?int $moveTo = null;

    public array $form = [];
    public $photo;

    public function mount(): void
    {
        $this->sortField = 'name';
        $this->sortDirection = 'asc';
        $this->filters = ['classroom' => '', 'status' => ''];
        $this->resetForm();
        if ($this->create) {
            $this->openCreate();
        }
    }

    private function resetForm(): void
    {
        $this->form = [
            'nisn' => '', 'nis' => '', 'name' => '', 'gender' => 'L', 'birth_place' => '', 'birth_date' => '',
            'classroom_id' => '', 'is_active' => true,
            'extra_minutes' => 0, 'large_font' => false, 'disable_canvas' => false,
        ];
        $this->photo = null;
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = Student::query()->findOrFail($id);
        $this->resetForm();
        $this->editingId = $s->id;
        $this->form = [
            'nisn' => $s->nisn, 'nis' => (string) $s->nis, 'name' => $s->name, 'gender' => $s->gender ?? 'L',
            'birth_place' => (string) $s->birth_place, 'birth_date' => $s->birth_date?->format('Y-m-d'),
            'classroom_id' => (string) $s->classroom_id, 'is_active' => $s->is_active,
            'extra_minutes' => (int) $s->accommodation('extra_minutes', 0),
            'large_font' => (bool) $s->accommodation('large_font', false),
            'disable_canvas' => (bool) $s->accommodation('disable_canvas', false),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form['nisn'] = StudentCredential::normalizeNisn((string) $this->form['nisn']) ?? $this->form['nisn'];
        $this->validate([
            'form.nisn' => ['required', 'digits:10', Rule::unique('students', 'nisn')->ignore($this->editingId)],
            'form.nis' => ['nullable', 'string', 'max:30'],
            'form.name' => ['required', 'string', 'max:120'],
            'form.gender' => ['required', 'in:L,P'],
            'form.birth_place' => ['nullable', 'string', 'max:80'],
            'form.birth_date' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'form.classroom_id' => ['required', 'exists:classrooms,id'],
            'form.extra_minutes' => ['integer', 'min:0', 'max:240'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ], [], ['form.nisn' => 'NISN', 'form.name' => 'nama', 'form.birth_date' => 'tanggal lahir', 'form.classroom_id' => 'kelas', 'form.gender' => 'jenis kelamin']);

        $student = $this->editingId ? Student::query()->findOrFail($this->editingId) : new Student();
        $birthChanged = ! $student->exists || $student->birth_date?->format('Y-m-d') !== $this->form['birth_date'];
        $student->fill([
            'nisn' => $this->form['nisn'],
            'nis' => $this->form['nis'] ?: null,
            'name' => trim($this->form['name']),
            'gender' => $this->form['gender'],
            'birth_place' => $this->form['birth_place'] ?: null,
            'birth_date' => $this->form['birth_date'],
            'classroom_id' => (int) $this->form['classroom_id'],
            'is_active' => (bool) $this->form['is_active'],
            'accommodations' => [
                'extra_minutes' => (int) $this->form['extra_minutes'],
                'large_font' => (bool) $this->form['large_font'],
                'disable_canvas' => (bool) $this->form['disable_canvas'],
            ],
        ]);
        if ($birthChanged) {
            $student->password = StudentCredential::passwordFromBirthDate(new \DateTimeImmutable($this->form['birth_date']));
        }
        if ($this->photo) {
            $student->photo = $this->photo->store('photos', 'local');
        }
        $student->save();
        ActivityLog::record($this->editingId ? 'student_updated' : 'student_created', $student->name, $student);
        $this->showForm = false;
        $this->toast('success', $this->editingId ? 'Data siswa diperbarui' : 'Siswa ditambahkan', $birthChanged ? 'Password = tanggal lahir (DDMMYYYY).' : '');
    }

    public function show(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        $ids = $this->deleteId ? [$this->deleteId] : $this->selectedIds();
        $n = Student::query()->whereIn('id', $ids)->count();
        Student::query()->whereIn('id', $ids)->delete();
        ActivityLog::record('student_deleted', "Menghapus {$n} siswa", null, ['ids' => $ids]);
        $this->confirmDelete = false;
        $this->deleteId = null;
        $this->clearSelection();
        $this->toast('success', "{$n} siswa dihapus");
    }

    public function resetPassword(?int $id = null): void
    {
        $ids = $id ? [$id] : $this->selectedIds();
        foreach (Student::query()->whereIn('id', $ids)->get() as $s) {
            $s->resetPasswordToBirthDate();
            $s->must_change_password = false;
            $s->save();
        }
        ActivityLog::record('student_password_reset', 'Reset password ke tanggal lahir: ' . count($ids) . ' siswa', null, ['ids' => $ids]);
        $this->toast('success', 'Password direset', 'Password kembali ke tanggal lahir (DDMMYYYY).');
    }

    public function unfreeze(int $id): void
    {
        Student::query()->whereKey($id)->update(['locked_until' => null, 'failed_logins' => 0]);
        ActivityLog::record('student_unfrozen', 'Membuka pembekuan akun siswa', Student::query()->find($id));
        $this->toast('success', 'Pembekuan akun dibuka');
    }

    public function setActive(bool $active): void
    {
        Student::query()->whereIn('id', $this->selectedIds())->update(['is_active' => $active]);
        $this->toast('success', count($this->selected) . ' siswa ' . ($active ? 'diaktifkan' : 'dinonaktifkan'));
        $this->clearSelection();
    }

    public function moveClass(): void
    {
        $this->validate(['moveTo' => ['required', 'exists:classrooms,id']], [], ['moveTo' => 'kelas tujuan']);
        Student::query()->whereIn('id', $this->selectedIds())->update(['classroom_id' => $this->moveTo]);
        ActivityLog::record('student_moved', 'Pindah kelas massal', Classroom::query()->find($this->moveTo), ['ids' => $this->selectedIds()]);
        $this->toast('success', count($this->selected) . ' siswa dipindah kelas');
        $this->showMove = false;
        $this->clearSelection();
    }

    /** Ekspor data siswa (hanya Super Admin/Operator, dicatat di log — KEAMANAN.md). */
    public function export(): void
    {
        $exp = ReportExport::query()->create(['user_id' => auth()->id(), 'type' => 'students_xlsx', 'params' => ['classroom' => $this->filters['classroom'] ?? null]]);
        ActivityLog::record('export', 'Mengekspor data siswa', $exp);
        ExportReport::dispatch($exp->id);
        $this->toast('info', 'Ekspor diproses', 'File muncul di menu Unduhan setelah cron memproses antrean.');
    }

    public function render()
    {
        $query = Student::query()->with('classroom');
        if ($this->filters['classroom'] ?? '') {
            $query->where('classroom_id', $this->filters['classroom']);
        }
        match ($this->filters['status'] ?? '') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'frozen' => $query->where('locked_until', '>', now()),
            default => null,
        };
        $students = $this->applyTable($query, ['name', 'nisn', 'nis'])->paginate($this->perPage);

        return view('livewire.admin.students', [
            'students' => $students,
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id'),
            'detail' => $this->detailId ? Student::query()->with(['classroom', 'attempts.schedule.exam'])->find($this->detailId) : null,
            'logins' => $this->detailId ? ActivityLog::query()->where('student_id', $this->detailId)->where('action', 'student_login')->latest('id')->limit(5)->get() : collect(),
        ]);
    }
}
