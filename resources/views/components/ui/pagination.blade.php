{{-- Footer tabel: jumlah per halaman + navigasi halaman (Livewire WithDataTable). --}}
@props(['paginator'])
<div class="tfoot">
    <div class="flex items-center gap-2">
        <span>Menampilkan {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ fmt_num($paginator->total()) }}</span>
        <select class="select" style="width:auto;height:32px" wire:model.live="perPage" aria-label="Per halaman">
            @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
        </select>
    </div>
    @if ($paginator->hasPages())
        <div class="pager">
            <button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage()) aria-label="Sebelumnya">‹</button>
            @php $cur = $paginator->currentPage(); $last = $paginator->lastPage(); @endphp
            @foreach (array_unique(array_filter([1, $cur - 1, $cur, $cur + 1, $last], fn ($p) => $p >= 1 && $p <= $last)) as $p)
                <button type="button" wire:click="gotoPage({{ $p }})" @class(['on' => $p === $cur])>{{ $p }}</button>
            @endforeach
            <button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) aria-label="Berikutnya">›</button>
        </div>
    @endif
</div>
