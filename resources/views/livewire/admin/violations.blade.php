<div>
    <x-ui.page-header title="Rekap Pelanggaran" subtitle="Semua event KITAQ Shield yang dicatat, diperingatkan, atau mengunci." :crumbs="['Dashboard' => route('admin.dashboard'), 'Pelanggaran']">
        <x-slot:actions><x-ui.button variant="secondary" icon="download" wire:click="export">Ekspor Excel</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <div class="card card-body mb-4 flex gap-3 flex-wrap items-end">
        <x-ui.input type="date" wire:model.live="date" label="Tanggal" />
        <div class="field flex-1" style="min-width:240px"><label for="vs">Jadwal</label>
            <select id="vs" class="select" wire:model.live="scheduleId"><option value="">Semua jadwal pada tanggal ini</option>@foreach ($schedules as $s)<option value="{{ $s->id }}">{{ $s->opens_at->format('H:i') }} · {{ $s->exam->name }} · {{ $s->classroom?->name ?? 'Khusus' }}</option>@endforeach</select></div>
    </div>
    <div class="grid g-2b mb-4">
        <x-ui.card title="Per jenis" :subtitle="fmt_num($data['total']) . ' event'" :padding="false">
            <x-ui.table :sticky="false">
                <x-slot:head><th>Jenis</th><th>Total</th><th>Peringatan</th><th>Kunci</th></x-slot:head>
                @foreach ($data['byType'] as $t)<tr><td>{{ $t['label'] }}</td><td class="tabular">{{ $t['total'] }}</td><td class="tabular">{{ $t['warn'] }}</td><td class="tabular" @if($t['lock']) style="color:var(--accent);font-weight:700" @endif>{{ $t['lock'] }}</td></tr>@endforeach
            </x-ui.table>
            @if (! $data['byType'])<x-ui.empty-state icon="shield" title="Tidak ada pelanggaran" />@endif
        </x-ui.card>
        <x-ui.card title="Grafik per jam">
            <x-ui.chart type="bar" :labels="$hourLabels" :datasets="[['label' => 'Event', 'data' => $hourData]]" wire:key="vh-{{ $date }}-{{ $scheduleId }}" />
        </x-ui.card>
    </div>
    <x-ui.card title="Per siswa" :padding="false">
        <x-ui.table>
            <x-slot:head><th>Siswa</th><th>Ujian</th><th>Total</th><th>Peringatan</th><th>Kunci</th><th>Status</th><th></th></x-slot:head>
            @foreach ($students as $r)
                <tr><td><b>{{ $r['attempt']->student->name }}</b><div class="text-xs muted">{{ $r['attempt']->student->nisn }} · {{ $r['attempt']->student->classroom?->name }}</div></td>
                    <td class="text-sm">{{ $r['attempt']->schedule->exam->name }}</td>
                    <td class="tabular">{{ $r['total'] }}</td><td class="tabular">{{ $r['warn'] }}</td><td class="tabular">{{ $r['lock'] }}</td>
                    <td><x-ui.status-chip :status="$r['attempt']->status" /></td>
                    <td><x-ui.button size="sm" variant="ghost" :href="route('admin.monitoring.show', $r['attempt']->exam_schedule_id)">Pantau</x-ui.button></td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
