<div wire:poll.30s>
    <x-ui.page-header title="Dashboard" :subtitle="school('school_name') . ' · ' . fmt_dt(now(), 'l, d F Y')">
        <x-slot:actions>
            @if (Route::has('admin.sessions'))<x-ui.button variant="secondary" icon="key" :href="route('admin.sessions')">Sesi & Token</x-ui.button>@endif
            @if (Route::has('admin.monitoring.index'))<x-ui.button icon="activity" :href="route('admin.monitoring.index')">Monitoring Live</x-ui.button>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $cronOk && auth()->user()->isAdminLike())
        <x-ui.alert tone="warning" title="Cron belum berjalan">
            {{ $cronLast ? 'Cron terakhir jalan ' . \Illuminate\Support\Carbon::createFromTimestamp($cronLast)->diffForHumans() . '.' : 'Belum pernah tercatat.' }}
            Buka/tutup jadwal otomatis, kumpul otomatis, dan ekspor tidak berjalan tanpa cron (lihat docs/DEPLOY_SHARED_HOSTING.md §5).
        </x-ui.alert>
    @endif

    <div class="grid g-stats">
        <x-ui.stat label="Siswa aktif" :value="fmt_num($stats['students'])" icon="users" tone="brand" />
        <x-ui.stat label="Ujian hari ini" :value="$stats['today']" icon="cal" tone="info" />
        <x-ui.stat label="Sedang berlangsung" :value="$stats['running']" icon="play" tone="success" />
        <x-ui.stat label="Siswa online" :value="fmt_num($stats['online'])" icon="wifi" tone="success" />
        <x-ui.stat label="Pelanggaran hari ini" :value="fmt_num($stats['violations'])" icon="shield" :tone="$stats['violations'] ? 'danger' : 'neutral'" />
    </div>

    <div class="grid g-2" style="margin-bottom:16px">
        <x-ui.card title="Ujian hari ini" subtitle="Jadwal sesuai hak akses Anda">
            @forelse ($today as $s)
                @php $c = $counts[$s->id] ?? null; @endphp
                <div class="svc">
                    <div class="tabular" style="min-width:92px"><b>{{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }}</b><small>{{ $s->session?->name ?? 'Sesi' }}</small></div>
                    <div class="flex-1 min-w-0">
                        <b class="truncate">{{ $s->exam->name }}</b>
                        <small>{{ $s->classroom?->name ?? 'Peserta khusus' }} · {{ $s->exam->subject->name }} · {{ (int) ($c->started ?? 0) }} masuk</small>
                    </div>
                    <x-ui.status-chip :status="$s->status" />
                    @if (Route::has('admin.monitoring.show'))<x-ui.button size="sm" variant="secondary" :href="route('admin.monitoring.show', $s)">Pantau</x-ui.button>@endif
                </div>
            @empty
                <x-ui.empty-state icon="cal" title="Tidak ada ujian hari ini" text="Jadwal ujian yang dibuat untuk hari ini akan tampil di sini." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Perlu perhatian">
            @forelse ($attention as $a)
                <div class="svc bad">
                    <span class="lamp"></span>
                    <div class="flex-1 min-w-0"><b>{{ $a->student->name }}</b><small>Terkunci · {{ \App\Services\Shield\ViolationPolicy::reasonFor((string) $a->lock_reason) }}</small></div>
                    @if (Route::has('admin.monitoring.show'))<x-ui.button size="sm" variant="soft-danger" :href="route('admin.monitoring.show', $a->exam_schedule_id)">Buka</x-ui.button>@endif
                </div>
            @empty
                @if ($alerts->isEmpty())
                    <x-ui.empty-state icon="checkc" title="Semua aman" text="Tidak ada siswa terkunci atau login dari perangkat lain." />
                @endif
            @endforelse
            @foreach ($alerts as $al)
                <div class="svc bad"><span class="lamp"></span><div><b>Login dari perangkat lain</b><small>{{ $al->message }}</small></div></div>
            @endforeach
            <div class="svc @if(! $cronOk) bad @endif"><span class="lamp"></span><div><b>Beban server</b><small>Respons API rata-rata {{ $apiMs !== null ? fmt_num($apiMs) . ' ms' : '-' }} · antrean {{ $queue }} tugas</small></div></div>
        </x-ui.card>
    </div>

    <div class="grid g-2b">
        <x-ui.card title="Rata-rata nilai per mapel">
            @if ($scores->isEmpty())
                <x-ui.empty-state icon="bars" title="Belum ada nilai" text="Grafik muncul setelah ada ujian yang dikumpulkan." />
            @else
                <x-ui.chart type="bar" :labels="$scores->pluck('name')->all()" :datasets="[['label' => 'Rata-rata', 'data' => $scores->map(fn ($s) => round((float) $s->avg_score, 1))->all()]]" :max="100" />
            @endif
        </x-ui.card>
        <x-ui.card title="Aktivitas pelanggaran per jam (hari ini)">
            <x-ui.chart type="line" :labels="$hourLabels" :datasets="[['label' => 'Event', 'data' => $hourData]]" />
        </x-ui.card>
    </div>
</div>
