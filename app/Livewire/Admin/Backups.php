<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Backup')]
class Backups extends Component
{
    use WithToast;

    public bool $showPassword = false;

    public function backupNow(BackupService $svc): void
    {
        try {
            $path = $svc->run();
            ActivityLog::record('backup_created', basename($path));
            $this->toast('success', 'Backup dibuat', basename($path));
        } catch (\Throwable $e) {
            report($e);
            $this->toast('error', 'Backup gagal', $e->getMessage());
        }
    }

    public function download(string $name)
    {
        abort_unless(preg_match('/^kitaq-backup-[\d-]+\.zip$/', $name), 404);
        $path = BackupService::DIR . '/' . $name;
        abort_unless(Storage::disk('local')->exists($path), 404);
        ActivityLog::record('backup_downloaded', $name);

        return Storage::disk('local')->download($path);
    }

    public function delete(string $name): void
    {
        abort_unless(preg_match('/^kitaq-backup-[\d-]+\.zip$/', $name), 404);
        Storage::disk('local')->delete(BackupService::DIR . '/' . $name);
        ActivityLog::record('backup_deleted', $name);
        $this->toast('success', 'Backup dihapus');
    }

    public function render(BackupService $svc)
    {
        return view('livewire.admin.backups', ['files' => $svc->list(), 'password' => $this->showPassword ? BackupService::password() : null]);
    }
}
