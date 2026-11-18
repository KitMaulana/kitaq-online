<div x-data x-on:keydown.window.ctrl.s.prevent="$wire.save(false)" x-on:keydown.window.ctrl.enter.prevent="$wire.save(true)">
    <x-ui.page-header :title="($question ? 'Ubah' : 'Tambah') . ' soal #' . $currentNo" :subtitle="$bank->name" :crumbs="['Bank Soal' => route('admin.banks'), $bank->name => route('admin.banks.show', $bank), 'Editor']">
        <x-slot:actions>
            <span class="muted text-sm hide-mobile"><x-ui.kbd>Ctrl</x-ui.kbd>+<x-ui.kbd>S</x-ui.kbd> simpan · <x-ui.kbd>Ctrl</x-ui.kbd>+<x-ui.kbd>Enter</x-ui.kbd> simpan & berikutnya</span>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Navigasi nomor soal --}}
    <div class="pill-tabs mb-4" aria-label="Nomor soal">
        @foreach ($ids as $i => $id)
            <a href="{{ route('admin.banks.question', [$bank, $id]) }}" @class(['on' => $question && $question->id === $id])>{{ $i + 1 }}</a>
        @endforeach
        <a href="{{ route('admin.banks.question', [$bank]) }}" @class(['on' => ! $question])>+ Baru</a>
    </div>

    <div class="grid editor-grid">
        <div class="space-y-4">
            <x-ui.card title="Soal">
                <div class="form-grid">
                    <x-ui.select wire:model.live="type" label="Tipe soal" :options="\App\Models\Question::TYPES" />
                    <x-ui.select wire:model.live="stimulus_id" label="Stimulus bersama (opsional)" :options="$stimuli" placeholder="— tanpa stimulus —" />
                    <div class="full"><x-ui.editor wire:model.live.debounce.500ms="content" label="Teks soal" upload="editorUpload" /></div>
                    <div class="full">
                        <label class="text-sm font-semibold">Gambar soal (opsional)</label>
                        @if ($image)
                            <div class="flex items-center gap-3 mt-2"><img src="{{ \App\Services\Questions\ImageOptimizer::adminUrl($image) }}" alt="" style="max-height:90px;border-radius:10px"><x-ui.button size="sm" variant="soft-danger" wire:click="removeImage('question')">Hapus</x-ui.button></div>
                        @else
                            <x-ui.file-drop wire:model="imageUpload" accept="image/*" label="Unggah gambar" hint="Otomatis WebP, maks 1600 px" />
                        @endif
                        @error('imageUpload')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="match ($type) { 'pg' => 'Opsi & kunci (pilih satu)', 'pgk' => 'Opsi & kunci (boleh lebih dari satu)', 'bs' => 'Pernyataan Benar/Salah', 'jodoh' => 'Pasangan menjodohkan', 'isian' => 'Kunci isian singkat', default => 'Uraian' }">
                @error('options')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                @if (in_array($type, ['pg', 'pgk', 'bs']))
                    @foreach ($options as $i => $o)
                        <div class="flex gap-3 items-start mb-3" wire:key="opt-{{ $type }}-{{ $i }}">
                            <span class="avatar" style="flex:none">{{ $o['key'] }}</span>
                            <div class="flex-1 space-y-2">
                                <textarea class="textarea" style="min-height:52px" rows="2" wire:model.live.debounce.500ms="options.{{ $i }}.content" placeholder="{{ $type === 'bs' ? 'Pernyataan' : 'Teks opsi' }} {{ $o['key'] }}" aria-label="Opsi {{ $o['key'] }}"></textarea>
                                <div class="flex items-center gap-2 text-sm">
                                    @if ($o['image'])<img src="{{ \App\Services\Questions\ImageOptimizer::adminUrl($o['image']) }}" alt="" style="height:40px;border-radius:6px"><button type="button" class="btn btn-ghost sm" wire:click="removeImage('options', {{ $i }})">Hapus gambar</button>
                                    @else<label class="btn btn-ghost sm cursor-pointer"><x-ui.icon name="image" size="sm" />Gambar<input type="file" accept="image/*" class="sr" wire:model="optionUploads.{{ $i }}"></label>@endif
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-1 pt-2">
                                @if ($type === 'pg')<x-ui.radio wire:model.live="keyPg" value="{{ $o['key'] }}" label="Kunci" />
                                @elseif ($type === 'pgk')<x-ui.checkbox wire:model.live="keyPgk" value="{{ $o['key'] }}" label="Kunci" />
                                @else
                                    <select class="select" style="width:auto;height:34px" wire:model.live="keyBs.{{ $o['key'] }}" aria-label="Kunci pernyataan {{ $o['key'] }}"><option value="">—</option><option value="B">Benar</option><option value="S">Salah</option></select>
                                @endif
                                @if (count($options) > 2)<button type="button" class="icon-btn" aria-label="Hapus opsi" wire:click="removeOption({{ $i }})"><x-ui.icon name="trash" size="sm" /></button>@endif
                            </div>
                        </div>
                    @endforeach
                    @error('keyPg')<span class="error">{{ $message }}</span>@enderror @error('keyPgk')<span class="error">{{ $message }}</span>@enderror @error('keyBs')<span class="error">{{ $message }}</span>@enderror
                    @if (count($options) < 8)<x-ui.button size="sm" variant="secondary" icon="plus" wire:click="addOption">Tambah {{ $type === 'bs' ? 'pernyataan' : 'opsi' }}</x-ui.button>@endif
                @elseif ($type === 'jodoh')
                    <div class="grid g-2b">
                        <div><b class="text-sm">Pernyataan kiri</b>
                            @foreach ($left as $i => $l)
                                <div class="flex gap-2 items-center mt-2" wire:key="l-{{ $i }}"><span class="avatar">{{ $l['key'] }}</span><input class="input" wire:model.live.debounce.500ms="left.{{ $i }}.content" aria-label="Pernyataan {{ $l['key'] }}">
                                    <select class="select" style="width:84px" wire:model.live="keyJodoh.{{ $l['key'] }}" aria-label="Pasangan {{ $l['key'] }}"><option value="">—</option>@foreach ($right as $r)<option value="{{ $r['key'] }}">{{ strtoupper($r['key']) }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                        <div><b class="text-sm">Pilihan kanan</b>
                            @foreach ($right as $i => $r)
                                <div class="flex gap-2 items-center mt-2" wire:key="r-{{ $i }}"><span class="avatar">{{ strtoupper($r['key']) }}</span><input class="input" wire:model.live.debounce.500ms="right.{{ $i }}.content" aria-label="Pilihan {{ $r['key'] }}">
                                    <label class="icon-btn cursor-pointer" aria-label="Gambar"><x-ui.icon name="image" size="sm" /><input type="file" accept="image/*" class="sr" wire:model="optionUploads.r{{ $i }}"></label></div>
                            @endforeach
                        </div>
                    </div>
                    @error('keyJodoh')<span class="error">{{ $message }}</span>@enderror
                    <div class="mt-3"><x-ui.button size="sm" variant="secondary" icon="plus" wire:click="addPair">Tambah pasangan</x-ui.button></div>
                @elseif ($type === 'isian')
                    <x-ui.textarea wire:model.live.debounce.500ms="keyIsian" label="Jawaban benar (satu variasi per baris)" hint="Huruf besar/kecil & spasi ganda diabaikan. Contoh: Serang ↵ Kota Serang" />
                    @error('keyIsian')<span class="error">{{ $message }}</span>@enderror
                    <div class="form-grid mt-3">
                        <x-ui.switch wire:model.live="numeric" label="Isian angka" hint="Keyboard KITAQ menampilkan papan angka; 1.500,5 = 1500.5" />
                        <x-ui.input type="number" wire:model="max_length" label="Batas karakter" placeholder="120" />
                    </div>
                @else
                    <p class="muted text-sm mb-3">Uraian dinilai guru di menu Koreksi Uraian. Tulis rubrik di kolom pembahasan.</p>
                    <x-ui.input type="number" wire:model="max_length" label="Batas karakter" placeholder="3000" />
                @endif
            </x-ui.card>

            <x-ui.card title="Penilaian & lainnya">
                <div class="form-grid">
                    <x-ui.input type="number" step="0.5" min="0.5" wire:model="weight" label="Bobot" />
                    <x-ui.select wire:model="difficulty" label="Tingkat kesukaran" :options="['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit']" placeholder="—" />
                    <x-ui.select wire:model="cognitive_level" label="Level kognitif" :options="['L1' => 'L1 Pemahaman', 'L2' => 'L2 Aplikasi', 'L3' => 'L3 Penalaran', 'C1' => 'C1', 'C2' => 'C2', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6']" placeholder="—" />
                    <x-ui.select wire:model="package" label="Paket soal" :options="['A' => 'Paket A', 'B' => 'Paket B', 'C' => 'Paket C']" placeholder="Semua paket" hint="Kosong = dipakai semua paket" />
                    <div class="full"><x-ui.editor wire:model="explanation" label="Pembahasan / rubrik (tidak dikirim ke siswa)" :hint="null" /></div>
                    <div>
                        <label class="text-sm font-semibold">Audio (opsional)</label>
                        @if ($audio)<p class="text-sm mt-1">🎧 {{ basename($audio) }} <button type="button" class="btn btn-ghost sm" wire:click="$set('audio', null)">Hapus</button></p>
                        @else<x-ui.file-drop wire:model="audioUpload" accept=".mp3,.m4a,.ogg,.wav" label="Unggah audio" />@endif
                    </div>
                    @if ($audio)<x-ui.input type="number" min="1" max="10" wire:model="audio_limit" label="Batas putar" />@endif
                </div>
            </x-ui.card>

            <div class="card card-body flex gap-2 justify-end" style="position:sticky;bottom:12px;z-index:5">
                <x-ui.button variant="secondary" :href="route('admin.banks.show', $bank)">Batal</x-ui.button>
                <x-ui.button variant="secondary" icon="save" wire:click="save(false)" loading="save">Simpan</x-ui.button>
                <x-ui.button icon="chev" wire:click="save(true)">Simpan & Berikutnya</x-ui.button>
            </div>
        </div>

        <div style="position:sticky;top:84px">
            <p class="text-sm font-semibold mb-2 text-center">Pratinjau tampilan siswa</p>
            <div class="flex justify-center">@include('livewire.admin.partials.question-preview', ['q' => $preview])</div>
        </div>
    </div>
</div>
