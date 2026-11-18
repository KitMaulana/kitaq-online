@props(['label', 'value', 'icon' => 'activity', 'tone' => 'brand', 'delta' => null, 'deltaTone' => null])
<div {{ $attributes->class(['card stat']) }}>
    <div class="chip tone-{{ $tone }}"><x-ui.icon :name="$icon" size="lg" /></div>
    <div>
        <div class="label">{{ $label }}</div>
        <div class="value tabular">{{ $value }}</div>
        @if ($delta)<div class="delta {{ $deltaTone }}">{{ $delta }}</div>@endif
    </div>
</div>
