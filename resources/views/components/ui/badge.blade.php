@props(['tone' => 'neutral', 'dot' => false])
<span {{ $attributes->class(['badge', 'tone-' . $tone]) }}>@if($dot)<i class="d"></i>@endif{{ $slot }}</span>
