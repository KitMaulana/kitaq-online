@props(['field', 'sort' => null, 'dir' => 'asc'])
<th>
    <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex items-center gap-1 uppercase" style="background:none;border:0;color:inherit;font:inherit;cursor:pointer">
        {{ $slot }}
        @if ($sort === $field)<span aria-hidden="true">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
    </button>
</th>
