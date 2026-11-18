@extends('layouts.student')
@section('title', 'Beranda')
@section('content')
<div class="st-head">
    <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
    <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="icon-btn" aria-label="Keluar"><svg class="ico"><use href="#i-logout"/></svg></button></form>
</div>
<div class="pad">
    <div class="hello">
        <p>Halo,</p>
        <h2>{{ $student->name }}</h2>
        <p>NISN {{ $student->nisn }} · {{ $student->classroom?->name ?? '-' }}</p>
    </div>

    <div class="list-item install mt-4" data-ios-install hidden>
        <svg class="ico" style="color:var(--info)"><use href="#i-phone"/></svg>
        <div class="t"><b>Pasang ke Layar Utama</b><small>Di iPhone, ujian wajib dibuka dari ikon KITAQ ONLINE.</small></div>
        <a href="{{ route('student.install') }}" class="btn btn-secondary sm">Panduan</a>
    </div>

    <div class="sec">Ujian hari ini</div>
    @forelse ($today as $s)
        @php
            $a = $attempts[$s->id] ?? null;
            $open = $s->isOpenNow();
            $done = $a && $a->isDone();
        @endphp
        <div class="exam-card">
            <small>{{ $s->exam->subject->name }} · {{ \App\Models\Exam::TYPES[$s->exam->type] ?? $s->exam->type }}</small>
            <h3>{{ $s->exam->name }}</h3>
            <div class="meta">
                <span>⏰ {{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }}</span>
                <span>⏳ {{ $s->exam->duration_minutes }} menit</span>
                <span>📝 {{ $s->exam->effectiveQuestionCount() }} soal</span>
            </div>
            @if ($done)
                <a href="{{ route('student.exam.result', $s) }}" class="btn btn-white btn-xl">Selesai · Lihat hasil</a>
            @elseif ($open)
                <a href="{{ route('student.exam.show', $s) }}" class="btn btn-white btn-xl">{{ $a && $a->started_at ? 'Lanjutkan ujian' : 'Mulai ujian' }}</a>
            @elseif ($s->status === 'paused')
                <button class="btn btn-white btn-xl" disabled>Ujian dijeda pengawas</button>
            @elseif ($s->opens_at->isFuture())
                <button class="btn btn-white btn-xl" disabled>Dimulai dalam <span data-countdown="{{ (int) now()->diffInSeconds($s->opens_at) }}" data-reload class="tabular"></span></button>
            @else
                <button class="btn btn-white btn-xl" disabled>Belum dibuka pengawas</button>
            @endif
        </div>
    @empty
        <div class="list-item"><svg class="ico muted"><use href="#i-cal"/></svg><div class="t"><b>Tidak ada ujian hari ini</b><small>Jadwal ujian kelasmu akan tampil di sini.</small></div></div>
    @endforelse

    @if ($upcoming->isNotEmpty())
        <div class="sec">Mendatang</div>
        @foreach ($upcoming as $s)
            <div class="list-item">
                <svg class="ico" style="color:var(--info)"><use href="#i-cal"/></svg>
                <div class="t"><b>{{ $s->exam->name }}</b><small>{{ fmt_dt($s->date, 'l, d M Y') }} · {{ $s->opens_at->format('H:i') }}</small></div>
            </div>
        @endforeach
    @endif

    <div class="sec">Riwayat & nilai</div>
    @forelse ($history as $a)
        <div class="list-item">
            <svg class="ico" style="color:var(--success)"><use href="#i-checkc"/></svg>
            <div class="t"><b>{{ $a->schedule->exam->name }}</b><small>{{ fmt_dt($a->submitted_at) }}</small></div>
            @if (\App\Http\Controllers\Student\DashboardController::scoreVisible($a))
                <span class="score tabular">{{ fmt_num($a->score, 0) }}</span>
            @else
                <small class="muted">{{ $a->needs_grading ? 'Dikoreksi' : 'Nilai belum dibuka' }}</small>
            @endif
        </div>
    @empty
        <div class="list-item"><div class="t"><small>Belum ada ujian yang selesai.</small></div></div>
    @endforelse

    <a class="link" href="/cek-perangkat">Cek perangkat</a>
</div>
@endsection
