@props(['title' => null])
@php $wireModel = $attributes->wire('model')->value(); @endphp
<div x-data="{ open: @entangle($wireModel).live }" x-show="open" x-cloak x-on:keydown.escape.window="open = false">
    <div class="drawer-wrap" x-on:click="open = false"></div>
    <aside class="drawer" role="dialog" aria-modal="true" x-trap="open">
        <div class="drawer-head"><h2>{{ $title }}</h2><button type="button" class="icon-btn" aria-label="Tutup" x-on:click="open = false"><x-ui.icon name="x" /></button></div>
        <div class="drawer-body">{{ $slot }}</div>
    </aside>
</div>
