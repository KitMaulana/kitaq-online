<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\View;

/** Halaman publik ringan: panduan pasang PWA, cek perangkat, logo sekolah. */
class PublicController extends Controller
{
    public function install(): View
    {
        return view('student.install');
    }

    /**
     * Halaman cek perangkat = berkas statis dengan skrip inline. CSP hanya mengizinkan skrip ber-nonce,
     * jadi nonce dari SecurityHeaders disuntikkan ke setiap <script> (tanpa ini JS diblokir dan halaman macet).
     */
    public function deviceCheck(): Response
    {
        $html = (string) file_get_contents(public_path('cek-perangkat.html'));
        $html = preg_replace('/<script(?![^>]*\bsrc=)(?![^>]*\bnonce=)/i', '<script nonce="' . Vite::cspNonce() . '"', $html);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /** Uji koneksi ringan untuk halaman Cek Perangkat (tanpa data, tanpa sesi). */
    public function ping(): Response
    {
        return response('', 204, ['Cache-Control' => 'no-store']);
    }

    public function schoolLogo()
    {
        $path = Setting::get('school_logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=86400']);
    }
}
