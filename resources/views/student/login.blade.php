@extends('layouts.student')
@section('title', 'Masuk')
@section('content')
<div class="pad login">
    <img class="lg-logo" src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
    <div class="school">
        @if (school('school_logo'))
            <img src="{{ route('school.logo') }}" alt="Logo sekolah">
        @else
            <span class="avatar" style="width:44px;height:44px"><svg class="ico"><use href="#i-school"/></svg></span>
        @endif
        <div><b>{{ school('school_name') }}</b><small>Ujian Online · {{ site_host() }}</small></div>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('student.login.submit') }}" data-once autocomplete="off">
        @csrf
        <label class="lbl" for="nisn">NISN</label>
        <input id="nisn" name="nisn" class="input xl" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" value="{{ old('nisn') }}" placeholder="10 digit NISN" required autofocus>

        <label class="lbl" for="password">Tanggal lahir</label>
        <div class="pw">
            <input id="password" name="password" type="password" class="input xl" inputmode="numeric" maxlength="12" placeholder="DDMMYYYY" required>
            <button type="button" data-toggle-password="password" aria-label="Tampilkan"><svg class="ico"><use href="#i-eye"/></svg></button>
        </div>
        <span class="st-hint">Format <b>DDMMYYYY</b> · contoh lahir 20 Oktober 2008 → <b>20102008</b></span>

        <button type="submit" class="btn btn-primary btn-xl" style="margin-top:20px">Masuk</button>
    </form>
    <a class="link" href="/cek-perangkat">Cek perangkat sebelum ujian</a>
    <a class="link" href="{{ route('student.install') }}">Pasang KITAQ ONLINE ke Layar Utama</a>
    <p class="by">by <b>CiptaKita.id</b></p>
</div>
@endsection
