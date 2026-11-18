{{-- Layar proyektor (layouts/projector): alamat situs, QR, token besar, hitung mundur. Token diperbarui dari server. --}}
<!doctype html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Token — {{ $schedule->title() }}</title>
    @vite(['resources/js/app.js'])
    @livewireStyles(['nonce' => Vite::cspNonce()])
    <style nonce="{{ Vite::cspNonce() }}">body{min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at 50% 20%,#0a2a8a,#020a2a)}.projector{width:min(900px,96vw)}</style>
</head>
<body>
<div class="projector" x-data="tokenRing(@js($token))">
    <img src="/images/kitaq-online-logo-dark.png" alt="KITAQ ONLINE" style="height:54px;margin:0 auto">
    <div class="p-label">{{ $schedule->exam->name }} · {{ $schedule->classroom?->name ?? 'Peserta khusus' }} · {{ $schedule->opens_at->format('H:i') }}–{{ $schedule->closes_at->format('H:i') }}</div>
    <div class="flex items-center justify-center gap-8 mt-6 flex-wrap">
        <div style="background:#fff;border-radius:16px;padding:12px;line-height:0">{!! $qr !!}</div>
        <div class="text-left">
            <div class="p-label" style="margin-top:0">Buka di HP</div>
            <div class="p-addr">{{ site_host() }}</div>
            <div class="p-label">Login: NISN + tanggal lahir (DDMMYYYY)</div>
        </div>
    </div>
    <div class="p-label">TOKEN UJIAN</div>
    <div class="p-token" x-text="grouped" aria-live="polite">{{ $token['token'] }}</div>
    <div class="p-label">Token berganti dalam <b class="tabular" x-text="mmss"></b></div>
    <div style="height:8px;border-radius:99px;background:rgba(255,255,255,.15);overflow:hidden;margin-top:12px"><i style="display:block;height:100%;background:#4da3ff" :style="'width:' + (left / total * 100) + '%'"></i></div>
    <p class="p-label" style="font-size:12px">{{ kitaq_footer() }}</p>
</div>
@livewireScriptConfig(['nonce' => Vite::cspNonce()])
</body>
</html>
