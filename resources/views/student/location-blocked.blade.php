@extends('layouts.student')
@section('title', 'Di luar lokasi ujian')
@section('content')
<div class="pad login">
    <img class="lg-logo" src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
    <div class="card card-body text-center">
        <div class="empty" style="padding:10px 0">
            <div class="ic" style="background:var(--warning-soft);color:var(--warning)"><svg class="ico lg"><use href="#i-wifioff"/></svg></div>
            <b>Hubungkan ke WiFi sekolah</b>
            <p class="text-sm">{{ $message }}</p>
            <p class="text-xs mt-2">IP kamu: {{ $ip }}</p>
        </div>
        <a href="{{ route('student.dashboard') }}" class="btn btn-secondary w-full">Kembali</a>
    </div>
</div>
@endsection
