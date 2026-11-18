{{--
  KITAQ ONLINE — Halaman ruang ujian siswa (diadaptasi dari kode referensi starter kit).
  PENTING:
   - JANGAN memakai <input>, <textarea>, contenteditable, Livewire, atau skrip pihak ketiga di halaman ini.
     Jawaban isian/uraian lewat Keyboard Virtual KITAQ (resources/js/keyboard).
   - Semua elemen di <body> diberi atribut data-kq agar guard ekstensi bisa membedakan elemen asing.
   - CSP nonce dipasang oleh middleware SecurityHeaders (Vite::useCspNonce()).
   - Kunci jawaban TIDAK PERNAH dikirim ke sini. Soal diambil satu per satu lewat API.
--}}
<!doctype html>
<html lang="id" translate="no" class="notranslate" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="google" content="notranslate">
    <meta name="robots" content="noindex, nofollow">
    <meta name="format-detection" content="telephone=no, date=no, email=no, address=no">
    <meta name="theme-color" content="#022684">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <title>{{ $exam->title }} — KITAQ ONLINE</title>
    @vite(['resources/js/exam/exam-app.js'])
    <script nonce="{{ Vite::cspNonce() }}">
        window.KITAQ_EXAM = @json($examConfig);
    </script>
</head>
<body class="kitaq-exam @if($examConfig['largeFont']) kq-large @endif" data-kq>

{{-- 1. Konfirmasi & prapemeriksaan perangkat --}}
<section id="kq-confirm" class="kq-screen" data-kq>
    <img src="/images/kitaq-online-logo.png" alt="KITAQ ONLINE" class="kq-logo">
    <p class="kq-sub">{{ $exam->subject }}</p>
    <h1>{{ $exam->title }}</h1>
    <div class="kq-kv">
        <div><small>Peserta</small><b>{{ $student->name }}</b></div>
        <div><small>NISN</small><b>{{ $student->nisn }}</b></div>
        <div><small>Durasi</small><b>{{ $exam->duration_minutes }} menit</b></div>
        <div><small>Soal</small><b>{{ $exam->question_count }} soal</b></div>
    </div>
    <ul class="kq-rules">
        <li>Jangan keluar dari halaman ujian, membuka layar terpisah, atau jendela mengambang.</li>
        <li>Fitur terjemahan, ekstensi/asisten AI, dan keyboard bawaan HP tidak boleh dipakai. Jawaban isian diketik dengan Keyboard KITAQ.</li>
        <li>Pelanggaran ke-{{ $examConfig['shield']['maxWarnings'] + 1 }} mengunci ujian. Hanya pengawas yang dapat membuka.</li>
        <li>Jika sinyal putus, tetap kerjakan. Jawaban disimpan di HP dan dikirim saat online lagi.</li>
    </ul>
    <p class="kq-small kq-privacy">Yang dipantau selama ujian: aktivitas di halaman ujian (pindah aplikasi, layar terpisah, terjemahan, ekstensi), jenis perangkat & browser, dan alamat IP — hanya untuk menjaga kejujuran ujian.</p>
    <div id="kq-precheck" class="kq-alert" hidden>
        <b>Perangkat belum siap:</b>
        <ul id="kq-precheck-list"></ul>
    </div>
    <div id="kq-precheck-ok" class="kq-ok" hidden>✓ Perangkat siap: browser diizinkan, layar tidak terbagi.</div>
    <button id="kq-start" type="button" class="kq-btn-primary">Saya Mengerti &amp; Mulai</button>
</section>

{{-- 2. Ruang ujian --}}
<main id="kq-exam" class="kq-exam-scroll" hidden data-kq>
    <div class="kq-watermark" aria-hidden="true" data-watermark="{{ $student->name }} · {{ $student->nisn }}"></div>
    <header class="kq-top">
        <div class="kq-top-l"><b>{{ $exam->title }}</b> <span id="kq-save" data-status="saved" aria-live="polite">Tersimpan</span></div>
        <div id="kq-timer" class="kq-timer" aria-label="Sisa waktu">--:--</div>
        <div class="kq-bar"><i id="kq-progress"></i></div>
    </header>
    <div id="kq-conn" class="kq-conn" hidden></div>
    <article class="kq-q">
        <div id="kq-stimulus" class="kq-stim" hidden><b id="kq-stimulus-title">Bacaan</b><div id="kq-stimulus-text"></div><img id="kq-stimulus-img" alt="Gambar bacaan" hidden></div>
        <div class="kq-qno-row"><span id="kq-qno" class="kq-qno"></span><span id="kq-qtype" class="kq-qtype"></span></div>
        <div id="kq-qtext" class="kq-qtext"></div>
        <img id="kq-qimg" alt="Gambar soal" hidden>
        <div id="kq-audio" hidden></div>
        <div id="kq-options"></div>
        <div class="kq-tools">
            <button id="kq-doubt" type="button">Ragu-ragu</button>
            <button id="kq-font" type="button" aria-label="Ukuran huruf">A<sup>±</sup></button>
            <button id="kq-open-map" type="button">Peta soal</button>
        </div>
    </article>
    <nav class="kq-nav">
        <button id="kq-prev" type="button">Sebelumnya</button>
        <button id="kq-next" type="button" class="kq-btn-primary">Berikutnya</button>
    </nav>
