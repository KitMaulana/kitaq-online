<?php

namespace App\Livewire\Admin;

use App\Models\ExamSession;

/** Sesi ujian (dipakai di halaman Ruang & Sesi sebagai komponen tersemat). */
class ExamSessions extends CrudPage
{
    protected array $sortable = ['starts_at', 'name'];

    protected function model(): string
    {
        return ExamSession::class;
    }

    protected function heading(): array
    {
        return ['Sesi Ujian', 'Sesi waktu, mis. Sesi 1 07.30–09.30. Bagi sesi bergiliran bila peserta banyak (shared hosting).', 'Sesi'];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nama sesi', 'rules' => ['required', 'string', 'max:60']],
            'starts_at' => ['label' => 'Mulai', 'type' => 'time', 'rules' => ['nullable', 'date_format:H:i,H:i:s']],
            'ends_at' => ['label' => 'Selesai', 'type' => 'time', 'rules' => ['nullable', 'date_format:H:i,H:i:s']],
        ];
    }

    protected function columns(): array
    {
        return ['name' => 'Sesi', 'starts_at' => 'Mulai', 'ends_at' => 'Selesai'];
    }
}
