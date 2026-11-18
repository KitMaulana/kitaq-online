@props(['variant' => 'primary', 'size' => null, 'icon' => null, 'href' => null, 'loading' => null, 'type' => 'button'])
@php($cls = ['btn', 'btn-' . $variant, $size])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($cls) }}>@if($icon)<x-ui.icon :name="$icon" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($cls) }} @if($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif>
        @if($loading)<span class="spin" wire:loading wire:target="{{ $loading }}"></span>@endif
        @if($icon)<x-ui.icon :name="$icon" @if($loading) wire:loading.remove wire:target="{{ $loading }}" @endif />@endif
        {{ $slot }}
    </button>
@endif
