@props(['label' => null, 'hint' => null, 'name' => null, 'icon' => null, 'type' => 'text', 'error' => null])
@php
    $id = $attributes->get('id') ?? ($name ?? $attributes->wire('model')->value() ?? 'in' . uniqid());
    $field = $name ?? $attributes->wire('model')->value();
    $err = $error ?? ($field && isset($errors) ? $errors->first($field) : null);
@endphp
<div class="field">
    @if ($label)<label for="{{ $id }}">{{ $label }}</label>@endif
    <div @class(['input-ico' => $icon])>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        <input id="{{ $id }}" type="{{ $type }}" @if($name) name="{{ $name }}" @endif {{ $attributes->class(['input', 'is-invalid' => $err]) }} @if($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
    </div>
    @if ($err)<span class="error" id="{{ $id }}-err">{{ $err }}</span>@elseif ($hint)<span class="hint">{{ $hint }}</span>@endif
</div>
