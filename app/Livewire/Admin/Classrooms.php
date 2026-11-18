<?php

namespace App\Livewire\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Classrooms extends CrudPage
{
    protected array $sortable = ['name', 'grade', 'students_count'];

    protected function model(): string
    {
        return Classroom::class;
    }

    protected function heading(): array
    {
        return ['Kelas', 'Kelas/rombel. Jadwal ujian dibuat per kelas.', 'Kelas'];
    }

    protected function query(): Builder
    {
        return Classroom::query()->with(['academicYear', 'homeroom'])->withCount('students');
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nama kelas', 'rules' => ['required', 'string', 'max:60', 'unique:classrooms,name,{id}'], 'hint' => 'Contoh: XII IPS 1'],
            'grade' => ['label' => 'Tingkat', 'rules' => ['nullable', 'string', 'max:10'], 'hint' => 'X / XI / XII'],
            'academic_year_id' => ['label' => 'Tahun ajaran', 'type' => 'select', 'options' => AcademicYear::query()->orderByDesc('id')->get()->mapWithKeys(fn ($y) => [$y->id => $y->label()])->all(), 'rules' => ['nullable', 'exists:academic_years,id']],
            'homeroom_user_id' => ['label' => 'Wali kelas', 'type' => 'select', 'options' => User::query()->role(['guru', 'operator'])->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:users,id']],
        ];
    }

    protected function columns(): array
    {
        return ['name' => 'Kelas', 'grade' => 'Tingkat', 'students_count' => 'Siswa', 'homeroom.name' => 'Wali kelas', 'academicYear.name' => 'Tahun ajaran'];
    }

    public function cell(Model $row, string $key): string
    {
        if ($key === 'students_count') {
            return '<a href="' . route('admin.students', ['filters' => ['classroom' => $row->id]]) . '">' . (int) $row->students_count . ' siswa</a>';
        }

        return parent::cell($row, $key);
    }
}
