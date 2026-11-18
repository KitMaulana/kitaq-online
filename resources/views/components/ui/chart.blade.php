@props(['type' => 'bar', 'labels' => [], 'datasets' => [], 'height' => 240, 'max' => null])
<div {{ $attributes }} wire:ignore x-data="kqChart(@js(['type' => $type, 'labels' => array_values($labels), 'datasets' => $datasets, 'max' => $max]))" style="height: {{ $height }}px; position: relative">
    <canvas x-ref="canvas" role="img" aria-label="Grafik"></canvas>
</div>
