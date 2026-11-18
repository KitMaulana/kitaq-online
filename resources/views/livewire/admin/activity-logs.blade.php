<div>
    <x-ui.page-header title="Log Aktivitas" subtitle="Jejak audit: buka kunci, ubah nilai, reset login, ekspor data, login." :crumbs="['Dashboard' => route('admin.dashboard'), 'Log Aktivitas']" />
    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari keterangan / IP…" wire:model.live.debounce.300ms="search"></div>
            <select class="select" wire:model.live="filters.action" aria-label="Jenis"><option value="">Semua jenis</option>@foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select>
        </div>
        <x-ui.table>
            <x-slot:head><x-ui.sort-th field="created_at" :sort="$sortField" :dir="$sortDirection">Waktu</x-ui.sort-th><th>Pelaku</th><th>Aksi</th><th>Keterangan</th><th>IP</th></x-slot:head>
            @foreach ($logs as $l)
                <tr><td class="tabular text-sm whitespace-nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="text-sm">{{ $l->user?->name ?? ($l->student ? $l->student->name . ' (siswa)' : 'Sistem') }}</td>
                    <td><x-ui.badge tone="info">{{ $l->action }}</x-ui.badge></td>
                    <td class="text-sm" style="max-width:420px">{{ $l->description }}</td>
                    <td class="tabular text-sm">{{ $l->ip }}</td></tr>
            @endforeach
        </x-ui.table>
        @if ($logs->isEmpty())<x-ui.empty-state icon="monitor" title="Belum ada aktivitas" />@endif
        <x-ui.pagination :paginator="$logs" />
    </x-ui.card>
</div>
