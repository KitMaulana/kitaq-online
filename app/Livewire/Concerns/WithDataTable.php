<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Tabel server-side: cari (debounce 300 ms di Blade), filter, urut, paginasi 10/25/50/100.
 * Komponen cukup mendefinisikan $sortable dan memanggil applyTable($query, ['kolom', ...]).
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public int $perPage = 25;

    /** @var array<string,mixed> */
    public array $filters = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array((int) $this->perPage, [10, 25, 50, 100], true) ? (int) $this->perPage : 25;
        $this->resetPage();
    }

    public function isSortable(string $field): bool
    {
        return in_array($field, $this->sortable ?? [], true);
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortable ?? [], true)) {
            return;
        }
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
    }

    /** @param list<string> $searchColumns */
    protected function applyTable(Builder $query, array $searchColumns): Builder
    {
        $s = trim($this->search);
        if ($s !== '') {
            $query->where(function (Builder $w) use ($searchColumns, $s) {
                foreach ($searchColumns as $col) {
                    $w->orWhere($col, 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%');
                }
            });
        }
        $field = in_array($this->sortField, $this->sortable ?? [], true) ? $this->sortField : 'id';

        return $query->orderBy($field, $this->sortDirection === 'asc' ? 'asc' : 'desc');
    }
}
