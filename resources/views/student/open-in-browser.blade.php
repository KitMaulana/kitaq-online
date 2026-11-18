@extends('layouts.student')
@section('title', 'Buka di browser')
@section('content')
<div class="pad login">
    <img class="lg-logo" src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE">
    <div class="card card-body">
        <h1 class="text-xl font-bold mb-2">Buka di browser yang diizinkan</h1>
        @if ($info['webview'])
            <p class="muted mb-3">Kamu membuka KITAQ ONLINE dari dalam aplikasi lain (mis. WhatsApp/Instagram). Halaman ini tidak bisa layar penuh dan tidak aman untuk ujian.</p>
        @else
            <p class="muted mb-3">Browser ini tidak diizinkan untuk ujian karena memiliki asisten AI bawaan atau memudahkan ekstensi.</p>
        @endif
        <p class="font-semibold mb-2">Gunakan salah satu:</p>
        <ul class="mb-4" style="padding-left:18px;list-style:disc">
            @foreach ($allow as $b)<li>{{ \App\Support\ExamSettings::BROWSERS[$b] ?? $b }}</li>@endforeach
        </ul>
        <div class="guide-step"><span class="n">1</span><div>Ketuk tombol di bawah untuk menyalin tautan.</div></div>
        <div class="guide-step"><span class="n">2</span><div>Buka Chrome / Safari / Samsung Internet / Huawei Browser.</div></div>
        <div class="guide-step" style="border:0"><span class="n">3</span><div>Tempel tautan di bilah alamat lalu buka.</div></div>
        <button type="button" class="btn btn-primary btn-xl mt-3" data-copy="{{ $url }}" data-fallback="url-box">Salin tautan</button>
        <p id="url-box" class="code mt-3 break-all" hidden>{{ $url }}</p>
    </div>
</div>
@endsection
