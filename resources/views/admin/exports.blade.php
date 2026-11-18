@extends('layouts.admin', ['title' => 'Unduhan'])
@section('content')
    <x-ui.page-header title="Unduhan" subtitle="Ekspor Excel/PDF diproses antrean oleh cron (tiap menit). Muat ulang halaman untuk melihat status terbaru." :crumbs="['Dashboard' => route('admin.dashboard'), 'Unduhan']" />
    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Waktu</th><th>Jenis</th><th>Oleh</th><th>Status</th><th></th></x-slot:head>
            @foreach ($exports as $e)
                <tr><td class="tabular text-sm">{{ fmt_dt($e->created_at) }}</td><td>{{ $e->label() }}</td><td class="text-sm">{{ $e->user?->name }}</td>
                    <td>@switch($e->status)
                        @case('done')<x-ui.badge tone="success" dot>Selesai</x-ui.badge>@break
                        @case('failed')<x-ui.badge tone="danger" dot>Gagal</x-ui.badge> <small class="muted">{{ $e->error }}</small>@break
                        @case('running')<x-ui.badge tone="info" dot>Diproses</x-ui.badge>@break
                        @default<x-ui.badge tone="warning" dot>Menunggu cron</x-ui.badge>
                    @endswitch</td>
                    <td class="text-right">@if ($e->status === 'done')<x-ui.button size="sm" icon="download" :href="route('admin.exports.download', $e)">Unduh</x-ui.button>@endif</td></tr>
            @endforeach
        </x-ui.table>
        @if ($exports->isEmpty())<x-ui.empty-state icon="download" title="Belum ada ekspor" text="Buat ekspor dari Rekap Nilai, Analisis Butir, Pelanggaran, atau Siswa." />@endif
        <div class="tfoot">{{ $exports->links() }}</div>
    </x-ui.card>
@endsection
