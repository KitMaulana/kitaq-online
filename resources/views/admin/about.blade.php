@extends('layouts.admin', ['title' => 'Tentang KITAQ'])
@section('content')
    <x-ui.page-header title="Tentang KITAQ ONLINE" subtitle="KITA Assessment & Question — versi online" />
    <div class="grid g-2b">
        <x-ui.card>
            <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE" style="width:280px" class="mb-4">
            <dl class="kv-list">
                <dt>Versi</dt><dd>{{ kitaq_version() }}</dd>
                <dt>Framework</dt><dd>Laravel {{ app()->version() }}</dd>
                <dt>PHP</dt><dd>{{ PHP_VERSION }}</dd>
                <dt>Realtime</dt><dd>Polling HTTP ({{ config('kitaq.realtime.monitor_poll_sec') }} dtk)</dd>
                <dt>Sekolah</dt><dd>{{ school('school_name') }}</dd>
            </dl>
        </x-ui.card>
        <x-ui.card title="CiptaKita.id">
            <img src="/images/ciptakita-mark.png" alt="CiptaKita.id" style="width:64px" class="mb-3">
            <p class="font-semibold">Solusi Digitalmu, dari kita!</p>
            <p class="muted text-sm mt-2">KITAQ ONLINE adalah ujian online anti-contek untuk HP & laptop: login NISN + tanggal lahir, token ujian berganti, KITAQ Shield, dan Keyboard Virtual KITAQ.</p>
            <p class="muted text-sm mt-2">Keterbatasan web (tangkapan layar, HP kedua, asisten AI tingkat sistem) dijelaskan di Spesifikasi Bagian 4 — tetap butuh pengawasan ruang.</p>
        </x-ui.card>
    </div>
@endsection
