<div>
    <x-ui.page-header title="Backup" subtitle="Backup database otomatis tiap hari pukul 01.30 (cron). Retensi {{ config('kitaq.backup_retention_days') }} hari. Unduh rutin ke penyimpanan sekolah." :crumbs="['Dashboard' => route('admin.dashboard'), 'Backup']">
        <x-slot:actions><x-ui.button icon="database" wire:click="backupNow" loading="backupNow">Backup sekarang</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.alert tone="info" title="Arsip terenkripsi">
        File ZIP dienkripsi AES-256 dengan password turunan APP_KEY.
        @if ($password) Password: <span class="code font-bold">{{ $password }}</span> @else <button type="button" class="btn btn-ghost sm" wire:click="$set('showPassword', true)">Tampilkan password</button> @endif
    </x-ui.alert>
    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Berkas</th><th>Ukuran</th><th>Waktu</th><th></th></x-slot:head>
            @foreach ($files as $f)
                <tr><td class="code">{{ $f['name'] }}</td><td class="tabular">{{ \App\Support\SystemHealth::humanBytes($f['size']) }}</td><td>{{ fmt_dt(\Illuminate\Support\Carbon::createFromTimestamp($f['time'])) }}</td>
                    <td class="text-right"><x-ui.button size="sm" icon="download" wire:click="download('{{ $f['name'] }}')">Unduh</x-ui.button>
                        <x-ui.button size="sm" variant="ghost" wire:click="delete('{{ $f['name'] }}')" wire:confirm="Hapus backup ini?">Hapus</x-ui.button></td></tr>
            @endforeach
        </x-ui.table>
        @if (! $files)<x-ui.empty-state icon="database" title="Belum ada backup" text="Klik Backup sekarang atau tunggu jadwal harian." />@endif
    </x-ui.card>
</div>
