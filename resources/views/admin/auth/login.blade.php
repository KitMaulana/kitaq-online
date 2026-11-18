@extends('layouts.guest')
@section('title', 'Masuk Staf')
@section('content')
    <h1 class="text-xl font-bold mb-1">Masuk Staf</h1>
    <p class="muted text-sm mb-5">Admin, operator, guru, dan pengawas {{ school('school_name') }}.</p>

    @if (session('status'))<x-ui.alert tone="info">{{ session('status') }}</x-ui.alert>@endif

    <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4" data-once>
        @csrf
        <x-ui.input name="login" label="Username atau email" icon="user" value="{{ old('login') }}" autocomplete="username" required autofocus />
        <div class="field">
            <label for="password">Password</label>
            <div class="pw">
                <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                <button type="button" data-toggle-password="password" aria-label="Tampilkan"><svg class="ico"><use href="#i-eye"/></svg></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-full lg">Masuk</button>
    </form>
    <p class="text-center text-sm mt-5"><a href="{{ route('student.login') }}">← Halaman login siswa</a></p>
@endsection
