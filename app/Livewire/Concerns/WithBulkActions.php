<?php

namespace App\Livewire\Concerns;

/** Pilih massal baris tabel + bar aksi melayang (DESIGN_SYSTEM 5.1). */
trait WithBulkActions
{
    /** @var list<string> */
    public array $selected = [];

    public bool $selectPage = false;

    /** @param list<int|string> $idsOnPage */
    public function toggleSelectPage(array $idsOnPage): void
    {
        $ids = array_map('strval', $idsOnPage);
        if ($this->selectPage) {
            $this->selected = array_values(array_diff($this->selected, $ids));
            $this->selectPage = false;
        } else {
            $this->selected = array_values(array_unique(array_merge($this->selected, $ids)));
            $this->selectPage = true;
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }

    /** @return list<int> */
    protected function selectedIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->selected)));
    }
}
