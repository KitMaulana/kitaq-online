<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Shared hosting sering di balik proxy/CDN → percayai header X-Forwarded-* (KITAQ_TRUST_PROXIES).
        $proxies = env('KITAQ_TRUST_PROXIES', '*');
        $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));

        $middleware->alias([
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'staff' => \App\Http\Middleware\EnsureStaffSession::class,
            'student.session' => \App\Http\Middleware\EnsureStudentSession::class,
            'browser.allowed' => \App\Http\Middleware\EnsureBrowserAllowed::class,
            'exam.location' => \App\Http\Middleware\EnsureExamLocation::class,
            'api.timing' => \App\Http\Middleware\TrackApiTiming::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('student.login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('student.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API ujian & monitoring selalu membalas JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
