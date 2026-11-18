@props(['label' => null])
<label class="inline-flex items-center gap-2 cursor-pointer text-sm">
    <input type="radio" {{ $attributes->class(['check']) }}>
    @if ($label)<span>{{ $label }}</span>@endif{{ $slot }}
</label>
