@props(['label' => null, 'hint' => null, 'name' => null, 'options' => [], 'placeholder' => null, 'error' => null])
@php
    $id = $attributes->get('id') ?? ($name ?? $attributes->wire('model')->value() ?? 'sl' . uniqid());
    $field = $name ?? $attributes->wire('model')->value();
    $err = $error ?? ($field && isset($errors) ? $errors->first($field) : null);
@endphp
<div class="field">
    @if ($label)<label for="{{ $id }}">{{ $label }}</label>@endif
    <select id="{{ $id }}" @if($name) name="{{ $name }}" @endif {{ $attributes->class(['select', 'is-invalid' => $err]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($err)<span class="error">{{ $err }}</span>@elseif ($hint)<span class="hint">{{ $hint }}</span>@endif
</div>
