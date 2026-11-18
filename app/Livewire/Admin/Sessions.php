<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Services\ScheduleService;
use App\Support\ExamSettings;
use App\Support\SystemHealth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Sesi & Token (DESIGN_SYSTEM 5.6): token aktif besar + cincin hitung mundur, proyektor, kesehatan, profil keketatan. */
#[Title('Sesi & Token')]
class Sessions extends Component
{
    use WithToast;

    #[Url(as: 'schedule')]
    public ?int $selected = null;

    public function select(int $id): void
    {
        $this->selected = $id;
    }

    public function toggleToken(): void
    {
        $s = $this->current();
        if (! $s) {
            return;
        }
        $s->forceFill(['token_required' => ! $s->token_required])->save();
        ActivityLog::record('token_toggled', ($s->token_required ? 'Mewajibkan' : 'Mematikan') . ' token ' . $s->title(), $s);
        $this->toast($s->token_required ? 'success' : 'warning', $s->token_required ? 'Token diwajibkan' : 'Token tidak diwajibkan');
    }

    public function rotate(): void
    {
        if ($s = $this->current()) {
            app(ScheduleService::class)->rotateTokenKey($s, auth()->user());
            $this->toast('success', 'Token diganti', 'Token lama langsung tidak berlaku.');
        }
    }

    private function current(): ?ExamSchedule
    {
        return $this->selected ? ExamSchedule::query()->visibleTo(auth()->user())->with('exam')->find($this->selected) : null;
    }

    public function render()
    {
        $today = ExamSchedule::query()->visibleTo(auth()->user())->with(['exam', 'classroom', 'session'])
            ->whereDate('date', today())->orderBy('opens_at')->get();
        if (! $this->selected || ! $today->contains('id', $this->selected)) {
            $this->selected = ($today->firstWhere('status', 'open') ?? $today->first())?->id ?? $this->selected;
        }
        $current = $this->current();
        $counts = ExamAttempt::query()->whereIn('exam_schedule_id', $today->pluck('id'))
            ->selectRaw('exam_schedule_id, SUM(CASE WHEN started_at IS NOT NULL THEN 1 ELSE 0 END) as started')
            ->groupBy('exam_schedule_id')->pluck('started', 'exam_schedule_id');
        $totals = [];
        foreach ($today as $s) {
            $totals[$s->id] = $s->participantsQuery()->count();
        }
        $settings = $current?->exam->resolvedSettings();

        return view('livewire.admin.sessions', [
            'today' => $today,
            'current' => $current,
            'counts' => $counts,
            'totals' => $totals,
            'token' => $current ? ['token' => $current->currentToken(), 'left' => $current->secondsUntilTokenRotation(), 'total' => (int) config('kitaq.exam_token.rotation_sec', 900), 'url' => route('admin.token', $current)] : null,
            'settings' => $settings,
            'summary' => $settings ? ExamSettings::presetSummary($settings['preset'], $settings['overrides']) : [],
            'health' => [
                'cron' => SystemHealth::cronLastRun(), 'cronOk' => SystemHealth::cronHealthy(), 'queue' => SystemHealth::queueSize(),
                'api' => SystemHealth::avgApiMs(), 'online' => SystemHealth::onlineStudents(),
            ],
        ]);
    }
}
