<div>
    <x-ui.page-header title="Siswa" subtitle="Login siswa memakai NISN + tanggal lahir (DDMMYYYY)." :crumbs="['Dashboard' => route('admin.dashboard'), 'Siswa']">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('admin.templates', ['file' => 'siswa'])">Unduh Template</x-ui.button>
            <x-ui.button variant="secondary" icon="upload" :href="route('admin.students.import')">Import</x-ui.button>
            <x-ui.button variant="secondary" icon="printer" :href="route('admin.students.cards', array_filter(['classroom' => $filters['classroom'] ?? null]))" target="_blank">Kartu Login</x-ui.button>
            <x-ui.button icon="plus" wire:click="openCreate">Tambah</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari nama / NISN / NIS…" wire:model.live.debounce.300ms="search" aria-label="Cari siswa"></div>
            <select class="select" wire:model.live="filters.classroom" aria-label="Filter kelas">
                <option value="">Semua kelas</option>
                @foreach ($classrooms as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select class="select" wire:model.live="filters.status" aria-label="Filter status">
                <option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option><option value="frozen">Dibekukan</option>
            </select>
            <span class="muted text-sm">{{ fmt_num($students->total()) }} siswa</span>
            <span class="spacer"></span>
            <x-ui.button variant="ghost" size="sm" icon="download" wire:click="export">Ekspor</x-ui.button>
        </div>
        <div wire:loading.delay wire:target="search,filters,perPage,sortBy,gotoPage" class="w-full"><x-ui.progress :value="60" /></div>
        <x-ui.table>
            <x-slot:head>
                <th style="width:40px"><input type="checkbox" class="check" aria-label="Pilih semua di halaman" @checked($selectPage) wire:click="toggleSelectPage(@js($students->pluck('id')->all()))"></th>
                <x-ui.sort-th field="name" :sort="$sortField" :dir="$sortDirection">Siswa</x-ui.sort-th>
                <x-ui.sort-th field="nisn" :sort="$sortField" :dir="$sortDirection">NISN</x-ui.sort-th>
                <x-ui.sort-th field="classroom_id" :sort="$sortField" :dir="$sortDirection">Kelas</x-ui.sort-th>
                <th>Tanggal lahir</th>
                <th>Status</th>
                <x-ui.sort-th field="last_login_at" :sort="$sortField" :dir="$sortDirection">Login terakhir</x-ui.sort-th>
                <th></th>
            </x-slot:head>
            @foreach ($students as $s)
                <tr wire:key="s-{{ $s->id }}" @class(['sel' => in_array((string) $s->id, $selected, true)])>
                    <td><input type="checkbox" class="check" value="{{ $s->id }}" wire:model.live="selected" aria-label="Pilih {{ $s->name }}"></td>
                    <td><button type="button" class="who" style="background:none;border:0;padding:0;color:inherit;text-align:left;cursor:pointer" wire:click="show({{ $s->id }})"><x-ui.avatar :name="$s->name" /><span><b>{{ $s->name }}</b><small>{{ $s->gender === 'P' ? 'Perempuan' : 'Laki-laki' }}{{ $s->nis ? ' · NIS ' . $s->nis : '' }}</small></span></button></td>
                    <td class="tabular code">{{ $s->nisn }}</td>
                    <td>{{ $s->classroom?->name ?? '-' }}</td>
                    <td class="tabular">{{ $s->maskedBirthDate() }}</td>
                    <td>
                        @if ($s->isFrozen())<x-ui.badge tone="danger" dot>Dibekukan</x-ui.badge>
                        @elseif ($s->is_active)<x-ui.badge tone="success" dot>Aktif</x-ui.badge>
                        @else<x-ui.badge tone="neutral" dot>Nonaktif</x-ui.badge>@endif
                    </td>
                    <td class="muted text-sm">{{ $s->last_login_at?->diffForHumans() ?? '-' }}</td>
                    <td class="text-right">
                        <x-ui.dropdown>
                            <button type="button" wire:click="show({{ $s->id }})"><x-ui.icon name="eye" />Lihat</button>
                            <button type="button" wire:click="edit({{ $s->id }})"><x-ui.icon name="edit" />Ubah</button>
                            <button type="button" wire:click="resetPassword({{ $s->id }})"><x-ui.icon name="key" />Reset password</button>
                            @if ($s->isFrozen())<button type="button" wire:click="unfreeze({{ $s->id }})"><x-ui.icon name="unlock" />Buka pembekuan</button>@endif
                            <hr><button type="button" class="danger" wire:click="askDelete({{ $s->id }})"><x-ui.icon name="trash" />Hapus</button>
                        </x-ui.dropdown>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        @if ($students->isEmpty())
            <x-ui.empty-state icon="users" title="Belum ada siswa" text="Tambah satu per satu atau import dari Excel (template_siswa.xlsx).">
                <x-ui.button icon="upload" :href="route('admin.students.import')">Import Excel</x-ui.button>
            </x-ui.empty-state>
        @endif
        <x-ui.pagination :paginator="$students" />
    </x-ui.card>

    <x-ui.bulk-bar :count="count($selected)">
        <button type="button" class="btn btn-ghost sm" wire:click="resetPassword"><x-ui.icon name="key" />Reset password</button>
        <button type="button" class="btn btn-ghost sm" wire:click="$set('showMove', true)"><x-ui.icon name="swap" />Pindah kelas</button>
        <button type="button" class="btn btn-ghost sm" wire:click="setActive(true)">Aktifkan</button>
        <button type="button" class="btn btn-ghost sm" wire:click="setActive(false)">Nonaktifkan</button>
        <button type="button" class="btn btn-ghost sm" style="color:#ff8a84" wire:click="$set('confirmDelete', true)"><x-ui.icon name="trash" />Hapus</button>
    </x-ui.bulk-bar>

    {{-- Form tambah/ubah --}}
    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Ubah siswa' : 'Tambah siswa'" size="lg">
        <form wire:submit="save" id="student-form" class="form-grid">
            <x-ui.input wire:model="form.nisn" label="NISN" inputmode="numeric" maxlength="10" hint="10 digit; nol di depan dipertahankan" :error="$errors->first('form.nisn')" />
            <x-ui.input wire:model="form.nis" label="NIS (opsional)" />
            <x-ui.input wire:model="form.name" label="Nama lengkap" class="full" :error="$errors->first('form.name')" />
            <x-ui.select wire:model="form.gender" label="Jenis kelamin" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" />
            <x-ui.select wire:model="form.classroom_id" label="Kelas" :options="$classrooms" placeholder="Pilih kelas…" :error="$errors->first('form.classroom_id')" />
            <x-ui.input wire:model="form.birth_place" label="Tempat lahir (opsional)" />
            <x-ui.input type="date" wire:model="form.birth_date" label="Tanggal lahir" hint="Menjadi password DDMMYYYY" :error="$errors->first('form.birth_date')" />
            <div class="full"><x-ui.switch wire:model="form.is_active" label="Akun aktif" /></div>
            <div class="full"><b class="text-sm">Akomodasi</b></div>
            <x-ui.input type="number" min="0" max="240" wire:model="form.extra_minutes" label="Tambahan waktu (menit)" />
            <div class="space-y-2 pt-6">
                <x-ui.switch wire:model="form.large_font" label="Huruf besar" />
                <x-ui.switch wire:model="form.disable_canvas" label="Nonaktifkan Mode Soal Kanvas" hint="Untuk pengguna pembaca layar" />
            </div>
            <div class="full"><x-ui.file-drop wire:model="photo" accept="image/*" label="Foto (opsional) — tampil di monitoring" /></div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="student-form" icon="save" loading="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Pindah kelas massal --}}
    <x-ui.modal wire:model="showMove" title="Pindah kelas">
        <x-ui.select wire:model="moveTo" label="Kelas tujuan" :options="$classrooms" placeholder="Pilih kelas…" :error="$errors->first('moveTo')" />
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button><x-ui.button wire:click="moveClass" loading="moveClass">Pindahkan {{ count($selected) }} siswa</x-ui.button></x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm wire:model="confirmDelete" title="Hapus siswa?" :message="($deleteId ? 'Siswa ini' : count($selected) . ' siswa terpilih') . ' beserta riwayat ujiannya akan dihapus permanen.'" action="delete" confirm-label="Hapus" />

    {{-- Drawer detail --}}
    <x-ui.drawer wire:model="showDetail" title="Detail siswa">
        @if ($detail)
            <div class="flex items-center gap-3 mb-4"><x-ui.avatar :name="$detail->name" :size="52" /><div><b class="text-lg">{{ $detail->name }}</b><p class="muted text-sm">{{ $detail->classroom?->name }}</p></div></div>
            <dl class="kv-list mb-5">
                <dt>NISN</dt><dd class="code">{{ $detail->nisn }}</dd>
                <dt>NIS</dt><dd>{{ $detail->nis ?? '-' }}</dd>
                <dt>Tanggal lahir</dt><dd>{{ $detail->maskedBirthDate() }}</dd>
                <dt>Status</dt><dd>{{ $detail->is_active ? 'Aktif' : 'Nonaktif' }}{{ $detail->isFrozen() ? ' · dibekukan s.d. ' . $detail->locked_until->format('H:i') : '' }}</dd>
                <dt>Tambahan waktu</dt><dd>{{ (int) $detail->accommodation('extra_minutes', 0) }} menit</dd>
            </dl>
            <b class="text-sm">Login terakhir</b>
            <ul class="timeline mb-5">
                @forelse ($logins as $l)<li><time>{{ $l->created_at->format('d/m H:i') }}</time><span>{{ $l->ip }} · {{ device_label($l->properties['device'] ?? null) }}</span></li>@empty<li class="muted">Belum pernah login.</li>@endforelse
            </ul>
            <b class="text-sm">Riwayat ujian</b>
            <ul class="timeline">
                @forelse ($detail->attempts as $a)<li><time>{{ $a->started_at?->format('d/m') ?? '-' }}</time><span class="flex-1">{{ $a->schedule?->exam?->name }}</span><x-ui.status-chip :status="$a->status" /></li>@empty<li class="muted">Belum ada.</li>@endforelse
            </ul>
        @endif
    </x-ui.drawer>
</div>
