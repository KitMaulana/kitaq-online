<?php

namespace App\Livewire\Admin;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Model;

class AcademicYears extends CrudPage
{
    protected array $sortable = ['name', 'semester'];

    protected function model(): string
    {
        return AcademicYear::class;
    }

    protected function heading(): array
    {
        return ['Tahun Ajaran', 'Tahun ajaran & semester aktif.', 'Tahun ajaran'];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Tahun ajaran', 'rules' => ['required', 'regex:/^\d{4}\/\d{4}$/'], 'hint' => 'Contoh: 2026/2027'],
            'semester' => ['label' => 'Semester', 'type' => 'select', 'options' => ['ganjil' => 'Ganjil', 'genap' => 'Genap'], 'rules' => ['required', 'in:ganjil,genap']],
            'is_active' => ['label' => 'Aktif', 'type' => 'switch', 'rules' => ['boolean'], 'hint' => 'Hanya satu yang aktif'],
        ];
    }

    protected function afterSave(Model $model): void
    {
        if ($model->is_active) {
            AcademicYear::query()->whereKeyNot($model->id)->update(['is_active' => false]);
        }
    }

    protected function columns(): array
    {
        return ['name' => 'Tahun ajaran', 'semester' => 'Semester', 'is_active' => 'Status'];
    }

    public function cell(Model $row, string $key): string
    {
        if ($key === 'is_active') {
            return $row->is_active ? '<span class="badge tone-success"><i class="d"></i>Aktif</span>' : '<span class="muted">-</span>';
        }

        return $key === 'semester' ? e(ucfirst($row->semester)) : parent::cell($row, $key);
    }
}