</main>

{{-- Keyboard Virtual KITAQ --}}
<div id="kq-kb" data-kq hidden></div>

{{-- Peta soal --}}
<div id="kq-map" class="kq-sheet" hidden data-kq>
    <div class="kq-grab"></div>
    <div class="kq-map-head"><b>Peta soal</b><span id="kq-map-count"></span></div>
    <div class="kq-legend"><span><i class="ans"></i>Dijawab</span><span><i class="dbt"></i>Ragu-ragu</span><span><i></i>Belum</span></div>
    <div class="kq-map-grid" id="kq-map-grid"></div>
    <button id="kq-finish" type="button" class="kq-btn-primary">Selesai &amp; Kumpulkan</button>
    <button id="kq-map-close" type="button" class="kq-btn-ghost">Tutup</button>
</div>

{{-- Konfirmasi kumpulkan (modal sendiri; jangan pakai confirm()) --}}
<div id="kq-submit-modal" class="kq-overlay" hidden data-kq>
    <h2>Kumpulkan jawaban?</h2>
    <p id="kq-submit-info">Jawaban tidak bisa diubah setelah dikumpulkan.</p>
    <button id="kq-submit-ok" type="button" class="kq-btn-primary">Ya, kumpulkan</button>
    <button id="kq-submit-cancel" type="button" class="kq-btn-ghost">Periksa lagi</button>
</div>

{{-- Status dari server --}}
<div class="kq-overlay warn" data-kq-overlay="warn" hidden data-kq>
    <div class="kq-big">⚠</div>
    <h2>Peringatan <span data-kq-warn-count></span></h2>
    <p data-kq-warn-reason></p>
    <div class="kq-count" data-kq-warn-bars></div>
    <button type="button" class="kq-btn-primary">Saya mengerti, lanjut</button>
</div>
<div class="kq-overlay lock" data-kq-overlay="lock" hidden data-kq>
    <div class="kq-big">🔒</div>
    <h2>Ujian dikunci</h2>
    <p data-kq-lock-reason></p>
    <p class="kq-who">{{ $student->name }} · NISN {{ $student->nisn }}</p>
    <p class="kq-small">Jawabanmu aman. Angkat tangan / hubungi pengawas untuk membuka kunci.</p>
</div>
<div class="kq-overlay off" data-kq-overlay="paused" hidden data-kq>
    <div class="kq-big">⏸</div>
    <h2>Ujian dijeda pengawas</h2>
    <p>Tunggu sebentar. Waktu ujian tidak berkurang selama jeda.</p>
</div>
<div class="kq-overlay off" data-kq-overlay="blocked" hidden data-kq>
    <div class="kq-big">📱</div>
    <h2>Ujian aktif di perangkat/tab lain</h2>
    <p data-kq-blocked-reason>Tutup tab ini. Membuka ujian di dua tempat dicatat sebagai pelanggaran.</p>
    <button type="button" class="kq-btn-primary" data-kq-goto-token>Masukkan token lagi</button>
</div>

{{-- Penutup lokal (hilang otomatis bila kondisi kembali normal) --}}
<div class="kq-overlay block" data-kq-blocker="split" hidden data-kq>
    <h2>Layar terpisah terdeteksi</h2>
    <p>Tutup aplikasi lain / jendela mengambang agar ujian memenuhi seluruh layar. Kejadian ini sudah dicatat.</p>
</div>
<div class="kq-overlay block" data-kq-blocker="fullscreen" hidden data-kq>
    <h2>Kembali ke layar penuh</h2>
    <p>Kamu keluar dari layar penuh. Kejadian ini sudah dicatat.</p>
    <button type="button" class="kq-btn-primary">Kembali ke layar penuh</button>
</div>
<div class="kq-overlay block" data-kq-blocker="multitab" hidden data-kq>
    <h2>Ujian sudah terbuka di tab/jendela lain</h2>
    <p>Tutup tab ini. Membuka ujian di dua tempat dicatat sebagai pelanggaran.</p>
</div>

<div id="kq-toast" class="kq-toast" role="status" hidden data-kq></div>
</body>
</html>
