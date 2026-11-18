@extends('layouts.admin', ['title' => 'Berita Acara'])
@section('content')
    <x-ui.page-header title="Berita Acara" :subtitle="$schedule->title() . ' · ' . $schedule->date->format('d/m/Y')" :crumbs="['Berita Acara' => route('admin.minutes'), $schedule->exam->name]" />
    <div class="grid g-2">
        <x-ui.card title="Ringkasan pelaksanaan">
            <dl class="kv-list mb-4">
                <dt>Mata pelajaran</dt><dd>{{ $schedule->exam->subject->name }}</dd>
                <dt>Waktu</dt><dd>{{ $schedule->opens_at->format('H:i') }}–{{ $schedule->closes_at->format('H:i') }} {{ $schedule->session?->name }}</dd>
                <dt>Ruang</dt><dd>{{ $schedule->room?->name ?? '-' }}</dd>
                <dt>Peserta</dt><dd>{{ $rows->count() }} siswa · hadir {{ $present }} · tidak hadir {{ $absent }}</dd>
                <dt>Pelanggaran</dt><dd>{{ $locked }} siswa tercatat</dd>
                <dt>Buka kunci</dt><dd>{{ $unlocks->count() }} kali</dd>
            </dl>
            <form method="GET" action="{{ route('admin.minutes.pdf', $schedule) }}" target="_blank" class="space-y-3">
                <x-ui.input name="pengawas" label="Nama pengawas" :value="auth()->user()->name" />
                <x-ui.textarea name="catatan" label="Catatan kejadian (opsional)" rows="3" />
                <x-ui.button type="submit" icon="printer">Cetak Berita Acara + Daftar Hadir (PDF)</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card title="Daftar hadir" :padding="false">
            <x-ui.table>
                <x-slot:head><th>No</th><th>Nama</th><th>NISN</th><th>Kehadiran</th></x-slot:head>
                @foreach ($rows as $i => $r)<tr><td>{{ $i + 1 }}</td><td>{{ $r['name'] }}</td><td class="code">{{ $r['nisn'] }}</td><td>{!! $r['present'] ? '<span class="badge tone-success">Hadir</span>' : '<span class="badge tone-danger">Tidak hadir</span>' !!}</td></tr>@endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
@endsection
