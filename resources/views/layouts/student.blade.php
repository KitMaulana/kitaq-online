{{-- Layout halaman siswa non-ujian (mobile-first). Ruang ujian memakai layouts/exam.blade.php. --}}
<!doctype html>
<html lang="id" translate="no" class="notranslate" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="google" content="notranslate">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#022684">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="KITAQ ONLINE">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" href="/favicon.ico">
    <title>@yield('title', 'KITAQ ONLINE') — KITAQ ONLINE</title>
    @vite(['resources/js/student/student.js'])
</head>
<body>
<x-ui.icons />
<div class="st-wrap">
    @yield('content')
    <p class="by">{{ kitaq_footer() }}</p>
</div>
@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $k => $tone)
    @if (session($k))
        <div class="pad" style="position:fixed;left:0;right:0;top:12px;z-index:60;max-width:520px;margin:0 auto">
            <x-ui.alert :tone="$tone">{{ session($k) }}</x-ui.alert>
        </div>
    @endif
@endforeach
</body>
</html>
