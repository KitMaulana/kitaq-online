{{-- Layout dashboard admin custom (tanpa Filament) — DESIGN_SYSTEM §3 + design/admin-prototype.html --}}
@php
    $user = auth('web')->user();
    $menu = \App\Support\AdminMenu::for($user);
    $online = \App\Support\SystemHealth::onlineStudents();
    $cronOk = \App\Support\SystemHealth::cronHealthy();
    $alertCount = \App\Models\MonitorAlert::query()->whereNull('read_at')->where('created_at', '>=', now()->subHours(6))
        ->whereHas('schedule', fn ($q) => $q->visibleTo($user)->whereIn('status', ['open', 'paused']))->count();
@endphp
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title . ' — ' : '' }}KITAQ ONLINE</title>
    <link rel="icon" href="/favicon.ico">
    <script nonce="{{ Vite::cspNonce() }}">
        (function () { var t = null; try { t = localStorage.getItem('kq:theme'); } catch (e) {} if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.theme = t; })();
    </script>
    @vite(['resources/js/app.js'])
    @livewireStyles(['nonce' => Vite::cspNonce()])
</head>
<body>
<x-ui.icons />
<div class="app" x-data="appShell" :class="{ collapsed: collapsed, open: open }">
    {{-- ========== SIDEBAR ========== --}}
    <aside class="sidebar" aria-label="Navigasi utama">
        <a href="{{ route('admin.dashboard') }}" class="brand-card" aria-label="KITAQ ONLINE — Dashboard">
            <img class="full" src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
            <img class="mark" src="/images/ciptakita-mark.png" alt="KITAQ">
        </a>
        <nav class="nav">
            @foreach ($menu as $group)
                <div class="nav-label">{{ $group['label'] }}</div>
                @foreach ($group['items'] as $item)
                    <a href="{{ route($item['route']) }}" @class(['nav-item', 'active' => request()->routeIs($item['match'])]) title="{{ $item['label'] }}">
                        <x-ui.icon :name="$item['icon']" /><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar-foot"><b>KITAQ ONLINE</b> v{{ kitaq_version() }}<br>© {{ date('Y') }} CiptaKita.id</div>
    </aside>
    <div class="only-mobile" x-show="open" x-cloak x-on:click="open = false" style="position:fixed;inset:0;background:rgba(5,14,45,.45);z-index:25"></div>

    {{-- ========== MAIN ========== --}}
    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn" aria-label="Lipat/buka menu" x-on:click="toggle()"><x-ui.icon name="menu" /></button>
            <button type="button" class="search" x-on:click="$dispatch('kq:palette')" aria-label="Cari (Ctrl+K)">
                <x-ui.icon name="search" /><span>Cari halaman, siswa, ujian…</span><x-ui.kbd>Ctrl</x-ui.kbd><x-ui.kbd>K</x-ui.kbd>
            </button>
            <div class="spacer"></div>
            @if (Route::has('admin.health') && $user->isAdminLike())
                <a href="{{ route('admin.health') }}" @class(['chip-server hide-mobile', 'warn' => ! $cronOk]) title="{{ $cronOk ? 'Cron berjalan normal' : 'Cron/antrean terlambat — klik untuk memeriksa' }}">
                    <span class="pulse"></span>{{ fmt_num($online) }} siswa online
                </a>
            @else
                <span class="chip-server hide-mobile"><span class="pulse"></span>{{ fmt_num($online) }} siswa online</span>
            @endif
            <a href="{{ Route::has('admin.monitoring.index') ? route('admin.monitoring.index') : '#' }}" class="icon-btn" aria-label="Notifikasi pelanggaran">
                <x-ui.icon name="bell" />@if($alertCount)<span class="dot">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>@endif
            </a>
            <button type="button" class="icon-btn" aria-label="Ganti tema terang/gelap" x-on:click="$store.theme.toggle()">
                <svg class="ico" x-show="$store.theme.value !== 'dark'"><use href="#i-moon"/></svg>
                <svg class="ico" x-show="$store.theme.value === 'dark'" x-cloak><use href="#i-sun"/></svg>
            </button>
            <x-ui.dropdown label="Menu pengguna">
                <x-slot:trigger>
                    <button type="button" class="user" style="background:none;border:0;color:inherit" aria-label="Menu pengguna">
                        <x-ui.avatar :name="$user->name" />
                        <span class="meta hide-mobile" style="text-align:left"><b>{{ $user->name }}</b><small>{{ $user->roleLabel() }}</small></span>
                    </button>
                </x-slot:trigger>
                <a href="{{ route('admin.profile') }}"><x-ui.icon name="user" />Profil & password</a>
                <hr>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="danger"><x-ui.icon name="logout" />Keluar</button></form>
            </x-ui.dropdown>
        </header>

        <main class="content" id="konten">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
        <footer class="footer">{{ kitaq_footer() }}</footer>
    </div>
</div>

{{-- Command palette (Ctrl+K) --}}
<div x-data="commandPalette(@js(\App\Support\AdminMenu::paletteItems($user)), @js(Route::has('admin.search') ? route('admin.search') : ''))"
     class="overlay" :class="open ? 'show' : ''" x-show="open" x-cloak x-on:click.self="open = false" role="dialog" aria-modal="true" aria-label="Pencarian cepat">
    <div class="palette" x-trap="open">
        <input x-ref="q" x-model="q" x-on:input="search()" x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="go()" placeholder="Ketik nama halaman, siswa (nama/NISN), atau ujian…" aria-label="Kata kunci">
        <ul>
            <template x-for="(it, i) in results" :key="it.url + i">
                <li :class="i === idx && 'on'" x-on:mouseenter="idx = i" x-on:click="go(it)"><span x-text="it.label"></span><small x-text="it.group"></small></li>
            </template>
            <li x-show="!results.length" class="muted">Tidak ada hasil.</li>
        </ul>
    </div>
</div>

<x-ui.toasts />
@livewireScriptConfig(['nonce' => Vite::cspNonce()])
@stack('scripts')
</body>
</html>
