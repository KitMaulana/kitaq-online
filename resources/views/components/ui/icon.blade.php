@props(['name', 'size' => null])
<svg {{ $attributes->class(['ico', $size]) }} aria-hidden="true"><use href="#i-{{ $name }}"/></svg>
