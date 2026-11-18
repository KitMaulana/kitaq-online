{{-- Editor TipTap lokal. <x-ui.editor wire:model="content" label="Teks soal" upload="editorUpload" /> --}}
@props(['label' => null, 'upload' => null, 'hint' => 'Rumus: tulis di antara $…$, contoh $x^2 + 1$.'])
@php $model = $attributes->wire('model')->value(); @endphp
<div class="field">
    @if ($label)<label>{{ $label }}</label>@endif
    <div class="tiptap-wrap" wire:ignore x-data="richEditor($wire.$entangle(@js($model), @js($attributes->wire('model')->hasModifier('live'))), @js($upload))">
        <div class="tiptap-bar" role="toolbar" aria-label="Format teks">
            <button type="button" :class="is('bold') && 'on'" x-on:click="run('bold')" aria-label="Tebal"><b>B</b></button>
            <button type="button" :class="is('italic') && 'on'" x-on:click="run('italic')" aria-label="Miring"><i>I</i></button>
            <button type="button" :class="is('strike') && 'on'" x-on:click="run('strike')" aria-label="Coret"><s>S</s></button>
            <button type="button" :class="is('heading', { level: 3 }) && 'on'" x-on:click="run('h3')" aria-label="Judul">H</button>
            <button type="button" :class="is('bulletList') && 'on'" x-on:click="run('bullet')" aria-label="Daftar">•</button>
            <button type="button" :class="is('orderedList') && 'on'" x-on:click="run('ordered')" aria-label="Daftar bernomor">1.</button>
            <button type="button" :class="is('blockquote') && 'on'" x-on:click="run('quote')" aria-label="Kutipan">❝</button>
            <button type="button" x-on:click="run('math')" aria-label="Sisipkan rumus">∑</button>
            @if ($upload)
                <button type="button" x-on:click="pickImage()" aria-label="Sisipkan gambar"><svg class="ico sm"><use href="#i-image"/></svg></button>
                <input type="file" accept="image/*" class="sr" x-ref="file" x-on:change="upload($event)">
            @endif
            <span class="flex-1"></span>
            <button type="button" x-on:click="run('undo')" aria-label="Urungkan">↶</button>
            <button type="button" x-on:click="run('redo')" aria-label="Ulangi">↷</button>
        </div>
        <div x-ref="editor" class="prose"></div>
    </div>
    @error($model)<span class="error">{{ $message }}</span>@else @if($hint)<span class="hint">{{ $hint }}</span>@endif @enderror
</div>
