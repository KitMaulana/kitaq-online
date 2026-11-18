{{-- Layout halaman staf tanpa login (login admin, 2FA, instalasi). --}}
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Masuk') — KITAQ ONLINE</title>
    <link rel="icon" href="/favicon.ico">
    <script nonce="{{ Vite::cspNonce() }}">
        (function () { var t = null; try { t = localStorage.getItem('kq:theme'); } catch (e) {} if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.theme = t; })();
    </script>
    @vite(['resources/js/student/student.js'])
</head>
<body>
<x-ui.icons />
<div class="guest">
    <div class="w-full max-w-md">
        <div class="text-center mb-5">
            <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE" class="mx-auto" style="width:240px">
        </div>
        <div class="card guest-card">
            @yield('content')
        </div>
        <p class="text-center text-xs muted mt-5">{{ kitaq_footer() }}</p>
    </div>
</div>
</body>
</html>
