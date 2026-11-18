<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Instalasi darurat TANPA SSH (docs/DEPLOY_SHARED_HOSTING.md §6):
 * aktifkan KITAQ_INSTALL_TOKEN=<acak panjang>, buka /install?token=..., jalankan migrate + seed Super Admin SEKALI,
 * lalu rute menonaktifkan dirinya (berkas penanda storage/app/install.lock). Hapus token setelah selesai.
 */
class InstallController extends Controller
{
    public const LOCK = 'app/install.lock';

    public function __invoke(Request $request)
    {
        $expected = (string) config('kitaq.install_token');
        $key = 'install:' . $request->ip();
        abort_if($expected === '' || strlen($expected) < 24, 404);
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429);
        if (! hash_equals($expected, (string) $request->query('token', ''))) {
            RateLimiter::hit($key, 600);
            abort(404);
        }
        if (is_file(storage_path(self::LOCK))) {
            return response('Instalasi sudah pernah dijalankan dan rute ini nonaktif. Hapus KITAQ_INSTALL_TOKEN dari .env.', 410)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $out = [];
        Artisan::call('migrate', ['--force' => true]);
        $out[] = trim(Artisan::output());
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SuperAdminSeeder', '--force' => true]);
        $out[] = trim(Artisan::output());
        try {
            Artisan::call('storage:link');
            $out[] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $out[] = 'storage:link gagal (symlink dilarang hosting) — tidak masalah, file disajikan lewat rute aplikasi.';
        }
        file_put_contents(storage_path(self::LOCK), now()->toDateTimeString());

        return response(implode("\n\n", array_filter($out)) . "\n\nSELESAI. Login /admin/login dengan superadmin / KitaQ#2026 lalu ganti password. Hapus KITAQ_INSTALL_TOKEN dari .env sekarang.", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
