<?php

use App\Jobs\AutoSubmitExpiredAttempts;
use App\Jobs\DetectHeartbeatGaps;
use App\Services\ScheduleService;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler KITAQ ONLINE — shared hosting: cron tiap menit → php artisan schedule:run
|--------------------------------------------------------------------------
| Tanpa Supervisor: antrean dikerjakan `queue:work --stop-when-empty --max-time=50` tiap menit.
*/

Artisan::command('kitaq:tick', function (ScheduleService $schedules) {
    SystemHealth::markCron();
    $r = $schedules->tick();
    $submitted = app()->call([new AutoSubmitExpiredAttempts(), 'handle']);
    $gaps = app()->call([new DetectHeartbeatGaps(), 'handle']);
    $this->info("dibuka {$r['opened']} · ditutup {$r['closed']} · dikumpulkan otomatis {$submitted} · terputus {$gaps}");
})->purpose('Buka/tutup jadwal otomatis, kumpul otomatis saat waktu habis, deteksi heartbeat hilang');

Schedule::command('kitaq:tick')->everyMinute()->withoutOverlapping(2);
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=45')->everyMinute()->withoutOverlapping(2);

Artisan::command('kitaq:backup', function (\App\Services\BackupService $backup) {
    $this->info('Backup: ' . $backup->run());
})->purpose('Backup database (mysqldump atau ekspor per tabel) ke storage/app/backups, retensi 14 hari');

Schedule::command('kitaq:backup')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('kitaq:prune')->dailyAt('02:00')->withoutOverlapping();
