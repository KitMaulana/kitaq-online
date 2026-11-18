@props(['label' => null, 'hint' => null, 'name' => null, 'error' => null])
@php
    $id = $attributes->get('id') ?? ($name ?? $attributes->wire('model')->value() ?? 'ta' . uniqid());
    $field = $name ?? $attributes->wire('model')->value();
    $err = $error ?? ($field && isset($errors) ? $errors->first($field) : null);
@endphp
<div class="field">
    @if ($label)<label for="{{ $id }}">{{ $label }}</label>@endif
    <textarea id="{{ $id }}" @if($name) name="{{ $name }}" @endif {{ $attributes->class(['textarea', 'is-invalid' => $err]) }}>{{ $slot }}</textarea>
    @if ($err)<span class="error">{{ $err }}</span>@elseif ($hint)<span class="hint">{{ $hint }}</span>@endif
</div>
