<div>
    <x-ui.page-header title="Bank Soal" subtitle="6 tipe soal: PG, PG Kompleks, Benar/Salah, Menjodohkan, Isian Singkat, Uraian." :crumbs="['Dashboard' => route('admin.dashboard'), 'Bank Soal']">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('admin.templates', ['file' => 'soal'])">Template Soal</x-ui.button>
            <x-ui.button variant="secondary" icon="image" :href="route('admin.templates', ['file' => 'gambar'])">Contoh ZIP Gambar</x-ui.button>
            <x-ui.button icon="plus" wire:click="openCreate">Bank Soal Baru</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari bank soal…" wire:model.live.debounce.300ms="search"></div>
            <select class="select" wire:model.live="filters.subject" aria-label="Filter mapel"><option value="">Semua mapel</option>@foreach ($subjects as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
        </div>
        <x-ui.table>
            <x-slot:head>
                <x-ui.sort-th field="name" :sort="$sortField" :dir="$sortDirection">Bank soal</x-ui.sort-th>
                <x-ui.sort-th field="subject_id" :sort="$sortField" :dir="$sortDirection">Mapel</x-ui.sort-th>
                <x-ui.sort-th field="questions_count" :sort="$sortField" :dir="$sortDirection">Soal</x-ui.sort-th>
                <th>Pemilik</th>
                <x-ui.sort-th field="updated_at" :sort="$sortField" :dir="$sortDirection">Diperbarui</x-ui.sort-th><th></th>
            </x-slot:head>
            @foreach ($banks as $b)
                <tr wire:key="b-{{ $b->id }}">
                    <td><a href="{{ route('admin.banks.show', $b) }}" class="who"><span class="avatar" style="border-radius:10px"><x-ui.icon name="fq" /></span><span><b>{{ $b->name }}</b><small>{{ $b->grade ? 'Kelas ' . $b->grade : '' }}</small></span></a></td>
                    <td>{{ $b->subject->name }}</td>
                    <td class="tabular">{{ $b->questions_count }}</td>
                    <td class="text-sm">{{ $b->owner?->name ?? '-' }}</td>
                    <td class="muted text-sm">{{ $b->updated_at->diffForHumans() }}</td>
                    <td class="text-right"><x-ui.dropdown>
                        <a href="{{ route('admin.banks.show', $b) }}"><x-ui.icon name="eye" />Buka</a>
                        <button type="button" wire:click="edit({{ $b->id }})"><x-ui.icon name="edit" />Ubah</button>
                        <hr><button type="button" class="danger" wire:click="askDelete({{ $b->id }})"><x-ui.icon name="trash" />Hapus</button>
                    </x-ui.dropdown></td>
                </tr>
            @endforeach
        </x-ui.table>
        @if ($banks->isEmpty())<x-ui.empty-state icon="fq" title="Belum ada bank soal" text="Buat bank soal per mapel, lalu tambah soal atau import dari Excel + ZIP gambar."><x-ui.button icon="plus" wire:click="openCreate">Bank Soal Baru</x-ui.button></x-ui.empty-state>@endif
        <x-ui.pagination :paginator="$banks" />
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Ubah bank soal' : 'Bank soal baru'">
        <form wire:submit="save" id="bank-form" class="space-y-4">
            <x-ui.input wire:model="form.name" label="Nama bank soal" placeholder="Matematika XII — PTS Ganjil" />
            <div class="form-grid">
                <x-ui.select wire:model="form.subject_id" label="Mata pelajaran" :options="$subjects" placeholder="Pilih…" />
                <x-ui.input wire:model="form.grade" label="Tingkat (opsional)" placeholder="XII" />
            </div>
            <x-ui.textarea wire:model="form.description" label="Keterangan (opsional)" />
        </form>
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button><x-ui.button type="submit" form="bank-form" icon="save" loading="save">Simpan</x-ui.button></x-slot:footer>
    </x-ui.modal>
    <x-ui.confirm wire:model="confirmDelete" title="Hapus bank soal?" message="Semua soal di dalamnya ikut terhapus." action="delete" confirm-label="Hapus" />
</div>
