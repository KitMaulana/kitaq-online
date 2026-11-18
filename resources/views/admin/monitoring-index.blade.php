@extends('layouts.admin', ['title' => 'Monitoring Live'])
@section('content')
    <x-ui.page-header title="Monitoring Live" subtitle="Pilih sesi yang ingin dipantau." :crumbs="['Dashboard' => route('admin.dashboard'), 'Monitoring']" />
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
        @forelse ($schedules as $s)
            <a href="{{ route('admin.monitoring.show', $s) }}" class="card card-body" style="color:inherit">
                <div class="flex justify-between items-start mb-2"><b>{{ $s->exam->name }}</b><x-ui.status-chip :status="$s->status" /></div>
                <p class="muted text-sm">{{ $s->classroom?->name ?? 'Peserta khusus' }} · {{ $s->exam->subject->name }}</p>
                <p class="text-sm mt-2 tabular">{{ $s->date->format('d/m') }} · {{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }}</p>
            </a>
        @empty
            <x-ui.card><x-ui.empty-state icon="activity" title="Tidak ada sesi untuk dipantau" text="Sesi yang ditugaskan kepada Anda hari ini akan muncul di sini." /></x-ui.card>
        @endforelse
    </div>
@endsection
