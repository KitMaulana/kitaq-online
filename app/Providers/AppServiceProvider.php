<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ExamTokenService;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Token ujian berganti (HMAC). Secret diturunkan dari APP_KEY agar tidak perlu kunci tambahan di .env.
        $this->app->singleton(ExamTokenService::class, function ($app) {
            $secret = hash_hmac('sha256', 'kitaq-exam-token', (string) $app['config']->get('app.key', 'kitaq'));

            return new ExamTokenService(
                $secret,
                (int) $app['config']->get('kitaq.exam_token.rotation_sec', 900),
                (int) $app['config']->get('kitaq.exam_token.length', 6),
            );
        });
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        // Super Admin boleh semua.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // API ujian siswa: cukup longgar untuk heartbeat+jawaban+event, tetap membatasi penyalahgunaan.
        RateLimiter::for('exam-api', function (Request $request) {
            $who = auth('student')->id() ?: $request->ip();

            return Limit::perMinute(120)->by('exam:' . $who);
        });
        RateLimiter::for('monitor-api', function (Request $request) {
            return Limit::perMinute(90)->by('mon:' . (auth('web')->id() ?: $request->ip()));
        });
        RateLimiter::for('staff-login', function (Request $request) {
            return Limit::perMinute(10)->by('staff:' . $request->ip());
        });
    }
}
