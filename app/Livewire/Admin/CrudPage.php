<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Basis halaman daftar + form modal untuk data master sederhana (DESIGN_SYSTEM 5.1 & 5.2).
 * Turunan mendefinisikan model(), fields(), columns(); view generik: livewire.admin.crud.
 */
abstract class CrudPage extends Component
{
    use WithBulkActions, WithDataTable, WithToast;

    public bool $showForm = false;
    public bool $confirmDelete = false;
    public ?int $editingId = null;
    public ?int $deleteId = null;
    public array $form = [];

    protected array $sortable = ['id'];

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** @return array<string,array{label:string,type?:string,options?:array,rules:array,hint?:string,full?:bool}> */
    abstract protected function fields(): array;

    /** @return array<string,string> kolom => judul */
    abstract protected function columns(): array;

    abstract protected function heading(): array; // [judul, subjudul, nama tunggal]

    /** @return list<string> */
    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function query(): Builder
    {
        return ($this->model())::query();
    }

    public function cell(Model $row, string $key): string
    {
        $v = data_get($row, $key);

        return is_bool($v) ? ($v ? 'Ya' : 'Tidak') : e((string) ($v ?? '-'));
    }

    protected function fillFrom(Model $m): array
    {
        $out = [];
        foreach ($this->fields() as $k => $f) {
            $out[$k] = ($f['type'] ?? 'text') === 'switch' ? (bool) $m->{$k} : (string) ($m->{$k} ?? '');
        }

        return $out;
    }

    protected function blank(): array
    {
        $out = [];
        foreach ($this->fields() as $k => $f) {
            $out[$k] = $f['default'] ?? (($f['type'] ?? 'text') === 'switch' ? false : (($f['type'] ?? '') === 'multiselect' ? [] : ''));
        }

        return $out;
    }

    protected function beforeSave(array $data, ?Model $model): array
    {
        return $data;
    }

    protected function afterSave(Model $model): void
    {
    }

    public function mount(): void
    {
        $this->form = $this->blank();
        $this->sortField = $this->sortable[0] ?? 'id';
        $this->sortDirection = 'asc';
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->blank();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $m = ($this->model())::query()->findOrFail($id);
        $this->editingId = $id;
        $this->form = $this->fillFrom($m);
        $this->showForm = true;
    }

    public function save(): void
    {
        $rules = [];
        $attrs = [];
        foreach ($this->fields() as $k => $f) {
            $rules['form.' . $k] = array_map(fn ($r) => is_string($r) ? str_replace('{id}', (string) ($this->editingId ?? 'NULL'), $r) : $r, $f['rules']);
            $attrs['form.' . $k] = strtolower($f['label']);
        }
        $this->validate($rules, [], $attrs);
        $model = $this->editingId ? ($this->model())::query()->findOrFail($this->editingId) : new ($this->model())();
        $data = [];
        foreach ($this->fields() as $k => $f) {
            if (($f['type'] ?? '') === 'multiselect') {
                continue;
            }
            $v = $this->form[$k];
            $data[$k] = ($f['type'] ?? '') === 'switch' ? (bool) $v : ($v === '' ? null : $v);
        }
        $model->fill($this->beforeSave($data, $model->exists ? $model : null))->save();
        $this->afterSave($model);
        ActivityLog::record($this->editingId ? 'master_updated' : 'master_created', class_basename($model) . ': ' . ($model->name ?? $model->getKey()), $model);
        $this->showForm = false;
        $this->toast('success', $this->heading()[2] . ' tersimpan');
    }

    public function askDelete(?int $id = null): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        $ids = $this->deleteId ? [$this->deleteId] : $this->selectedIds();
        try {
            $n = 0;
            foreach (($this->model())::query()->whereIn('id', $ids)->get() as $m) {
                $m->delete();
                $n++;
            }
            ActivityLog::record('master_deleted', class_basename($this->model()) . ": {$n} dihapus", null, ['ids' => $ids]);
            $this->toast('success', "{$n} data dihapus");
        } catch (\Throwable $e) {
            $this->toast('error', 'Tidak dapat menghapus', 'Data masih dipakai oleh data lain.');
        }
        $this->confirmDelete = false;
        $this->deleteId = null;
        $this->clearSelection();
    }

    public function render()
    {
        $rows = $this->applyTable($this->query(), $this->searchColumns())->paginate($this->perPage);
        [$title, $subtitle, $single] = $this->heading();

        return view('livewire.admin.crud', [
            'rows' => $rows, 'fieldDefs' => $this->fields(), 'cols' => $this->columns(),
            'title' => $title, 'subtitle' => $subtitle, 'single' => $single,
        ])->title($title);
    }
}
