<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Halaman publik ringan: panduan pasang PWA, cek perangkat, logo sekolah. */
class PublicController extends Controller
{
    public function install(): View
    {
        return view('student.install');
    }

    public function deviceCheck()
    {
        return response()->file(public_path('cek-perangkat.html'), ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function schoolLogo()
    {
        $path = Setting::get('school_logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=86400']);
    }
}
