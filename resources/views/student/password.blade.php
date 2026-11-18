@extends('layouts.student')
@section('title', 'Ganti Password')
@section('content')
<div class="st-head"><img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE"></div>
<div class="pad">
    <div class="hello"><h2>Ganti password</h2><p>Sekolah mewajibkan password baru sebelum ujian. Password lama = tanggal lahir (DDMMYYYY).</p></div>
    @if ($errors->any())<div class="mt-3"><x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert></div>@endif
    <form method="POST" action="{{ route('student.password.update') }}" data-once class="mt-4">
        @csrf
        <label class="lbl" for="current_password">Password lama</label>
        <input id="current_password" name="current_password" type="password" class="input xl" required>
        <label class="lbl" for="password">Password baru (min. 6 karakter)</label>
        <input id="password" name="password" type="password" class="input xl" required>
        <label class="lbl" for="password_confirmation">Ulangi password baru</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="input xl" required>
        <button type="submit" class="btn btn-primary btn-xl" style="margin-top:20px">Simpan</button>
    </form>
    <form method="POST" action="{{ route('student.logout') }}" class="mt-3">@csrf<button class="btn btn-ghost w-full">Keluar</button></form>
</div>
@endsection
