<div wire:poll.20s>
    <x-ui.page-header title="Sesi & Token" subtitle="Token berganti tiap 15 menit. Tampilkan di proyektor atau bacakan ke siswa." :crumbs="['Dashboard' => route('admin.dashboard'), 'Sesi & Token']" />

    <div class="grid g-2">
        <div class="space-y-4">
            @if ($current)
                <x-ui.card :title="'Token aktif — ' . $current->title()" :subtitle="$current->opens_at->format('H:i') . '–' . $current->closes_at->format('H:i')">
                    <x-slot:actions><x-ui.status-chip :status="$current->status" /></x-slot:actions>
                    <div class="tokbox" wire:key="tok-{{ $current->id }}-{{ md5($token['token']) }}" x-data="tokenRing(@js($token))">
                        <div class="kq-ring">
                            <svg width="132" height="132" viewBox="0 0 132 132" aria-hidden="true">
                                <circle cx="66" cy="66" r="58" fill="none" stroke="var(--surface-2)" stroke-width="10"/>
                                <circle cx="66" cy="66" r="58" fill="none" stroke="var(--info)" stroke-width="10" stroke-linecap="round" :stroke-dasharray="circ" :stroke-dashoffset="offset" transform="rotate(-90 66 66)"/>
                            </svg>
                            <div class="kq-ring-t"><b class="tabular" x-text="mmss"></b><small>ganti token</small></div>
                        </div>
                        <div>
                            <small class="muted">Token ujian</small>
                            <div class="token" x-text="grouped" aria-live="polite">{{ $token['token'] }}</div>
                            <div class="flex gap-2 mt-3 flex-wrap">
                                <x-ui.button icon="monitor" :href="route('admin.projector', $current)" target="_blank">Layar Proyektor</x-ui.button>
                                <x-ui.button variant="secondary" icon="copy" x-on:click="copy()">Salin</x-ui.button>
                                <x-ui.button variant="ghost" icon="refresh" wire:click="rotate" wire:confirm="Ganti token sekarang? Token yang sudah dibacakan tidak berlaku lagi.">Ganti</x-ui.button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4" style="border-top:1px solid var(--border)">
                        <label class="switch"><input type="checkbox" role="switch" @checked($current->token_required) wire:click="toggleToken"><span class="track"></span><span><span class="font-semibold">Wajib token</span><span class="block text-xs muted">Matikan hanya untuk uji coba. Siswa yang sudah mulai tidak perlu token lagi.</span></span></label>
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="Sesi hari ini" :padding="false">
                <x-ui.table :sticky="false">
                    <x-slot:head><th>Jam</th><th>Ujian · kelas</th><th>Masuk</th><th>Status</th><th></th></x-slot:head>
                    @foreach ($today as $s)
                        <tr @class(['sel' => $current && $current->id === $s->id])>
                            <td class="tabular">{{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }}</td>
                            <td><b>{{ $s->exam->name }}</b><div class="text-xs muted">{{ $s->classroom?->name ?? 'Peserta khusus' }}</div></td>
                            <td class="tabular">{{ (int) ($counts[$s->id] ?? 0) }}/{{ $totals[$s->id] }}</td>
                            <td><x-ui.status-chip :status="$s->status" /></td>
                            <td class="text-right"><x-ui.button size="sm" variant="secondary" wire:click="select({{ $s->id }})">Token</x-ui.button> <x-ui.button size="sm" :href="route('admin.monitoring.show', $s)">Pantau</x-ui.button></td>
                        </tr>
                    @endforeach
                </x-ui.table>
                @if ($today->isEmpty())<x-ui.empty-state icon="cal" title="Tidak ada sesi hari ini" />@endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Kesehatan sistem">
                <div @class(['svc', 'bad' => ! $health['cronOk']])><span class="lamp"></span><div><b>Cron</b><small>{{ $health['cron'] ? 'terakhir ' . \Illuminate\Support\Carbon::createFromTimestamp($health['cron'])->diffForHumans() : 'belum pernah berjalan' }}</small></div></div>
                <div @class(['svc', 'bad' => $health['queue'] > 50])><span class="lamp"></span><div><b>Antrean</b><small>{{ $health['queue'] }} tugas menunggu</small></div></div>
                <div @class(['svc', 'bad' => ($health['api'] ?? 0) > 800])><span class="lamp"></span><div><b>Respons API</b><small>{{ $health['api'] !== null ? fmt_num($health['api']) . ' ms rata-rata (5 menit)' : 'belum ada data' }}</small></div></div>
                <div class="svc"><span class="lamp"></span><div><b>Siswa online</b><small>{{ fmt_num($health['online']) }} mengirim heartbeat</small></div></div>
            </x-ui.card>
            @if ($settings)
                <x-ui.card title="Profil keketatan" :subtitle="'Profil ' . ucfirst($settings['preset']) . ' · pelanggaran ke-' . ($settings['max_warnings'] + 1) . ' mengunci'">
                    @foreach ($summary as $label => $action)
                        <div class="flex justify-between text-sm py-1" style="border-bottom:1px dashed var(--border)"><span>{{ $label }}</span>
                            <x-ui.badge :tone="['Kunci' => 'danger', 'Peringatan' => 'warning'][$action] ?? 'neutral'">{{ $action }}</x-ui.badge></div>
                    @endforeach
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
