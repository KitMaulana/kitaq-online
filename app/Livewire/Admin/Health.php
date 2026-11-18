<?php

namespace App\Livewire\Admin;

use App\Models\ExamAttempt;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Kesehatan sistem (/admin/kesehatan): cron, antrean, ukuran database, versi PHP, waktu respons. */
#[Title('Kesehatan Sistem')]
class Health extends Component
{
    public function render()
    {
        $checks = [];
        $cron = SystemHealth::cronLastRun();
        $checks[] = ['Cron (schedule:run)', SystemHealth::cronHealthy(), $cron ? 'Terakhir ' . \Illuminate\Support\Carbon::createFromTimestamp($cron)->diffForHumans() : 'Belum pernah berjalan — pasang cron tiap menit'];
        $q = SystemHealth::queueSize();
        $checks[] = ['Antrean (jobs)', $q < 50, "{$q} menunggu · " . SystemHealth::failedJobs() . ' gagal'];
        $api = SystemHealth::avgApiMs();
        $checks[] = ['Respons API ujian', $api === null || $api < 800, $api !== null ? fmt_num($api) . ' ms rata-rata (5 menit, sampel)' : 'Belum ada data'];
        $checks[] = ['Versi PHP', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '8.3.0', '<') ? ' — disarankan 8.3/8.4 bila tersedia' : '')];
        $checks[] = ['OPcache', (bool) (function_exists('opcache_get_status') && @opcache_get_status(false)), function_exists('opcache_get_status') ? 'Periksa di panel hosting' : 'Tidak terpasang'];
        $checks[] = ['APP_DEBUG mati', ! config('app.debug'), config('app.debug') ? 'Matikan di produksi (APP_DEBUG=false)' : 'OK'];
        $checks[] = ['HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url')];
        $checks[] = ['Driver session/cache/queue', config('session.driver') === 'database' && config('queue.default') === 'database', config('session.driver') . ' / ' . config('cache.default') . ' / ' . config('queue.default')];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'gd', 'zip', 'fileinfo', 'bcmath', 'intl'] as $ext) {
            $checks[] = ["Ekstensi {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'Aktif' : ($ext === 'intl' ? 'Disarankan (tidak wajib)' : 'Belum aktif')];
        }
        $checks[] = ['Folder storage dapat ditulis', is_writable(storage_path('app')) && is_writable(storage_path('logs')), storage_path()];
        $checks[] = ['Mode uji beban', ! config('kitaq.load_test'), config('kitaq.load_test') ? 'KITAQ_LOAD_TEST aktif — matikan saat ujian sungguhan!' : 'Nonaktif'];

        return view('livewire.admin.health', [
            'checks' => $checks,
            'db' => SystemHealth::humanBytes(SystemHealth::databaseSize()),
            'online' => SystemHealth::onlineStudents(),
            'active' => ExamAttempt::query()->whereIn('status', ExamAttempt::ACTIVE_STATUSES)->count(),
            'heartbeat' => SystemHealth::heartbeatInterval(),
            'tables' => collect(['students', 'questions', 'exam_attempts', 'attempt_answers', 'shield_events', 'activity_logs'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]),
        ]);
    }
}
