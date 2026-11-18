<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk KITAQ ONLINE.
 *
 * Pemakaian di routes:
 *   Route::middleware('security.headers:exam')  → halaman ujian siswa (CSP ketat, tanpa eval)
 *   Route::middleware('security.headers:app')   → admin & halaman lain (Livewire/Alpine butuh 'unsafe-eval')
 * Daftarkan alias di bootstrap/app.php:
 *   $middleware->alias(['security.headers' => \App\Http\Middleware\SecurityHeaders::class]);
 *
 * CSP pada halaman ujian juga mempersulit skrip yang disuntikkan lewat halaman
 * (bukan ekstensi — ekstensi bisa melewati CSP, karena itu ada extension-guard di klien).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next, string $profile = 'app'): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);
        $nonce = Vite::cspNonce();

        $script = $profile === 'exam'
            ? "script-src 'self' 'nonce-{$nonce}'"
            : "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'";

        $csp = implode('; ', [
            "default-src 'self'",
            $script,
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        if ($request->isSecure()) {
            $csp .= '; upgrade-insecure-requests';
        }

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), display-capture=(), clipboard-read=(), payment=(), usb=(), serial=(), bluetooth=(), fullscreen=(self)');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($profile === 'exam') {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
