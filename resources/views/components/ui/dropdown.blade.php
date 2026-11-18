@props(['label' => 'Aksi', 'icon' => 'more'])
<div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    @isset($trigger)
        <span x-on:click="open = !open">{{ $trigger }}</span>
    @else
        <button type="button" class="icon-btn" aria-label="{{ $label }}" x-on:click="open = !open"><x-ui.icon :name="$icon" /></button>
    @endisset
    <div class="dropdown-menu" x-show="open" x-cloak x-transition.opacity x-on:click="open = false">{{ $slot }}</div>
</div>
