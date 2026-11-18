<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\ActivityLog;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Log aktivitas: siapa membuka kunci, mengubah nilai, mereset login, mengekspor data, login. */
#[Title('Log Aktivitas')]
class ActivityLogs extends Component
{
    use WithDataTable;

    protected array $sortable = ['created_at'];

    public function mount(): void
    {
        $this->sortField = 'created_at';
        $this->filters = ['action' => ''];
    }

    public function render()
    {
        $q = ActivityLog::query()->with(['user:id,name', 'student:id,name,nisn']);
        if ($this->filters['action'] ?? '') {
            $q->where('action', $this->filters['action']);
        }

        return view('livewire.admin.activity-logs', [
            'logs' => $this->applyTable($q, ['description', 'action', 'ip'])->paginate($this->perPage),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
