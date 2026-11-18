@extends('layouts.admin', ['title' => 'Berita Acara'])
@section('content')
    <x-ui.page-header title="Berita Acara" subtitle="Berita acara pelaksanaan & daftar hadir per jadwal." :crumbs="['Dashboard' => route('admin.dashboard'), 'Berita Acara']" />
    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Tanggal</th><th>Ujian</th><th>Kelas</th><th>Status</th><th></th></x-slot:head>
            @foreach ($schedules as $s)
                <tr><td class="tabular">{{ $s->date->format('d/m/Y') }} {{ $s->opens_at->format('H:i') }}</td><td>{{ $s->exam->name }}</td><td>{{ $s->classroom?->name ?? 'Khusus' }}</td><td><x-ui.status-chip :status="$s->status" /></td>
                    <td class="text-right"><x-ui.button size="sm" variant="secondary" :href="route('admin.minutes.show', $s)">Buka</x-ui.button></td></tr>
            @endforeach
        </x-ui.table>
        @if ($schedules->isEmpty())<x-ui.empty-state icon="file" title="Belum ada jadwal" />@endif
        <div class="tfoot">{{ $schedules->links() }}</div>
    </x-ui.card>
@endsection
