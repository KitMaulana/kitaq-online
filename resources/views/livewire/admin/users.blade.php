<div>
    <x-ui.page-header title="Pengguna" subtitle="Admin, operator, guru, dan pengawas. Password staf minimal 10 karakter." :crumbs="['Dashboard' => route('admin.dashboard'), 'Pengguna']">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('admin.templates', ['file' => 'pengguna'])">Unduh Template</x-ui.button>
            <x-ui.button variant="secondary" icon="upload" wire:click="$set('showImport', true)">Import</x-ui.button>
            <x-ui.button icon="plus" wire:click="openCreate">Tambah</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($newPassword)
        <x-ui.alert tone="warning" title="Password sementara (catat sekarang, tidak ditampilkan lagi)"><span class="code text-base">{{ $newPassword }}</span> — pengguna wajib menggantinya saat login.
            <x-slot:action><x-ui.button size="sm" variant="secondary" wire:click="$set('newPassword', null)">Tutup</x-ui.button></x-slot:action>
        </x-ui.alert>
    @endif

    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari nama / username…" wire:model.live.debounce.300ms="search"></div>
            <select class="select" wire:model.live="filters.role" aria-label="Filter role"><option value="">Semua role</option>@foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        </div>
        <x-ui.table>
            <x-slot:head>
                <x-ui.sort-th field="name" :sort="$sortField" :dir="$sortDirection">Nama</x-ui.sort-th>
                <x-ui.sort-th field="username" :sort="$sortField" :dir="$sortDirection">Username</x-ui.sort-th>
                <th>Role</th><th>Mapel</th><th>2FA</th><th>Status</th>
                <x-ui.sort-th field="last_login_at" :sort="$sortField" :dir="$sortDirection">Login terakhir</x-ui.sort-th><th></th>
            </x-slot:head>
            @foreach ($users as $u)
                <tr wire:key="u-{{ $u->id }}">
                    <td><div class="who"><x-ui.avatar :name="$u->name" /><span><b>{{ $u->name }}</b><small>{{ $u->email ?? '-' }}</small></span></div></td>
                    <td class="code">{{ $u->username }}</td>
                    <td><x-ui.badge tone="brand">{{ $u->roleLabel() }}</x-ui.badge></td>
                    <td class="text-sm">{{ $u->subjects->pluck('code')->join(', ') ?: '-' }}</td>
                    <td>{!! $u->hasTwoFactor() ? '<span class="badge tone-success">Aktif</span>' : '<span class="muted">-</span>' !!}</td>
                    <td>@if ($u->is_active)<x-ui.badge tone="success" dot>Aktif</x-ui.badge>@else<x-ui.badge dot>Nonaktif</x-ui.badge>@endif @if ($u->must_change_password)<x-ui.badge tone="warning">Wajib ganti</x-ui.badge>@endif</td>
                    <td class="muted text-sm">{{ $u->last_login_at?->diffForHumans() ?? '-' }}</td>
                    <td class="text-right"><x-ui.dropdown>
                        <button type="button" wire:click="edit({{ $u->id }})"><x-ui.icon name="edit" />Ubah</button>
                        <button type="button" wire:click="resetPassword({{ $u->id }})"><x-ui.icon name="key" />Reset password</button>
                        <hr><button type="button" class="danger" wire:click="askDelete({{ $u->id }})"><x-ui.icon name="trash" />Hapus</button>
                    </x-ui.dropdown></td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$users" />
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Ubah pengguna' : 'Tambah pengguna'" size="lg">
        <form wire:submit="save" id="user-form" class="form-grid">
            <x-ui.input wire:model="form.name" label="Nama" />
            <x-ui.input wire:model="form.username" label="Username" />
            <x-ui.input wire:model="form.email" type="email" label="Email (opsional)" />
            <x-ui.select wire:model.live="form.role" label="Role" :options="\App\Models\User::ROLES" />
            <x-ui.input wire:model="form.password" type="password" :label="$editingId ? 'Password baru (kosongkan bila tetap)' : 'Password'" hint="Min. 10 karakter, huruf + angka. Wajib diganti saat login pertama." autocomplete="new-password" />
            <div class="pt-6"><x-ui.switch wire:model="form.is_active" label="Akun aktif" /></div>
            @error('form.is_active')<span class="error full">{{ $message }}</span>@enderror
            @if ($form['role'] === 'guru')
                <div class="field full"><label>Mapel yang diampu</label>
                    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;border:1px solid var(--border);border-radius:10px;padding:10px">
                        @forelse ($subjects as $id => $name)<x-ui.checkbox wire:model="form.subjects" value="{{ $id }}" :label="$name" />@empty<span class="muted text-sm">Belum ada mapel.</span>@endforelse
                    </div>
                </div>
            @endif
        </form>
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button><x-ui.button type="submit" form="user-form" icon="save" loading="save">Simpan</x-ui.button></x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showImport" title="Import pengguna" size="lg">
        @include('livewire.admin.partials.import-wizard', [
            'templateUrl' => route('admin.templates', ['file' => 'pengguna']),
            'doneUrl' => route('admin.users'),
            'columns' => 'Kolom: nama*, username*, email, password* (min. 8), role* (super_admin/operator/guru/pengawas), kode_mapel (guru, pisahkan koma).',
            'previewCols' => ['name' => 'Nama', 'username' => 'Username', 'role' => 'Role'],
        ])
    </x-ui.modal>

    <x-ui.confirm wire:model="confirmDelete" title="Hapus pengguna?" message="Akun staf akan dihapus permanen." action="delete" confirm-label="Hapus" />
</div>
