{{-- Pratinjau tampilan siswa dalam bingkai HP. Markup & ukuran mengikuti ruang ujian (exam.css / exam-mobile-prototype).
     Variabel: $q (Question atau array draft: type, content, image, options[[key,content,image,side]], stimulus, key), $showKey --}}
@php
    $isArr = is_array($q);
    $type = $isArr ? $q['type'] : $q->type;
    $content = $isArr ? $q['content'] : $q->content;
    $image = $isArr ? ($q['image'] ?? null) : \App\Services\Questions\ImageOptimizer::adminUrl($q->image);
    $stim = $isArr ? ($q['stimulus'] ?? null) : $q->stimulus?->content;
    $opts = $isArr ? ($q['options'] ?? []) : $q->options->map(fn ($o) => ['key' => $o->key, 'content' => $o->content, 'image' => \App\Services\Questions\ImageOptimizer::adminUrl($o->image), 'side' => $o->side])->all();
    $key = $isArr ? ($q['key'] ?? null) : $q->answer_key;
    $showKey = $showKey ?? true;
@endphp
<div class="phone-frame">
    <div class="pf-screen q-preview" x-data x-math>
        <div class="flex justify-between items-center mb-3 text-xs muted"><b>Soal 1</b><span class="status st-active">12:34</span></div>
        @if ($stim)<div class="stim mb-3" style="background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--info);border-radius:14px;padding:12px"><b class="text-xs" style="color:var(--info)">BACAAN</b><div class="prose text-sm">{!! $stim !!}</div></div>@endif
        <div class="q-text prose">{!! $content ?: '<p class="muted">Teks soal…</p>' !!}</div>
        @if ($image)<img src="{{ $image }}" alt="Gambar soal" class="mb-3">@endif

        @if (in_array($type, ['pg', 'pgk']))
            @foreach ($opts as $o)
                @php $ok = $showKey && ($type === 'pg' ? (string) $key === (string) $o['key'] : in_array($o['key'], (array) $key, true)); @endphp
                <div @class(['opt', 'ok' => $ok])><span class="k">{{ $o['key'] }}</span><div class="prose flex-1">{!! $o['content'] !!}@if(! empty($o['image']))<img src="{{ $o['image'] }}" alt="">@endif</div>@if($ok)<span class="text-xs" style="color:var(--success)">✓</span>@endif</div>
            @endforeach
        @elseif ($type === 'bs')
            @foreach ($opts as $o)
                <div class="opt" style="display:block"><div class="prose mb-2">{!! $o['content'] !!}</div>
                    <div class="flex gap-2">
                        <span @class(['badge', 'tone-success' => $showKey && (($key[$o['key']] ?? null) === true)])>Benar</span>
                        <span @class(['badge', 'tone-danger' => $showKey && (($key[$o['key']] ?? null) === false)])>Salah</span>
                    </div>
                </div>
            @endforeach
        @elseif ($type === 'jodoh')
            @php $right = collect($opts)->where('side', 'right')->keyBy('key'); @endphp
            @foreach (collect($opts)->where('side', 'left') as $o)
                <div class="opt" style="display:block"><div class="prose"><b>{{ $o['key'] }}.</b> {!! strip_tags((string) $o['content'], '<b><i><em><strong><sub><sup>') !!}</div>
                    <div class="text-xs mt-1 muted">Pasangan: @if($showKey && isset($key[$o['key']]))<b style="color:var(--success)">{{ strtoupper($key[$o['key']]) }}. {{ strip_tags((string) ($right[$key[$o['key']]]['content'] ?? '')) }}</b>@else — pilih — @endif</div>
                </div>
            @endforeach
            <div class="text-xs muted mt-2">Pilihan: @foreach ($right as $r){{ strtoupper($r['key']) }}. {{ strip_tags((string) $r['content']) }}@if(! $loop->last) · @endif @endforeach</div>
        @else
            <div class="kq-textbox" style="min-height:{{ $type === 'uraian' ? 120 : 52 }}px;border:2px solid var(--border);border-radius:14px;padding:12px;color:var(--text-muted)">Ketuk untuk {{ $type === 'uraian' ? 'menulis jawaban uraian' : 'mengisi jawaban' }} (Keyboard KITAQ)</div>
            @if ($showKey && $type === 'isian' && $key)<p class="text-xs mt-2" style="color:var(--success)">Kunci: {{ implode(' | ', (array) $key) }}</p>@endif
        @endif
    </div>
</div>
