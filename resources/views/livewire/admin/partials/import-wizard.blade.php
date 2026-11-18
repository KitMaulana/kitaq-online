{{-- Wizard import 3 langkah (dipakai siswa, pengguna, soal). Variabel: $templateUrl, $columns (keterangan kolom), slot opsi via $options --}}
<x-ui.stepper :steps="['Unggah', 'Pratinjau & validasi', 'Hasil']" :current="$step" />

@if ($step === 1)
    <x-ui.card title="1. Unggah berkas Excel" subtitle="Gunakan template agar kolom terbaca benar. Baris pertama = judul kolom.">
        <div class="grid g-2">
            <div class="space-y-4">
                <x-ui.file-drop wire:model="file" accept=".xlsx" hint="Format .xlsx, maksimal 10 MB" />
                @error('file')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                @isset($extraUploadView) @include($extraUploadView) @endisset
            </div>
            <div class="space-y-3">
                <x-ui.button variant="secondary" icon="download" :href="$templateUrl">Unduh template</x-ui.button>
                <div class="field"><label>Bila data sudah ada</label>
                    <div class="flex gap-4"><x-ui.radio wire:model="mode" value="skip" label="Lewati" /><x-ui.radio wire:model="mode" value="update" label="Perbarui" /></div>
                </div>
                @isset($optionsView) @include($optionsView) @endisset
                <p class="text-sm muted">{{ $columns }}</p>
            </div>
        </div>
        <div class="mt-5 flex justify-end"><x-ui.button icon="chev" wire:click="analyze" loading="analyze">Periksa data</x-ui.button></div>
    </x-ui.card>
@elseif ($step === 2)
    @php $sum = $this->importSummary; @endphp
    <x-ui.card :padding="false" title="2. Pratinjau & validasi" :subtitle="$sum['valid'] . ' valid · ' . $sum['warnings'] . ' peringatan · ' . $sum['errors'] . ' galat (dari ' . $sum['total'] . ' baris)'">
        <x-slot:actions>
            <x-ui.checkbox wire:model.live="onlyErrors" label="Hanya galat" />
            @if ($sum['errors'])<x-ui.button variant="secondary" size="sm" icon="download" wire:click="downloadErrors">Laporan galat</x-ui.button>@endif
        </x-slot:actions>
        <div class="mt-4">
            <x-ui.table>
                <x-slot:head><th>Baris</th><th>Status</th>@foreach ($previewCols as $label)<th>{{ $label }}</th>@endforeach<th>Keterangan</th></x-slot:head>
                @foreach ($previewRows as $r)
                    @continue($onlyErrors && ! $r['errors'])
                    <tr>
                        <td class="tabular">{{ $r['line'] }}</td>
                        <td>@if ($r['errors'])<x-ui.badge tone="danger">✕ Galat</x-ui.badge>@elseif ($r['warnings'])<x-ui.badge tone="warning">⚠ Peringatan</x-ui.badge>@else<x-ui.badge tone="success">✓ Valid</x-ui.badge>@endif</td>
                        @foreach (array_keys($previewCols) as $k)<td class="text-sm">{{ is_scalar($r['data'][$k] ?? null) ? \Illuminate\Support\Str::limit((string) $r['data'][$k], 60) : '' }}</td>@endforeach
                        <td class="text-sm">{{ implode('; ', array_merge($r['errors'], $r['warnings'])) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
        <div class="tfoot">
            <x-ui.button variant="secondary" wire:click="resetImport">Ulangi</x-ui.button>
            <x-ui.button icon="check" wire:click="runImport" loading="runImport" :disabled="$sum['valid'] === 0">Import {{ $sum['valid'] }} baris valid</x-ui.button>
        </div>
    </x-ui.card>
@else
    <x-ui.card title="3. Hasil import">
        <div class="grid g-stats">
            <x-ui.stat label="Baru" :value="$result['created']" icon="plus" tone="success" />
            <x-ui.stat label="Diperbarui" :value="$result['updated']" icon="refresh" tone="info" />
            <x-ui.stat label="Dilewati" :value="$result['skipped']" icon="chev" tone="neutral" />
            <x-ui.stat label="Gagal" :value="$result['failed']" icon="x" :tone="$result['failed'] ? 'danger' : 'neutral'" />
        </div>
        <div class="flex gap-2 justify-end">
            @if ($result['failed'])<x-ui.button variant="secondary" icon="download" wire:click="downloadErrors">Unduh baris gagal</x-ui.button>@endif
            <x-ui.button variant="secondary" wire:click="resetImport">Import lagi</x-ui.button>
            <x-ui.button :href="$doneUrl">Selesai</x-ui.button>
        </div>
    </x-ui.card>
@endif
