<div>
    <x-ui.page-header :title="$title" :subtitle="$subtitle" :crumbs="['Dashboard' => route('admin.dashboard'), $title]">
        <x-slot:actions>
            @isset($extraActions){!! $extraActions !!}@endisset
            <x-ui.button icon="plus" wire:click="openCreate">Tambah</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (method_exists($this, 'topView') && $this->topView())@include($this->topView())@endif

    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari…" wire:model.live.debounce.300ms="search" aria-label="Cari"></div>
            <span class="muted text-sm">{{ fmt_num($rows->total()) }} data</span>
        </div>
        <x-ui.table>
            <x-slot:head>
                <th style="width:40px"><input type="checkbox" class="check" aria-label="Pilih semua" @checked($selectPage) wire:click="toggleSelectPage(@js($rows->pluck('id')->all()))"></th>
                @foreach ($cols as $key => $label)
                    @if ($this->isSortable($key))
                        <x-ui.sort-th :field="$key" :sort="$sortField" :dir="$sortDirection">{{ $label }}</x-ui.sort-th>
                    @else<th>{{ $label }}</th>@endif
                @endforeach
                <th></th>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr wire:key="r-{{ $row->id }}">
                    <td><input type="checkbox" class="check" value="{{ $row->id }}" wire:model.live="selected" aria-label="Pilih baris"></td>
                    @foreach ($cols as $key => $label)<td>{!! $this->cell($row, $key) !!}</td>@endforeach
                    <td class="text-right">
                        <x-ui.dropdown>
                            <button type="button" wire:click="edit({{ $row->id }})"><x-ui.icon name="edit" />Ubah</button>
                            <hr><button type="button" class="danger" wire:click="askDelete({{ $row->id }})"><x-ui.icon name="trash" />Hapus</button>
                        </x-ui.dropdown>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        @if ($rows->isEmpty())
            <x-ui.empty-state :title="'Belum ada ' . strtolower($single)" text="Klik Tambah untuk membuat data pertama.">
                <x-ui.button icon="plus" wire:click="openCreate">Tambah</x-ui.button>
            </x-ui.empty-state>
        @endif
        <x-ui.pagination :paginator="$rows" />
    </x-ui.card>

    <x-ui.bulk-bar :count="count($selected)">
        <button type="button" class="btn btn-ghost sm" style="color:#ff8a84" wire:click="askDelete"><x-ui.icon name="trash" />Hapus</button>
    </x-ui.bulk-bar>

    <x-ui.modal wire:model="showForm" :title="($editingId ? 'Ubah ' : 'Tambah ') . strtolower($single)">
        <form wire:submit="save" id="crud-form-{{ $this->getId() }}" class="form-grid">
            @foreach ($fieldDefs as $key => $f)
                @php $type = $f['type'] ?? 'text'; $err = $errors->first('form.' . $key); @endphp
                <div @class(['full' => $f['full'] ?? false])>
                    @if ($type === 'select')
                        <x-ui.select wire:model="form.{{ $key }}" :label="$f['label']" :options="$f['options'] ?? []" placeholder="Pilih…" :hint="$f['hint'] ?? null" :error="$err" />
                    @elseif ($type === 'multiselect')
                        <div class="field"><label>{{ $f['label'] }}</label>
                            <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;max-height:180px;overflow:auto;border:1px solid var(--border);border-radius:10px;padding:10px">
                                @forelse ($f['options'] ?? [] as $v => $t)<x-ui.checkbox wire:model="form.{{ $key }}" value="{{ $v }}" :label="$t" />@empty<span class="muted text-sm">Belum ada pilihan.</span>@endforelse
                            </div>
                            @if (! empty($f['hint']))<span class="hint">{{ $f['hint'] }}</span>@endif
                        </div>
                    @elseif ($type === 'switch')
                        <div class="pt-6"><x-ui.switch wire:model="form.{{ $key }}" :label="$f['label']" :hint="$f['hint'] ?? null" /></div>
                    @else
                        <x-ui.input wire:model="form.{{ $key }}" :type="$type" :label="$f['label']" :hint="$f['hint'] ?? null" :error="$err" />
                    @endif
                </div>
            @endforeach
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="crud-form-{{ $this->getId() }}" icon="save" loading="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm wire:model="confirmDelete" :title="'Hapus ' . strtolower($single) . '?'" message="Data yang dihapus tidak dapat dikembalikan." action="delete" confirm-label="Hapus" />
</div>
