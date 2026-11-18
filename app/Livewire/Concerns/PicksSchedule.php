<?php

namespace App\Livewire\Concerns;

use App\Models\ExamSchedule;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/** Pemilih jadwal untuk halaman laporan (hanya jadwal yang boleh dilihat pengguna). */
trait PicksSchedule
{
    #[Url(as: 'jadwal')]
    public ?int $scheduleId = null;

    protected function scheduleOptions(): Collection
    {
        return ExamSchedule::query()->visibleTo(auth()->user())->with(['exam', 'classroom'])
            ->whereHas('attempts', fn ($q) => $q->whereNotNull('started_at'))
            ->orderByDesc('opens_at')->limit(200)->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->date->format('d/m') . ' · ' . $s->exam->name . ' · ' . ($s->classroom?->name ?? 'Khusus')]);
    }

    protected function pickedSchedule(): ?ExamSchedule
    {
        if (! $this->scheduleId) {
            $this->scheduleId = $this->scheduleOptions()->keys()->first();
        }

        return $this->scheduleId ? ExamSchedule::query()->visibleTo(auth()->user())->with(['exam.subject', 'classroom'])->find($this->scheduleId) : null;
    }
}
