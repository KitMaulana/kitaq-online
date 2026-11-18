@props(['steps' => [], 'current' => 1])
<div class="stepper" aria-label="Langkah">
    @foreach ($steps as $i => $label)
        @php $n = $i + 1; @endphp
        <div @class(['step', 'on' => $n === $current, 'done' => $n < $current])><span class="n">@if($n < $current)✓@else{{ $n }}@endif</span>{{ $label }}</div>
        @if (! $loop->last)<div class="step-line"></div>@endif
    @endforeach
</div>
