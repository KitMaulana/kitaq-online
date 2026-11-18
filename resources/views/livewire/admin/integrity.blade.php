<div>
    <x-ui.page-header title="Analitik Integritas" subtitle="Jawaban salah identik, kecepatan tidak wajar, satu perangkat banyak akun. Ini daftar PERLU DITINJAU — bukan vonis otomatis." :crumbs="['Dashboard' => route('admin.dashboard'), 'Analitik Integritas']" />
    @include('livewire.admin.partials.schedule-picker', ['slot' => new \Illuminate\Support\HtmlString($schedule ? '<button type="button" class="btn btn-primary" wire:click="analyze" wire:loading.attr="disabled">Jalankan analisis</button>' : '')])
    @if ($schedule)
        <x-ui.alert tone="info">Dianalisis otomatis setelah jadwal ditutup. {{ $schedule->analyzed_at ? 'Terakhir: ' . fmt_dt($schedule->analyzed_at) : 'Belum pernah dianalisis.' }} Hasil tinjauan tidak mengubah nilai.</x-ui.alert>
        @forelse ($flags as $f)
            <x-ui.card class="mb-3" wire:key="flag-{{ $f->id }}">
                <div class="flex gap-4 flex-wrap items-start">
                    <div class="flex-1 min-w-0" style="min-width:260px">
                        <div class="flex gap-2 items-center mb-2"><x-ui.badge :tone="['identical_wrong' => 'danger', 'speed' => 'warning', 'shared_device' => 'info'][$f->kind]">{{ \App\Models\IntegrityFlag::KINDS[$f->kind] }}</x-ui.badge><span class="muted text-sm">skor {{ fmt_num($f->score, 2) }}</span>
                            @if ($f->reviewed_at)<x-ui.badge tone="success">Ditinjau: {{ \App\Models\IntegrityFlag::VERDICTS[$f->verdict] ?? $f->verdict }}</x-ui.badge>@endif</div>
                        <p class="font-semibold">@foreach ($f->student_ids as $sid){{ $students[$sid]->name ?? '#' . $sid }} <span class="muted text-xs">({{ $students[$sid]->nisn ?? '' }})</span>@if(! $loop->last), @endif @endforeach</p>
                        <p class="text-sm muted mt-1">
                            @if ($f->kind === 'identical_wrong') {{ $f->detail['identical_wrong'] }} jawaban salah identik (rata-rata pasangan {{ $f->detail['mean'] }}, sd {{ $f->detail['sd'] }}); selisih waktu menjawab median {{ $f->detail['median_time_gap_sec'] ?? '-' }} dtk.
                            @elseif ($f->kind === 'speed') {{ count($f->detail['fast_correct']) }} soal panjang dijawab benar dalam &lt; 8 detik.
                            @else Sidik perangkat & IP sama ({{ $f->detail['ip'] ?? '-' }}) dipakai {{ count($f->student_ids) }} akun pada sesi ini.
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2 items-end flex-wrap">
                        <x-ui.select wire:model="verdicts.{{ $f->id }}" label="Hasil tinjauan" :options="\App\Models\IntegrityFlag::VERDICTS" placeholder="Pilih…" :error="$errors->first('verdicts.' . $f->id)" />
                        <x-ui.input wire:model="notes.{{ $f->id }}" label="Catatan" />
                        <x-ui.button wire:click="review({{ $f->id }})" loading="review({{ $f->id }})">Simpan</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state icon="flag" title="Tidak ada temuan" text="Jalankan analisis atau tunggu jadwal ditutup." /></x-ui.card>
        @endforelse
    @else
        <x-ui.card><x-ui.empty-state icon="flag" title="Belum ada ujian yang dikerjakan" /></x-ui.card>
    @endif
</div>
