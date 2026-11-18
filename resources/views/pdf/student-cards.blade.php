<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
    td.card { width: 50%; border: 1px solid #c9d3e6; border-radius: 8px; padding: 8px 10px; vertical-align: top; height: 52mm; }
    .head { border-bottom: 2px solid #022684; padding-bottom: 4px; margin-bottom: 6px; }
    .head b { color: #022684; font-size: 11px; }
    .head small { color: #5b6b88; display: block; font-size: 8px; }
    .name { font-size: 12px; font-weight: bold; margin: 2px 0 4px; }
    .kv td { padding: 1px 4px 1px 0; font-size: 9.5px; }
    .nisn { font-family: DejaVu Sans Mono, monospace; font-size: 13px; font-weight: bold; letter-spacing: 1px; color: #022684; }
    .note { margin-top: 5px; font-size: 8px; color: #5b6b88; }
    .foot { font-size: 7px; color: #93a3c4; margin-top: 4px; }
</style>
</head>
<body>
<table class="grid">
    @foreach ($students->chunk(2) as $pair)
        <tr>
            @foreach ($pair as $s)
                <td class="card">
                    <div class="head"><b>KARTU LOGIN UJIAN ONLINE</b><small>{{ $school }}</small></div>
                    <table style="width:100%"><tr>
                        <td style="vertical-align:top">
                            <div class="name">{{ $s->name }}</div>
                            <table class="kv">
                                <tr><td>Kelas</td><td>: {{ $s->classroom?->name ?? '-' }}</td></tr>
                                <tr><td>Username</td><td>: <span class="nisn">{{ $s->nisn }}</span></td></tr>
                                <tr><td>Password</td><td>: tanggal lahir (DDMMYYYY)</td></tr>
                                <tr><td>Situs</td><td>: {{ $url }}</td></tr>
                            </table>
                            <div class="note">Contoh: lahir 1 Januari 2009 → password 01012009. Token ujian dibacakan pengawas di ruang ujian.</div>
                        </td>
                        <td style="width:30mm;text-align:right;vertical-align:top"><img src="{{ $qr }}" style="width:26mm;height:26mm"></td>
                    </tr></table>
                    <div class="foot">KITAQ ONLINE · CiptaKita.id</div>
                </td>
            @endforeach
            @if ($pair->count() === 1)<td></td>@endif
        </tr>
    @endforeach
</table>
@if ($students->isEmpty())<p>Tidak ada siswa.</p>@endif
</body>
</html>
