<div>
    <x-ui.page-header title="Ujian" subtitle="Ujian = bank soal + aturan. Jadwal per kelas dibuat di menu Jadwal." :crumbs="['Dashboard' => route('admin.dashboard'), 'Ujian']">
        <x-slot:actions><x-ui.button icon="plus" :href="route('admin.exams.create')">Ujian Baru</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.card :padding="false">
        <div class="toolbar"><div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari ujian…" wire:model.live.debounce.300ms="search"></div></div>
        <x-ui.table>
            <x-slot:head>
                <x-ui.sort-th field="name" :sort="$sortField" :dir="$sortDirection">Ujian</x-ui.sort-th>
                <x-ui.sort-th field="type" :sort="$sortField" :dir="$sortDirection">Jenis</x-ui.sort-th>
                <th>Bank soal</th>
                <x-ui.sort-th field="duration_minutes" :sort="$sortField" :dir="$sortDirection">Durasi</x-ui.sort-th>
                <th>Keketatan</th><th>Jadwal</th><th></th>
            </x-slot:head>
            @foreach ($exams as $e)
                @php $s = $e->resolvedSettings(); @endphp
                <tr wire:key="e-{{ $e->id }}">
                    <td><a href="{{ route('admin.exams.edit', $e) }}"><b style="color:var(--text)">{{ $e->name }}</b></a><div class="text-xs muted">{{ $e->subject->name }} · KKM {{ $e->kkm }}</div></td>
                    <td>{{ $e->type }}</td>
                    <td class="text-sm">{{ $e->bank?->name }} <span class="muted">({{ $e->question_count ?: 'semua' }} soal)</span></td>
                    <td class="tabular">{{ $e->duration_minutes }} mnt</td>
                    <td><x-ui.badge :tone="['longgar' => 'info', 'standar' => 'brand', 'ketat' => 'danger'][$s['preset']]">{{ ucfirst($s['preset']) }}</x-ui.badge> @if($s['canvas_mode'])<x-ui.badge>Kanvas</x-ui.badge>@endif</td>
                    <td class="tabular">{{ $e->schedules_count }}</td>
                    <td class="text-right"><x-ui.dropdown>
                        <a href="{{ route('admin.exams.edit', $e) }}"><x-ui.icon name="edit" />Ubah & pengaturan</a>
                        <a href="{{ route('admin.schedules', ['create' => 1, 'exam' => $e->id]) }}"><x-ui.icon name="cal" />Buat jadwal</a>
                        <button type="button" wire:click="duplicate({{ $e->id }})"><x-ui.icon name="copy" />Gandakan</button>
                        <hr><button type="button" class="danger" wire:click="askDelete({{ $e->id }})"><x-ui.icon name="trash" />Hapus</button>
                    </x-ui.dropdown></td>
                </tr>
            @endforeach
        </x-ui.table>
        @if ($exams->isEmpty())<x-ui.empty-state icon="pencil" title="Belum ada ujian" text="Buat ujian dari bank soal, atur durasi, keketatan KITAQ Shield, lalu jadwalkan per kelas."><x-ui.button icon="plus" :href="route('admin.exams.create')">Ujian Baru</x-ui.button></x-ui.empty-state>@endif
        <x-ui.pagination :paginator="$exams" />
    </x-ui.card>
    <x-ui.confirm wire:model="confirmDelete" title="Hapus ujian?" message="Ujian beserta jadwalnya akan dihapus." action="delete" confirm-label="Hapus" />
</div>
