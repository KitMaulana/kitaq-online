@props(['rows' => 4])
<div {{ $attributes->class(['p-4 space-y-3']) }} aria-busy="true" aria-label="Memuat">
    @for ($i = 0; $i < $rows; $i++)<div class="skeleton" style="width: {{ 100 - ($i % 3) * 15 }}%"></div>@endfor
</div>
