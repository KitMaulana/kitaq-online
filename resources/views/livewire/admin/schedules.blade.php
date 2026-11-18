<div>
    <x-ui.page-header title="Jadwal" subtitle="Jadwal per kelas. Dibuka & ditutup otomatis oleh scheduler; bisa juga manual." :crumbs="['Dashboard' => route('admin.dashboard'), 'Jadwal']">
        <x-slot:actions>@if ($canManage)<x-ui.button icon="plus" wire:click="openCreate">Jadwal Baru</x-ui.button>@endif</x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="toolbar">
            <div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari nama ujian…" wire:model.live.debounce.300ms="search"></div>
            <select class="select" wire:model.live="filters.when" aria-label="Waktu"><option value="today">Hari ini</option><option value="upcoming">Mendatang</option><option value="past">Lampau</option><option value="all">Semua</option></select>
            <select class="select" wire:model.live="filters.status" aria-label="Status"><option value="">Semua status</option>@foreach (\App\Models\ExamSchedule::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <span class="spacer"></span>
            <label class="text-sm muted flex items-center gap-2">Perpanjang <input type="number" class="input" style="width:70px;height:32px" wire:model="extendMinutes" min="1" max="240"> mnt</label>
        </div>
        <x-ui.table>
            <x-slot:head>
                <x-ui.sort-th field="opens_at" :sort="$sortField" :dir="$sortDirection">Waktu</x-ui.sort-th>
                <th>Ujian</th><th>Kelas</th><th>Pengawas</th><th>Masuk</th>
                <x-ui.sort-th field="status" :sort="$sortField" :dir="$sortDirection">Status</x-ui.sort-th><th></th>
            </x-slot:head>
            @foreach ($schedules as $s)
                <tr wire:key="s-{{ $s->id }}">
                    <td class="tabular"><b>{{ $s->date->format('d/m/Y') }}</b><div class="text-xs muted">{{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }} {{ $s->session?->name }}</div></td>
                    <td><b>{{ $s->exam->name }}</b><div class="text-xs muted">{{ $s->exam->subject->name }} @if($s->ip_allowlist)· 🔒 IP sekolah @endif @if(! $s->token_required)· tanpa token @endif</div></td>
                    <td>{{ $s->classroom?->name ?? 'Peserta khusus' }}</td>
                    <td class="text-sm">{{ $s->supervisors->pluck('name')->join(', ') ?: '-' }}</td>
                    <td class="tabular">{{ $s->started_count }}</td>
                    <td><x-ui.status-chip :status="$s->status" /></td>
                    <td class="text-right whitespace-nowrap">
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.monitoring.show', $s)">Pantau</x-ui.button>
                        <x-ui.dropdown>
                            <a href="{{ route('admin.sessions', ['schedule' => $s->id]) }}"><x-ui.icon name="key" />Token</a>
                            <a href="{{ route('admin.projector', $s) }}" target="_blank"><x-ui.icon name="monitor" />Layar proyektor</a>
                            @if ($s->status === 'scheduled')<button type="button" wire:click="openNow({{ $s->id }})"><x-ui.icon name="play" />Mulai sekarang</button>@endif
                            @if ($s->status === 'open')<button type="button" wire:click="pause({{ $s->id }})"><x-ui.icon name="pause" />Jeda semua</button>@endif
                            @if ($s->status === 'paused')<button type="button" wire:click="resume({{ $s->id }})"><x-ui.icon name="play" />Lanjutkan</button>@endif
                            @if (in_array($s->status, ['open', 'paused']))<button type="button" wire:click="extend({{ $s->id }})"><x-ui.icon name="clock" />Perpanjang {{ $extendMinutes }} menit</button>@endif
                            @if ($canManage)<button type="button" wire:click="edit({{ $s->id }})"><x-ui.icon name="edit" />Ubah</button>@endif
                            <a href="{{ route('admin.minutes.show', $s) }}"><x-ui.icon name="file" />Berita acara</a>
                            @if ($s->status !== 'closed')<hr><button type="button" class="danger" wire:click="askClose({{ $s->id }})"><x-ui.icon name="x" />Tutup paksa</button>@endif
                            @if ($canManage && $s->started_count === 0)<button type="button" class="danger" wire:click="delete({{ $s->id }})" wire:confirm="Hapus jadwal ini?"><x-ui.icon name="trash" />Hapus</button>@endif
                        </x-ui.dropdown>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        @if ($schedules->isEmpty())<x-ui.empty-state icon="cal" title="Tidak ada jadwal" text="Ubah filter waktu atau buat jadwal baru." />@endif
        <x-ui.pagination :paginator="$schedules" />
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Ubah jadwal' : 'Jadwal baru'" size="lg">
        <form wire:submit="save" id="schedule-form" class="form-grid">
            <x-ui.select wire:model="form.exam_id" label="Ujian" :options="$exams" placeholder="Pilih ujian…" class="full" />
            <div class="field full"><label>Kelas {{ $editingId ? '' : '(boleh lebih dari satu — dibuat satu jadwal per kelas)' }}</label>
                <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:6px;max-height:150px;overflow:auto;border:1px solid var(--border);border-radius:10px;padding:10px">
                    @foreach ($classrooms as $id => $name)
                        @if ($editingId)<x-ui.radio wire:model="form.classrooms.0" value="{{ $id }}" :label="$name" />@else<x-ui.checkbox wire:model="form.classrooms" value="{{ $id }}" :label="$name" />@endif
                    @endforeach
                </div>
                @error('form.classrooms')<span class="error">{{ $message }}</span>@enderror
            </div>
            <x-ui.input type="date" wire:model="form.date" label="Tanggal" />
            <x-ui.select wire:model.live="form.exam_session_id" label="Sesi" :options="$sessions" placeholder="— tanpa sesi —" />
            <x-ui.input type="time" wire:model="form.opens" label="Jam buka" />
            <x-ui.input type="time" wire:model="form.closes" label="Jam tutup" />
            <x-ui.select wire:model="form.room_id" label="Ruang (opsional)" :options="$rooms" placeholder="—" />
            <div class="pt-6"><x-ui.switch wire:model="form.token_required" label="Wajib token" /></div>
            <div class="field full"><label>Pengawas</label>
                <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;max-height:120px;overflow:auto;border:1px solid var(--border);border-radius:10px;padding:10px">
                    @forelse ($supervisorOptions as $id => $name)<x-ui.checkbox wire:model="form.supervisors" value="{{ $id }}" :label="$name" />@empty<span class="muted text-sm">Belum ada pengguna pengawas.</span>@endforelse
                </div>
            </div>
            <div class="full"><x-ui.textarea wire:model="form.ip_allowlist" label="Batasi lokasi: IP publik sekolah (opsional)" hint="Satu IP/CIDR per baris, mis. 103.10.20.30 atau 103.10.20.0/24. Kosong = dari mana saja." rows="2" /></div>
            <div class="field full"><label>Peserta khusus (susulan/remedial)</label>
                <div class="flex gap-2"><input class="input" wire:model="specialNisn" placeholder="NISN siswa" wire:keydown.enter.prevent="addSpecial"><x-ui.button variant="secondary" wire:click="addSpecial">Tambah</x-ui.button></div>
                @error('specialNisn')<span class="error">{{ $message }}</span>@enderror
                @foreach ($form['special'] as $i => $sp)
                    <div class="flex items-center gap-2 mt-2 text-sm"><span class="flex-1">{{ $sp['name'] }} · {{ $sp['nisn'] }}</span>
                        <select class="select" style="width:130px;height:32px" wire:model="form.special.{{ $i }}.reason"><option value="susulan">Susulan</option><option value="remedial">Remedial</option><option value="khusus">Khusus</option></select>
                        <button type="button" class="icon-btn" aria-label="Hapus" wire:click="removeSpecial({{ $i }})"><x-ui.icon name="x" size="sm" /></button></div>
                @endforeach
            </div>
        </form>
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button><x-ui.button type="submit" form="schedule-form" icon="save" loading="save">Simpan</x-ui.button></x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm wire:model="confirmClose" title="Tutup paksa jadwal?" message="Semua siswa yang masih mengerjakan akan dikumpulkan paksa sekarang." action="close" confirm-label="Tutup paksa" />
</div>
