<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\AttemptCommand;
use App\Models\MonitorAlert;
use App\Models\ReportExport;
use App\Models\ShieldEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Pembersihan log lama (KEAMANAN.md: retensi log pelanggaran default 365 hari). */
class PruneLogs extends Command
{
    protected $signature = 'kitaq:prune';

    protected $description = 'Hapus log pelanggaran, alarm, perintah, log aktivitas, dan file ekspor yang melewati masa retensi';

    public function handle(): int
    {
        $days = max(30, (int) config('kitaq.privacy.retention_days_logs', 365));
        $cut = now()->subDays($days);
        $events = ShieldEvent::query()->where('received_at', '<', $cut)->delete();
        $logs = ActivityLog::query()->where('created_at', '<', $cut)->delete();
        $alerts = MonitorAlert::query()->where('created_at', '<', now()->subDays(30))->delete();
        $cmds = AttemptCommand::query()->where('created_at', '<', now()->subDays(30))->delete();

        $files = 0;
        foreach (ReportExport::query()->where('created_at', '<', now()->subDays(7))->get() as $e) {
            if ($e->path) {
                Storage::disk('local')->delete($e->path);
            }
            $e->delete();
            $files++;
        }
        // Sisa folder import yang tidak selesai.
        foreach (glob(storage_path('app/private-import/*')) ?: [] as $dir) {
            if (is_dir($dir) && filemtime($dir) < time() - 86400) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }

        $this->info("Dihapus: {$events} event, {$logs} log aktivitas, {$alerts} alarm, {$cmds} perintah, {$files} ekspor.");

        return self::SUCCESS;
    }
}
