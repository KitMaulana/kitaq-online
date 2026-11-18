@props(['value' => 0, 'tone' => 'info'])
<div {{ $attributes->class(['progress']) }} role="progressbar" aria-valuenow="{{ (int) $value }}" aria-valuemin="0" aria-valuemax="100">
    <i style="width: {{ max(0, min(100, (float) $value)) }}%; background: var(--{{ $tone === 'danger' ? 'accent' : $tone }})"></i>
</div>
