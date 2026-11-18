<div>
    <x-ui.page-header title="Analisis Butir" subtitle="Tingkat kesukaran (p), daya beda (D, 27% atas−bawah), dan sebaran pengecoh." :crumbs="['Dashboard' => route('admin.dashboard'), 'Analisis Butir']">
        <x-slot:actions>@if ($schedule)<x-ui.button variant="secondary" icon="download" wire:click="export">Ekspor Excel</x-ui.button>@endif</x-slot:actions>
    </x-ui.page-header>
    @include('livewire.admin.partials.schedule-picker')
    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>No</th><th>Soal</th><th>Kesukaran (p)</th><th>Daya beda (D)</th><th>Pengecoh (PG)</th></x-slot:head>
            @foreach ($items as $it)
                <tr>
                    <td class="tabular">{{ $it['no'] }}</td>
                    <td style="max-width:360px"><span class="text-sm">{{ $it['text'] }}</span><div class="text-xs muted">{{ \App\Models\Question::TYPES[$it['type']] }}</div></td>
                    <td>
                        <div class="flex items-center gap-2"><div class="bar-h" style="width:90px"><i style="width:{{ $it['p'] * 100 }}%;background:{{ $it['p'] >= .7 ? 'var(--success)' : ($it['p'] >= .3 ? 'var(--info)' : 'var(--accent)') }}"></i></div>
                            <span class="tabular text-sm">{{ fmt_num($it['p'], 2) }}</span></div>
                        <small class="muted">{{ $it['difficulty_label'] }}</small>
                    </td>
                    <td>
                        <div class="flex items-center gap-2"><div class="bar-h" style="width:90px"><i style="width:{{ max(0, $it['d']) * 100 }}%;background:{{ $it['d'] >= .3 ? 'var(--success)' : ($it['d'] >= .2 ? 'var(--warning)' : 'var(--accent)') }}"></i></div>
                            <span class="tabular text-sm">{{ fmt_num($it['d'], 2) }}</span></div>
                        <small class="muted">{{ $it['discrimination_label'] }}</small>
                    </td>
                    <td class="text-xs">
                        @foreach ($it['distractors'] as $k => $d)<span @class(['badge', 'tone-success' => $d['key'], 'tone-neutral' => ! $d['key'] && $d['pct'] > 0]) style="margin:1px">{{ $k }} {{ fmt_num($d['pct'], 0) }}%</span>@endforeach
                        @if (! $it['distractors'])<span class="muted">-</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        @if (! $items)<x-ui.empty-state icon="trend" title="Belum ada data" text="Analisis butir tersedia setelah ada siswa yang mengumpulkan ujian." />@endif
    </x-ui.card>
</div>
