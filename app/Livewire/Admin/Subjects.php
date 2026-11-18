<?php

namespace App\Livewire\Admin;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subjects extends CrudPage
{
    protected array $sortable = ['code', 'name'];

    protected function model(): string
    {
        return Subject::class;
    }

    protected function heading(): array
    {
        return ['Mata Pelajaran', 'Mapel dan guru pengampu. Guru hanya mengelola bank soal mapel yang diampu.', 'Mapel'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function query(): Builder
    {
        return Subject::query()->with('teachers');
    }

    protected function fields(): array
    {
        return [
            'code' => ['label' => 'Kode', 'rules' => ['required', 'string', 'max:20', 'unique:subjects,code,{id}']],
            'name' => ['label' => 'Nama mapel', 'rules' => ['required', 'string', 'max:100']],
            'teachers' => ['label' => 'Guru pengampu', 'type' => 'multiselect', 'full' => true, 'options' => User::query()->role('guru')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['array'], 'hint' => 'Centang guru yang mengajar mapel ini.'],
        ];
    }

    protected function fillFrom(Model $m): array
    {
        $data = parent::fillFrom($m);
        $data['teachers'] = $m->teachers()->pluck('users.id')->map(fn ($i) => (string) $i)->all();

        return $data;
    }

    protected function afterSave(Model $model): void
    {
        $model->teachers()->sync(array_map('intval', (array) ($this->form['teachers'] ?? [])));
    }

    protected function columns(): array
    {
        return ['code' => 'Kode', 'name' => 'Mapel', 'teachers' => 'Guru pengampu'];
    }

    public function cell(Model $row, string $key): string
    {
        if ($key === 'teachers') {
            return e($row->teachers->pluck('name')->join(', ') ?: '-');
        }

        return parent::cell($row, $key);
    }
}
