<div wire:poll.15s>
    <x-ui.page-header title="Kesehatan Sistem" subtitle="Periksa sebelum ujian dimulai. Diperbarui tiap 15 detik." :crumbs="['Dashboard' => route('admin.dashboard'), 'Kesehatan']" />
    <div class="grid g-stats">
        <x-ui.stat label="Siswa online" :value="fmt_num($online)" icon="wifi" tone="success" />
        <x-ui.stat label="Percobaan aktif" :value="fmt_num($active)" icon="pencil" tone="info" />
        <x-ui.stat label="Ukuran database" :value="$db" icon="database" tone="brand" />
        <x-ui.stat label="Interval heartbeat" :value="$heartbeat . ' dtk'" icon="heart" tone="neutral" delta="adaptif bila server lambat" />
    </div>
    <div class="grid g-2">
        <x-ui.card title="Pemeriksaan">
            @foreach ($checks as [$label, $ok, $info])
                <div @class(['svc', 'bad' => ! $ok])><span class="lamp"></span><div><b>{{ $label }}</b><small>{{ $info }}</small></div></div>
            @endforeach
        </x-ui.card>
        <div class="space-y-4">
            <x-ui.card title="Isi database">
                <dl class="kv-list">@foreach ($tables as $t => $n)<dt>{{ $t }}</dt><dd class="tabular">{{ fmt_num($n) }}</dd>@endforeach</dl>
            </x-ui.card>
            <x-ui.card title="Cron yang wajib dipasang">
                <p class="text-sm muted mb-2">Menu Cron Job di panel hosting, setiap menit:</p>
                <pre class="code" style="white-space:pre-wrap;background:var(--surface-2);padding:10px;border-radius:10px">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</pre>
                <p class="text-xs muted mt-2">Menjalankan: buka/tutup jadwal, kumpul otomatis, deteksi heartbeat hilang, antrean ekspor & analitik, backup harian 01.30, pembersihan log 02.00.</p>
            </x-ui.card>
        </div>
    </div>
</div>
