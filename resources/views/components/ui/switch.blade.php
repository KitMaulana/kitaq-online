@props(['label' => null, 'hint' => null])
<label class="switch">
    <input type="checkbox" role="switch" {{ $attributes }}>
    <span class="track"></span>
    <span>
        @if ($label)<span class="font-semibold">{{ $label }}</span>@endif
        @if ($hint)<span class="block text-xs muted">{{ $hint }}</span>@endif
        {{ $slot }}
    </span>
</label>
