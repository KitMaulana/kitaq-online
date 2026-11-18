@extends('layouts.student')
@section('title', 'Token ujian')
@section('content')
<div class="st-head">
    <a href="{{ route('student.dashboard') }}" class="icon-btn" aria-label="Kembali"><svg class="ico"><use href="#i-chevl"/></svg></a>
    <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
    <span style="width:40px"></span>
</div>
<div class="pad" style="padding-bottom:320px">
    <div class="hello"><p>{{ $exam->subject?->name }}</p><h2>{{ $exam->name }}</h2></div>
    <div class="kv">
        <div><small>Durasi</small><b>{{ $exam->duration_minutes }} menit</b></div>
        <div><small>Jumlah soal</small><b>{{ $exam->effectiveQuestionCount() }} soal</b></div>
        <div><small>Ditutup</small><b>{{ $schedule->closes_at->format('H:i') }}</b></div>
        <div><small>Peserta</small><b>{{ $student->name }}</b></div>
    </div>

    @if ($errors->any())<x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>@endif

    <form method="POST" action="{{ route('student.exam.start', $schedule) }}" data-once>
        @csrf
        @if ($tokenRequired)
            <label class="lbl">Token ujian</label>
            <p class="st-hint" style="margin:0 0 10px">Lihat token di layar pengawas/proyektor. Token berganti tiap 15 menit.</p>
            {{-- Kotak token Keyboard KITAQ (tanpa keyboard HP). Nilai dikirim lewat field tersembunyi. --}}
            <div id="kq-token-box" data-length="{{ $tokenLength }}" aria-label="Token ujian"></div>
            <input type="hidden" name="token" id="kq-token-value" value="">
            <button type="submit" id="kq-token-submit" class="btn btn-primary btn-xl" style="margin-top:16px">{{ $attempt && $attempt->started_at ? 'Lanjutkan ujian' : 'Lanjut' }}</button>
        @else
            <x-ui.alert tone="info">Ujian ini tidak memerlukan token.</x-ui.alert>
            <button type="submit" class="btn btn-primary btn-xl">{{ $attempt && $attempt->started_at ? 'Lanjutkan ujian' : 'Lanjut' }}</button>
        @endif
    </form>
    <p class="st-hint" style="margin-top:14px">Dengan melanjutkan, kamu menyetujui pemantauan aktivitas di halaman ujian, jenis perangkat, dan alamat IP untuk menjaga kejujuran ujian.</p>
</div>
@if ($tokenRequired)<div id="kq-token-kb" class="token-kb"></div>@endif
@endsection
