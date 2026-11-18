@extends('layouts.student')
@section('title', 'Hasil ujian')
@section('content')
<div class="st-head"><img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE"></div>
<div class="pad text-center" style="padding-top:20px">
    <p class="muted">{{ $schedule->exam->subject?->name }}</p>
    <h2 class="text-xl font-bold mb-5">{{ $schedule->exam->name }}</h2>
    @php $d = $attempt->score_detail ?? []; @endphp
    @if ($visible)
        <div class="res-score tabular">{{ fmt_num($attempt->score, 0) }}</div>
        <p class="mt-2">
            @if ($attempt->score >= $schedule->exam->kkm)<x-ui.badge tone="success" dot>Tuntas (KKM {{ $schedule->exam->kkm }})</x-ui.badge>
            @else<x-ui.badge tone="warning" dot>Belum tuntas (KKM {{ $schedule->exam->kkm }})</x-ui.badge>@endif
        </p>
        <div class="res-grid">
            <div><b style="color:var(--success)">{{ $d['correct'] ?? 0 }}</b><small>Benar</small></div>
            <div><b style="color:var(--accent)">{{ $d['wrong'] ?? 0 }}</b><small>Salah</small></div>
            <div><b>{{ $d['blank'] ?? 0 }}</b><small>Kosong</small></div>
        </div>
    @else
        <div class="empty">
            <div class="ic" style="background:var(--success-soft);color:var(--success)"><svg class="ico lg"><use href="#i-checkc"/></svg></div>
            <b>Jawaban sudah dikumpulkan</b>
            <p class="text-sm">{{ $attempt->needs_grading ? 'Soal uraian sedang dikoreksi guru.' : 'Nilai akan diumumkan oleh sekolah.' }}</p>
        </div>
    @endif
    <p class="muted text-sm">Dikumpulkan {{ fmt_dt($attempt->submitted_at) }}{{ $attempt->status === 'force_submitted' ? ' (oleh pengawas)' : '' }}</p>
    <a href="{{ route('student.dashboard') }}" class="btn btn-primary btn-xl mt-5">Kembali ke beranda</a>
</div>
@endsection
