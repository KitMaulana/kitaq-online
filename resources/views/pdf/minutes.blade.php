<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<style>
    @page { margin: 16mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; }
    .kop { text-align: center; border-bottom: 3px double #022684; padding-bottom: 6px; margin-bottom: 10px; }
    .kop b { font-size: 14px; } .kop small { display: block; color: #5b6b88; }
    h2 { text-align: center; font-size: 13px; margin: 8px 0; text-decoration: underline; }
    table.kv td { padding: 2px 6px 2px 0; vertical-align: top; }
    table.list { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.list th, table.list td { border: 1px solid #c9d3e6; padding: 4px 5px; }
    table.list th { background: #f1f5fb; font-size: 9.5px; }
    .sign { width: 100%; margin-top: 24px; } .sign td { width: 50%; text-align: center; vertical-align: top; }
    .foot { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 8px; color: #93a3c4; text-align: center; }
    .pb { page-break-before: always; }
</style></head>
<body>
<div class="kop"><b>{{ school('school_name') }}</b><small>{{ school('school_address') }} {{ school('school_city') }}</small></div>
<h2>BERITA ACARA PELAKSANAAN UJIAN ONLINE</h2>
<p>Pada hari ini, {{ fmt_dt($schedule->date, 'l, d F Y') }}, telah dilaksanakan ujian online dengan rincian:</p>
<table class="kv">
    <tr><td>Nama ujian</td><td>: {{ $schedule->exam->name }} ({{ $schedule->exam->type }})</td></tr>
    <tr><td>Mata pelajaran</td><td>: {{ $schedule->exam->subject->name }}</td></tr>
    <tr><td>Kelas / ruang</td><td>: {{ $schedule->classroom?->name ?? 'Peserta khusus' }} / {{ $schedule->room?->name ?? '-' }}</td></tr>
    <tr><td>Waktu</td><td>: {{ $schedule->opens_at->format('H:i') }} – {{ $schedule->closes_at->format('H:i') }} WIB</td></tr>
    <tr><td>Jumlah peserta</td><td>: {{ $rows->count() }} siswa (hadir {{ $present }}, tidak hadir {{ $absent }})</td></tr>
    <tr><td>Siswa tercatat pelanggaran</td><td>: {{ $locked }} siswa; buka kunci oleh pengawas {{ $unlocks->count() }} kali</td></tr>
</table>
@if ($absent)
    <p><b>Tidak hadir:</b> {{ $rows->where('present', false)->pluck('name')->join(', ') }}</p>
@endif
@if ($unlocks->count())
    <p><b>Catatan buka kunci:</b></p>
    <table class="list"><tr><th>Jam</th><th>Siswa</th><th>Oleh</th><th>Alasan</th></tr>
        @foreach ($unlocks as $u)<tr><td>{{ $u->created_at->format('H:i') }}</td><td>{{ $names[$u->exam_attempt_id] ?? '-' }}</td><td>{{ $u->user?->name }}</td><td>{{ $u->reason }}</td></tr>@endforeach
    </table>
@endif
<p><b>Catatan kejadian:</b> {{ $notes ?: '-' }}</p>
<table class="sign"><tr>
    <td>Mengetahui,<br>Kepala Sekolah<br><br><br><br><b>{{ school('principal_name') ?: '....................' }}</b><br>{{ school('principal_nip') ? 'NIP ' . school('principal_nip') : '' }}</td>
    <td>{{ school('school_city') ? school('school_city') . ', ' : '' }}{{ fmt_dt($schedule->date, 'd F Y') }}<br>Pengawas<br><br><br><br><b>{{ $supervisor }}</b></td>
</tr></table>

<div class="pb"></div>
<div class="kop"><b>{{ school('school_name') }}</b></div>
<h2>DAFTAR HADIR PESERTA</h2>
<p>{{ $schedule->exam->name }} · {{ $schedule->classroom?->name ?? 'Peserta khusus' }} · {{ fmt_dt($schedule->date, 'd F Y') }}</p>
<table class="list">
    <tr><th style="width:24px">No</th><th>Nama</th><th style="width:80px">NISN</th><th style="width:70px">Mulai</th><th style="width:70px">Selesai</th><th style="width:80px">Keterangan</th></tr>
    @foreach ($rows as $i => $r)
        <tr><td>{{ $i + 1 }}</td><td>{{ $r['name'] }}</td><td>{{ $r['nisn'] }}</td><td>{{ $r['started_at']?->format('H:i') ?? '-' }}</td><td>{{ $r['submitted_at']?->format('H:i') ?? '-' }}</td><td>{{ $r['present'] ? 'Hadir' : 'Tidak hadir' }}</td></tr>
    @endforeach
</table>
<div class="foot">Dicetak dari KITAQ ONLINE · CiptaKita.id · {{ now()->format('d/m/Y H:i') }}</div>
</body></html>
