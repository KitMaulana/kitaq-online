@props(['count' => 0])
<div @class(['bulkbar', 'show' => $count > 0]) role="region" aria-label="Aksi massal">
    <b>{{ $count }} dipilih</b>
    {{ $slot }}
    <button type="button" class="btn btn-ghost sm" wire:click="clearSelection">Batal</button>
</div>
