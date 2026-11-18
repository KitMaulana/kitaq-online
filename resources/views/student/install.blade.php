@extends('layouts.student')
@section('title', 'Pasang ke Layar Utama')
@section('content')
<div class="st-head">
    <a href="{{ url('/') }}" class="icon-btn" aria-label="Kembali"><svg class="ico"><use href="#i-chevl"/></svg></a>
    <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE"><span style="width:40px"></span>
</div>
<div class="pad">
    <div class="hello"><h2>Pasang KITAQ ONLINE</h2><p>Buka KITAQ ONLINE dari ikon di Layar Utama agar tampil penuh tanpa bilah alamat. <b>Wajib di iPhone.</b></p></div>

    <div class="sec">iPhone / iPad (Safari)</div>
    <div class="card card-body">
        <div class="guide-step"><span class="n">1</span><div>Buka <b>{{ site_host() }}</b> di <b>Safari</b> (bukan Chrome/WhatsApp).</div></div>
        <div class="guide-step"><span class="n">2</span><div>Ketuk tombol <b>Bagikan</b> (kotak dengan panah ke atas) di bawah layar.</div></div>
        <div class="guide-step"><span class="n">3</span><div>Gulir lalu pilih <b>Tambah ke Layar Utama</b> → <b>Tambah</b>.</div></div>
        <div class="guide-step" style="border:0"><span class="n">4</span><div>Tutup Safari, lalu buka <b>KITAQ ONLINE</b> dari ikon di Layar Utama. Login ulang bila diminta.</div></div>
    </div>

    <div class="sec">Android (Chrome / Samsung Internet)</div>
    <div class="card card-body">
        <div class="guide-step"><span class="n">1</span><div>Buka <b>{{ site_host() }}</b> di Chrome atau Samsung Internet.</div></div>
        <div class="guide-step"><span class="n">2</span><div>Ketuk <b>Pasang aplikasi</b> bila muncul, atau menu <b>⋮</b> → <b>Tambahkan ke layar utama</b> / <b>Instal aplikasi</b>.</div></div>
        <div class="guide-step" style="border:0"><span class="n">3</span><div>Buka KITAQ ONLINE dari ikon yang baru.</div></div>
    </div>

    <div class="sec">Huawei (Huawei Browser)</div>
    <div class="card card-body">
        <div class="guide-step"><span class="n">1</span><div>Buka <b>{{ site_host() }}</b> di Huawei Browser.</div></div>
        <div class="guide-step"><span class="n">2</span><div>Ketuk menu <b>⋮</b> / <b>≡</b> → <b>Tambahkan ke layar beranda</b> (nama menu bisa berbeda per versi HarmonyOS).</div></div>
        <div class="guide-step" style="border:0"><span class="n">3</span><div>Bila menu tidak ada, tetap pakai Huawei Browser dengan layar penuh saat ujian.</div></div>
    </div>

    <a href="/cek-perangkat" class="btn btn-secondary btn-xl mt-5">Cek perangkat sekarang</a>
</div>
@endsection
