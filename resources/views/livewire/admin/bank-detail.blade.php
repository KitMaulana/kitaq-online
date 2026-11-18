<div>
    <x-ui.page-header :title="$bank->name" :subtitle="$bank->subject->name . ' · ' . $questions->total() . ' soal'" :crumbs="['Bank Soal' => route('admin.banks'), $bank->name]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="upload" wire:click="$set('showImport', true)">Import Excel + ZIP</x-ui.button>
            <x-ui.button icon="plus" :href="route('admin.banks.question', [$bank])">Tambah Soal</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :tabs="['soal' => 'Soal', 'stimulus' => 'Stimulus bersama']" :active="$tab" wire-prop="tab" />

    @if ($tab === 'soal')
        <div class="pill-tabs mb-3">
            <button type="button" @class(['on' => $type === '']) wire:click="$set('type', '')">Semua ({{ $counts->sum() }})</button>
            @foreach (\App\Models\Question::TYPES as $k => $label)
                <button type="button" @class(['on' => $type === $k]) wire:click="$set('type', '{{ $k }}')">{{ $label }} ({{ $counts[$k] ?? 0 }})</button>
            @endforeach
        </div>
        <x-ui.card :padding="false">
            <div class="toolbar"><div class="grow input-ico"><x-ui.icon name="search" /><input class="input" placeholder="Cari teks soal…" wire:model.live.debounce.300ms="search"></div></div>
            <x-ui.table>
                <x-slot:head><th style="width:60px">No</th><th>Soal</th><th>Tipe</th><th>Bobot</th><th>Kesulitan</th><th></th></x-slot:head>
                @foreach ($questions as $i => $q)
                    <tr wire:key="q-{{ $q->id }}">
                        <td class="tabular">{{ $questions->firstItem() + $i }}</td>
                        <td style="max-width:520px">
                            <a href="{{ route('admin.banks.question', [$bank, $q]) }}" class="font-semibold" style="color:var(--text)">{{ $q->plainText(140) ?: '(gambar)' }}</a>
                            <div class="text-xs muted">@if($q->stimulus)📄 {{ $q->stimulus->title ?? 'Stimulus' }} · @endif @if($q->image)🖼 gambar · @endif{{ $q->options->count() }} opsi @if($q->package)· Paket {{ $q->package }}@endif</div>
                        </td>
                        <td><x-ui.badge tone="info">{{ $q->typeLabel() }}</x-ui.badge></td>
                        <td class="tabular">{{ rtrim(rtrim(number_format($q->weight, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="text-sm">{{ ucfirst((string) $q->difficulty) ?: '-' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="icon-btn" aria-label="Naik" wire:click="move({{ $q->id }}, -1)">↑</button>
                            <button type="button" class="icon-btn" aria-label="Turun" wire:click="move({{ $q->id }}, 1)">↓</button>
                            <x-ui.dropdown>
                                <button type="button" wire:click="preview({{ $q->id }})"><x-ui.icon name="phone" />Pratinjau HP</button>
                                <a href="{{ route('admin.banks.question', [$bank, $q]) }}"><x-ui.icon name="edit" />Ubah</a>
                                <button type="button" wire:click="duplicate({{ $q->id }})"><x-ui.icon name="copy" />Gandakan</button>
                                <hr><button type="button" class="danger" wire:click="askDelete({{ $q->id }})"><x-ui.icon name="trash" />Hapus</button>
                            </x-ui.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($questions->isEmpty())
                <x-ui.empty-state icon="fq" title="Belum ada soal" text="Tambah soal satu per satu atau import dari template_soal.xlsx beserta ZIP gambar.">
                    <x-ui.button icon="plus" :href="route('admin.banks.question', [$bank])">Tambah Soal</x-ui.button>
                </x-ui.empty-state>
            @endif
            <div class="tfoot">{{ $questions->links('livewire::simple-tailwind') }}</div>
        </x-ui.card>
    @else
        <x-ui.card title="Stimulus bersama" subtitle="Bacaan/wacana yang dipakai beberapa soal. Saat diacak, soal berstimulus sama tetap berdekatan.">
            <x-slot:actions><x-ui.button size="sm" icon="plus" wire:click="editStimulus">Stimulus baru</x-ui.button></x-slot:actions>
            @forelse ($stimuli as $s)
                <div class="svc"><div class="flex-1 min-w-0"><b>{{ $s->title ?? 'Tanpa judul' }}</b><small>{{ \Illuminate\Support\Str::limit(strip_tags($s->content), 140) }} · {{ $s->questions_count }} soal</small></div>
                    <x-ui.button size="sm" variant="secondary" wire:click="editStimulus({{ $s->id }})">Ubah</x-ui.button>
                    <x-ui.button size="sm" variant="soft-danger" wire:click="deleteStimulus({{ $s->id }})" wire:confirm="Hapus stimulus ini?">Hapus</x-ui.button>
                </div>
            @empty
                <x-ui.empty-state icon="file" title="Belum ada stimulus" />
            @endforelse
        </x-ui.card>
    @endif

    <x-ui.modal wire:model="showPreview" title="Pratinjau tampilan siswa">
        @if ($previewQ)<div class="flex justify-center">@include('livewire.admin.partials.question-preview', ['q' => $previewQ])</div>@endif
    </x-ui.modal>

    <x-ui.modal wire:model="showStimulus" :title="$stimulusId ? 'Ubah stimulus' : 'Stimulus baru'" size="lg">
        <div class="space-y-4">
            <x-ui.input wire:model="stimTitle" label="Judul (opsional)" />
            <x-ui.editor wire:model="stimContent" label="Teks bacaan" upload="editorUpload" />
        </div>
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button><x-ui.button icon="save" wire:click="saveStimulus" loading="saveStimulus">Simpan</x-ui.button></x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showImport" title="Import soal (Excel + ZIP gambar)" size="lg">
        <x-ui.stepper :steps="['Unggah', 'Pratinjau', 'Hasil']" :current="$importStep" />
        @if ($importStep === 1)
            <div class="space-y-4">
                <x-ui.file-drop wire:model="xlsx" accept=".xlsx" label="Berkas soal (.xlsx)" hint="template_soal.xlsx — sheet soal & stimulus" />
                @error('xlsx')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                <x-ui.file-drop wire:model="zip" accept=".zip" label="ZIP gambar (opsional)" hint="Semua gambar dalam satu ZIP tanpa folder. JPG/PNG/WEBP maks 2 MB; otomatis dikonversi WebP ≤ 1600 px." />
                @error('zip')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                <p class="text-sm muted">Kunci — PG: <b>B</b> · PGK: <b>A,C</b> · BS: <b>B,S,B</b> · JODOH: <b>1-C,2-A</b> · ISIAN: <b>Serang|Kota Serang</b> · URAIAN: kosong.</p>
            </div>
        @elseif ($importStep === 2)
            @php $err = collect($importRows)->filter(fn ($r) => $r['errors'])->count(); @endphp
            @foreach ($zipErrors as $ze)<x-ui.alert tone="warning">{{ $ze }}</x-ui.alert>@endforeach
            <p class="mb-3"><b>{{ count($importRows) - $err }}</b> soal valid · <b style="color:var(--accent)">{{ $err }}</b> galat · {{ count($importStimuli) }} stimulus</p>
            <x-ui.table>
                <x-slot:head><th>Baris</th><th>Tipe</th><th>Soal</th><th>Status</th></x-slot:head>
                @foreach ($importRows as $r)
                    <tr><td class="tabular">{{ $r['line'] }}</td><td>{{ strtoupper((string) $r['data']['type']) }}</td><td class="text-sm">{{ \Illuminate\Support\Str::limit($r['data']['text'], 80) }}</td>
                        <td class="text-sm">@if ($r['errors'])<span style="color:var(--accent)">✕ {{ implode('; ', $r['errors']) }}</span>@else<span style="color:var(--success)">✓ Valid</span>@endif</td></tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.alert tone="info" title="Import selesai">Soal valid sudah ditambahkan ke bank soal. Baris bergalat dilewati.</x-ui.alert>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="closeImport">{{ $importStep === 3 ? 'Selesai' : 'Batal' }}</x-ui.button>
            @if ($importStep === 1)<x-ui.button wire:click="analyzeImport" loading="analyzeImport">Periksa</x-ui.button>@endif
            @if ($importStep === 2)<x-ui.button icon="check" wire:click="runImport" loading="runImport">Import soal valid</x-ui.button>@endif
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm wire:model="confirmDelete" title="Hapus soal?" message="Soal akan dihapus dari bank beserta jawaban siswa untuk soal ini. Jangan hapus soal yang sudah dipakai ujian." action="delete" confirm-label="Hapus" />
</div>
