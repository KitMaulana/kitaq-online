{{-- Konfirmasi aksi destruktif (Livewire): <x-ui.confirm wire:model="confirmDelete" title="Hapus siswa?" message="..." action="delete" confirm-label="Hapus" /> --}}
@props(['title' => 'Yakin?', 'message' => '', 'confirmLabel' => 'Ya, lanjutkan', 'tone' => 'danger', 'action' => null])
<x-ui.modal {{ $attributes }} :title="$title" size="">
    <div class="big-ico tone-{{ $tone }}"><x-ui.icon name="warn" size="lg" /></div>
    <p class="muted">{{ $message }}</p>{{ $slot }}
    <x-slot:footer>
        <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
        <x-ui.button :variant="$tone === 'danger' ? 'danger' : 'primary'" wire:click="{{ $action }}" :loading="$action">{{ $confirmLabel }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
