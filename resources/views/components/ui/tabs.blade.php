{{-- Tab dalam halaman.
     Livewire : <x-ui.tabs :tabs="[...]" :active="$tab" wire-prop="tab" />
     Alpine   : <x-ui.tabs :tabs="[...]" model="tab" />   (variabel Alpine induk)
     Tautan   : <x-ui.tabs :tabs="['a' => ['label' => 'A', 'url' => '...']]" active="a" /> --}}
@props(['tabs' => [], 'active' => null, 'model' => null, 'wireProp' => null])
<div class="tabs" role="tablist">
    @foreach ($tabs as $key => $label)
        @if ($wireProp)
            <button type="button" role="tab" @class(['tab', 'on' => $active === $key]) aria-selected="{{ $active === $key ? 'true' : 'false' }}" wire:click="$set('{{ $wireProp }}', '{{ $key }}')">{{ $label }}</button>
        @elseif ($model)
            <button type="button" role="tab" class="tab" :class="{{ $model }} === @js($key) ? 'on' : ''" x-on:click="{{ $model }} = @js($key)">{{ $label }}</button>
        @else
            <a role="tab" @class(['tab', 'on' => $active === $key]) href="{{ $label['url'] ?? '#' }}">{{ $label['label'] ?? $label }}</a>
        @endif
    @endforeach
</div>
