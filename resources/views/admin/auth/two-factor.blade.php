@extends('layouts.guest')
@section('title', 'Verifikasi 2FA')
@section('content')
    <h1 class="text-xl font-bold mb-1">Verifikasi dua langkah</h1>
    <p class="muted text-sm mb-5">Masukkan 6 digit kode dari aplikasi autentikator Anda.</p>
    <form method="POST" action="{{ route('admin.2fa.verify') }}" class="space-y-4" data-once>
        @csrf
        <x-ui.input name="code" label="Kode 2FA" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
        <button type="submit" class="btn btn-primary w-full lg">Verifikasi</button>
    </form>
    <form method="POST" action="{{ route('admin.logout') }}" class="text-center mt-4">@csrf<button class="btn btn-ghost sm">Keluar</button></form>
@endsection
