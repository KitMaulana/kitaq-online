@props(['name' => '', 'src' => null, 'size' => 36])
@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class(['avatar']) }} style="width:{{ $size }}px;height:{{ $size }}px;object-fit:cover">
@else
    <span {{ $attributes->class(['avatar']) }} style="width:{{ $size }}px;height:{{ $size }}px" aria-hidden="true">{{ initials($name) }}</span>
@endif
