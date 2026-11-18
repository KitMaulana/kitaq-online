@props(['status' => 'not_started', 'label' => null])
@php
    $map = [
        'not_started' => ['st-idle', 'circle', 'Belum masuk'],
        'in_progress' => ['st-active', 'pencil', 'Mengerjakan'],
        'warned' => ['st-warned', 'warn', 'Peringatan'],
        'locked' => ['st-locked', 'lock', 'Terkunci'],
        'disconnected' => ['st-offline', 'wifioff', 'Terputus'],
        'submitted' => ['st-done', 'checkc', 'Selesai'],
        'force_submitted' => ['st-done', 'checkc', 'Dikumpulkan paksa'],
        'scheduled' => ['st-idle', 'clock', 'Terjadwal'],
        'open' => ['st-active', 'play', 'Berlangsung'],
        'paused' => ['st-warned', 'pause', 'Dijeda'],
        'closed' => ['st-done', 'checkc', 'Ditutup'],
    ];
    [$cls, $ico, $text] = $map[$status] ?? ['st-idle', 'circle', $status];
@endphp
<span {{ $attributes->class(['status', $cls]) }}><x-ui.icon :name="$ico" size="sm" />{{ $label ?? $text }}</span>
