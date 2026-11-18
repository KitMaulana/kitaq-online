<div>
    <x-ui.page-header title="Rekap Nilai" subtitle="Nilai dihitung server; uraian menunggu koreksi guru." :crumbs="['Dashboard' => route('admin.dashboard'), 'Rekap Nilai']">
        <x-slot:actions>
            @if ($schedule)
                <x-ui.button variant="secondary" icon="download" wire:click="export('recap_xlsx')">Excel</x-ui.button>
                <x-ui.button variant="secondary" icon="file" wire:click="export('recap_pdf')">PDF</x-ui.button>
                <x-ui.button variant="secondary" icon="download" wire:click="export('erapor_xlsx')">Format e-Rapor</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.admin.partials.schedule-picker')

    @if ($schedule)
        <div class="grid g-stats">
            <x-ui.stat label="Rata-rata" :value="$summary['avg'] !== null ? fmt_num($summary['avg'], 1) : '-'" icon="bars" />
            <x-ui.stat label="Tertinggi" :value="$summary['max'] !== null ? fmt_num($summary['max'], 0) : '-'" icon="trend" tone="success" />
            <x-ui.stat label="Terendah" :value="$summary['min'] !== null ? fmt_num($summary['min'], 0) : '-'" icon="trend" tone="warning" />
            <x-ui.stat label="% Tuntas (KKM {{ $schedule->exam->kkm }})" :value="$summary['pass_pct'] !== null ? fmt_num($summary['pass_pct'], 1) . '%' : '-'" icon="award" tone="info" />
            <x-ui.stat label="Hadir" :value="$rows->where('present', true)->count() . '/' . $rows->count()" icon="users" tone="neutral" />
        </div>
        <div class="grid g-2">
            <x-ui.card title="Peringkat" :padding="false">
                <x-ui.table>
                    <x-slot:head><th>#</th><th>Siswa</th><th>B/S/K</th><th>Nilai</th><th>Tuntas</th><th>Pelanggaran</th></x-slot:head>
                    @foreach ($rows as $i => $r)
                        <tr>
                            <td class="tabular">{{ $r['score'] !== null ? $i + 1 : '-' }}</td>
                            <td><b>{{ $r['name'] }}</b><div class="text-xs muted tabular">{{ $r['nisn'] }} · {{ $r['class'] }}</div></td>
                            <td class="tabular text-sm">{{ $r['correct'] ?? '-' }}/{{ $r['wrong'] ?? '-' }}/{{ $r['blank'] ?? '-' }}</td>
                            <td class="tabular font-bold">{{ $r['score'] !== null ? fmt_num($r['score'], 1) : '-' }} @if($r['needs_grading'])<x-ui.badge tone="warning">uraian</x-ui.badge>@endif</td>
                            <td>@if ($r['passed'] === true)<x-ui.badge tone="success">Tuntas</x-ui.badge>@elseif ($r['passed'] === false)<x-ui.badge tone="danger">Belum</x-ui.badge>@else<x-ui.status-chip :status="$r['present'] ? $r['status'] : 'not_started'" />@endif</td>
                            <td class="tabular">{{ $r['violations'] }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
            <x-ui.card title="Distribusi nilai">
                <x-ui.chart type="bar" :labels="array_keys($summary['histogram'])" :datasets="[['label' => 'Siswa', 'data' => array_values($summary['histogram'])]]" wire:key="hist-{{ $schedule->id }}" />
            </x-ui.card>
        </div>
    @else
        <x-ui.card><x-ui.empty-state icon="bars" title="Belum ada data nilai" text="Rekap muncul setelah siswa mengerjakan ujian." /></x-ui.card>
    @endif
</div>
