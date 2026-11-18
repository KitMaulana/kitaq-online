<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<style>
    @page { margin: 12mm; } body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0f172a; }
    h1 { font-size: 14px; margin: 0; color: #022684; } p { margin: 2px 0 8px; color: #5b6b88; }
    table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #c9d3e6; padding: 3px 5px; } th { background: #f1f5fb; }
    .sum td { border: 0; padding: 2px 12px 2px 0; }
</style></head>
<body>
<h1>Rekap Nilai — {{ $schedule->exam->name }}</h1>
<p>{{ school('school_name') }} · {{ $schedule->exam->subject->name }} · {{ $schedule->classroom?->name ?? 'Peserta khusus' }} · {{ fmt_dt($schedule->date, 'd F Y') }} · KKM {{ $schedule->exam->kkm }}</p>
<table class="sum"><tr><td>Rata-rata: <b>{{ $summary['avg'] ?? '-' }}</b></td><td>Tertinggi: <b>{{ $summary['max'] ?? '-' }}</b></td><td>Terendah: <b>{{ $summary['min'] ?? '-' }}</b></td><td>Tuntas: <b>{{ $summary['pass_pct'] !== null ? $summary['pass_pct'] . '%' : '-' }}</b></td></tr></table>
<table style="margin-top:6px">
    <tr><th>#</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>B</th><th>S</th><th>K</th><th>Nilai</th><th>Tuntas</th><th>Pelanggaran</th></tr>
    @foreach ($rows as $i => $r)
        <tr><td>{{ $i + 1 }}</td><td>{{ $r['nisn'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['class'] }}</td><td>{{ $r['correct'] ?? '-' }}</td><td>{{ $r['wrong'] ?? '-' }}</td><td>{{ $r['blank'] ?? '-' }}</td>
            <td>{{ $r['score'] !== null ? number_format($r['score'], 1, ',', '.') : '-' }}</td><td>{{ $r['passed'] === null ? '-' : ($r['passed'] ? 'Ya' : 'Tidak') }}</td><td>{{ $r['violations'] }}</td></tr>
    @endforeach
</table>
<p style="margin-top:8px;font-size:8px">KITAQ ONLINE · CiptaKita.id · dicetak {{ now()->format('d/m/Y H:i') }}</p>
</body></html>
