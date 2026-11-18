@props(['accept' => null, 'label' => 'Seret berkas ke sini atau klik untuk memilih', 'hint' => null, 'multiple' => false])
@php $id = 'fd' . uniqid(); @endphp
<label for="{{ $id }}" class="dropzone block cursor-pointer" x-data="{ over: false, name: '' }"
       x-on:dragover.prevent="over = true" x-on:dragleave="over = false" x-on:drop="over = false"
       :style="over ? 'border-color:var(--info);background:var(--info-soft)' : ''">
    <x-ui.icon name="upload" />
    <div class="font-semibold" style="color:var(--text)" x-text="name || @js($label)"></div>
    @if ($hint)<div class="text-xs mt-1">{{ $hint }}</div>@endif
    <input id="{{ $id }}" type="file" class="sr" @if($accept) accept="{{ $accept }}" @endif @if($multiple) multiple @endif
           x-on:change="name = Array.from($event.target.files).map(f => f.name).join(', ')" {{ $attributes }}>
    <div wire:loading wire:target="{{ $attributes->wire('model')->value() }}" class="mt-2 text-sm"><span class="spin"></span> Mengunggah…</div>
</label>
