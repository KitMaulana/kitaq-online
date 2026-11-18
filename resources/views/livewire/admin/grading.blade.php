<div>
    <x-ui.page-header title="Koreksi Uraian" subtitle="Beri skor jawaban uraian. Nilai akhir siswa dihitung ulang otomatis." :crumbs="['Dashboard' => route('admin.dashboard'), 'Koreksi Uraian']" />
    @include('livewire.admin.partials.schedule-picker')
    @if ($schedule)
        <div class="flex gap-3 items-center mb-4">
            <x-ui.badge tone="warning" dot>{{ $stats['pending'] }} belum dikoreksi</x-ui.badge>
            <x-ui.badge tone="success" dot>{{ $stats['done'] }} sudah</x-ui.badge>
            <x-ui.checkbox wire:model.live="onlyPending" label="Hanya yang belum dikoreksi" />
        </div>
        @forelse ($answers as $a)
            <x-ui.card class="mb-3" wire:key="ans-{{ $a->id }}">
                <div class="grid g-2">
                    <div>
                        <p class="text-xs muted mb-1">{{ $a->attempt->student->name }} · {{ $a->attempt->student->nisn }}</p>
                        <div class="prose text-sm mb-3" style="color:var(--text-muted)">{!! $a->question->content !!}</div>
                        <div style="white-space:pre-wrap;background:var(--surface-2);border-radius:12px;padding:12px;font-size:15px">{{ is_string($a->answer) ? $a->answer : json_encode($a->answer) }}</div>
                    </div>
                    <div>
                        @if ($a->question->explanation)<b class="text-sm">Rubrik / pembahasan</b><div class="prose text-sm mb-3">{!! $a->question->explanation !!}</div>@endif
                        <div class="flex gap-2 items-end">
                            <div class="field"><label for="sc-{{ $a->id }}">Skor (0–{{ rtrim(rtrim(number_format($a->question->weight, 2, '.', ''), '0'), '.') }})</label>
                                <input id="sc-{{ $a->id }}" type="number" step="0.5" min="0" max="{{ $a->question->weight }}" class="input" style="width:120px" wire:model="scores.{{ $a->id }}" wire:keydown.enter="grade({{ $a->id }})"></div>
                            <x-ui.button icon="save" wire:click="grade({{ $a->id }})" loading="grade({{ $a->id }})">Simpan</x-ui.button>
                        </div>
                        @error('scores.' . $a->id)<span class="error">{{ $message }}</span>@enderror
                        @if ($a->score !== null)<p class="text-xs mt-2" style="color:var(--success)">Tersimpan: {{ $a->score }}</p>@endif
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state icon="checkc" title="Tidak ada jawaban uraian untuk dikoreksi" /></x-ui.card>
        @endforelse
    @else
        <x-ui.card><x-ui.empty-state icon="check" title="Belum ada ujian yang dikerjakan" /></x-ui.card>
    @endif
</div>
