<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Katalog visual komponen x-ui.* — hanya APP_ENV=local (DESIGN_SYSTEM §4). */
#[Title('Katalog UI')]
class UiCatalog extends Component
{
    use WithToast;

    public bool $showModal = false;
    public bool $showConfirm = false;
    public bool $showDrawer = false;
    public string $name = '';
    public bool $flag = true;

    public function mount(): void
    {
        abort_unless(app()->environment('local'), 404);
    }

    public function ping(): void
    {
        $this->showConfirm = false;
        $this->toast('success', 'Contoh toast', 'Komponen berjalan.');
    }

    public function render()
    {
        return view('livewire.admin.ui-catalog');
    }
}
