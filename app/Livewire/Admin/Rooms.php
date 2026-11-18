<?php

namespace App\Livewire\Admin;

use App\Models\Room;

class Rooms extends CrudPage
{
    protected array $sortable = ['name', 'capacity'];

    protected function model(): string
    {
        return Room::class;
    }

    protected function heading(): array
    {
        return ['Ruang & Sesi', 'Ruang ujian (opsional) dan sesi waktu ujian.', 'Ruang'];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nama ruang', 'rules' => ['required', 'string', 'max:60', 'unique:rooms,name,{id}']],
            'capacity' => ['label' => 'Kapasitas', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:1000']],
        ];
    }

    protected function columns(): array
    {
        return ['name' => 'Ruang', 'capacity' => 'Kapasitas'];
    }

    public function topView(): string
    {
        return 'livewire.admin.partials.rooms-sessions';
    }
}
