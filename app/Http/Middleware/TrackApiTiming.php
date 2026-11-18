<?php

namespace App\Http\Middleware;

use App\Support\SystemHealth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengukur waktu respons API ujian (sampel 1 dari N agar tidak menambah beban)
 * untuk panel "Kesehatan sistem" & adaptive heartbeat.
 */
class TrackApiTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $t = microtime(true);
        $response = $next($request);
        if (random_int(1, 10) === 1) {
            SystemHealth::recordApiTiming((microtime(true) - $t) * 1000);
        }

        return $response;
    }
}
