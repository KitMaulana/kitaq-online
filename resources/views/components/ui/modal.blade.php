{{-- Modal: <x-ui.modal wire:model="showForm" title="..."> ... <x-slot:footer>...</x-slot:footer></x-ui.modal>
     Fokus dijebak di dalam, Esc menutup. Bisa juga dikendalikan Alpine: show="namaVariabel". --}}
@props(['title' => null, 'size' => null, 'show' => null])
@php $wireModel = $attributes->wire('model')->value(); @endphp
<div @if($wireModel) x-data="{ open: @entangle($wireModel).live }" @elseif($show) x-data="{ open: false }" x-modelable="open" x-model="{{ $show }}" @else x-data="{ open: false }" @endif
     x-show="open" x-cloak x-on:keydown.escape.window="open = false"
     class="overlay" :class="open ? 'show' : ''" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif
     x-trap.noscroll="open" x-on:click.self="open = false">
    <div @class(['modal', $size])>
        <div class="modal-head">
            <h2>{{ $title }}</h2>
            <button type="button" class="icon-btn" aria-label="Tutup" x-on:click="open = false"><x-ui.icon name="x" /></button>
        </div>
        <div class="modal-body">{{ $slot }}</div>
        @isset($footer)<div class="modal-foot">{{ $footer }}</div>@endisset
    </div>
</div>
