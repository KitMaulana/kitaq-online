<div x-data="dirtyGuard" data-clean-on-save>
    <x-ui.page-header :title="$exam ? 'Ubah ujian' : 'Ujian baru'" :subtitle="$exam?->name ?? 'Atur bank soal, durasi, dan KITAQ Shield.'" :crumbs="['Ujian' => route('admin.exams'), $exam ? $exam->name : 'Baru']">
        <x-slot:actions>@if ($exam)<x-ui.button variant="secondary" icon="cal" :href="route('admin.schedules', ['create' => 1, 'exam' => $exam->id])">Buat jadwal</x-ui.button>@endif</x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :tabs="['umum' => 'Umum', 'tampilan' => 'Soal & nilai', 'shield' => 'KITAQ Shield', 'perangkat' => 'Perangkat & lokasi']" :active="$tab" wire-prop="tab" />

    <form wire:submit="save" class="space-y-4">
        <div @class(['hidden' => $tab !== 'umum'])>
            <x-ui.card title="Identitas ujian">
                <div class="form-grid">
                    <x-ui.input wire:model="form.name" label="Nama ujian" class="full" placeholder="PTS Matematika XII" />
                    <x-ui.select wire:model="form.type" label="Jenis" :options="\App\Models\Exam::TYPES" />
                    <x-ui.select wire:model.live="form.subject_id" label="Mata pelajaran" :options="$subjects" placeholder="Pilih…" />
                    <x-ui.select wire:model="form.question_bank_id" label="Bank soal" :options="$banks" placeholder="Pilih bank soal…" class="full" />
                    <x-ui.input type="number" wire:model="form.question_count" label="Jumlah soal diujikan" hint="0 = semua soal; N = acak N dari bank" />
                    <x-ui.input type="number" wire:model="form.duration_minutes" label="Durasi (menit)" />
                    <x-ui.input type="number" wire:model="form.kkm" label="KKM" />
                </div>
            </x-ui.card>
        </div>

        <div @class(['hidden' => $tab !== 'tampilan'])>
            <x-ui.card title="Soal & penilaian">
                <div class="form-grid">
                    <x-ui.switch wire:model="settings.shuffle_questions" label="Acak urutan soal per siswa" />
                    <x-ui.switch wire:model="settings.shuffle_options" label="Acak opsi jawaban per siswa" />
                    <x-ui.switch wire:model="settings.token_required" label="Wajib token" hint="Default menyala — mencegah joki dari luar ruang" />
                    <x-ui.select wire:model="settings.show_score" label="Tampilkan nilai ke siswa" :options="\App\Support\ExamSettings::SHOW_SCORE" />
                    <x-ui.input type="number" wire:model="settings.min_minutes_before_submit" label="Minimal waktu sebelum boleh kumpul (menit)" />
                    <x-ui.input wire:model="settings.packages_text" label="Paket soal (opsional)" hint="Mis. A,B,C — siswa dibagi bergiliran per paket" />
                </div>
            </x-ui.card>
        </div>

        <div @class(['hidden' => $tab !== 'shield'])>
            <div class="grid g-2">
                <x-ui.card title="Profil keketatan" subtitle="Server memutuskan peringatan/kunci (ViolationPolicy). Klien hanya melapor.">
                    <div class="pill-tabs mb-4">
                        @foreach (\App\Support\ExamSettings::PRESETS as $k => $label)
                            <button type="button" @class(['on' => $settings['preset'] === $k]) wire:click="$set('settings.preset', '{{ $k }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="form-grid mb-4">
                        <x-ui.input type="number" wire:model.live="settings.max_warnings" label="Batas peringatan" :hint="'Pelanggaran ke-' . ((int) $settings['max_warnings'] + 1) . ' mengunci ujian'" />
                        <x-ui.select wire:model="settings.timer_when_locked" label="Waktu saat terkunci" :options="['run' => 'Tetap berjalan', 'pause' => 'Dijeda']" />
                        <x-ui.switch wire:model="settings.canvas_mode" label="Mode Soal Kanvas" hint="Teks soal digambar di kanvas (anti terjemahan & ekstensi). Siswa berakomodasi pembaca layar dikecualikan." />
                    </div>
                    <b class="text-sm">Pengecualian per event</b>
                    <p class="muted text-xs mb-2">Kosong = ikut profil. Mis. "Kehilangan fokus → Catat" untuk HP yang sering memunculkan notifikasi.</p>
                    <div class="grid" style="grid-template-columns:1fr auto;gap:6px 12px;align-items:center">
                        @foreach (\App\Support\ExamSettings::EVENT_LABELS as $type => $label)
                            <span class="text-sm">{{ $label }}</span>
                            <select class="select" style="width:150px;height:34px" wire:model.live="settings.overrides.{{ $type }}" aria-label="Aksi {{ $label }}">
                                <option value="">Ikut profil</option>@foreach (\App\Support\ExamSettings::ACTIONS as $a => $al)<option value="{{ $a }}">{{ $al }}</option>@endforeach
                            </select>
                        @endforeach
                    </div>
                </x-ui.card>
                <x-ui.card title="Ringkasan aturan" :subtitle="'Profil ' . ucfirst($settings['preset'])">
                    @foreach ($summary as $label => $action)
                        <div class="flex justify-between text-sm py-1" style="border-bottom:1px dashed var(--border)"><span>{{ $label }}</span>
                            <x-ui.badge :tone="['Kunci' => 'danger', 'Peringatan' => 'warning', 'Catat' => 'neutral', 'Abaikan' => 'neutral'][$action] ?? 'neutral'">{{ $action }}</x-ui.badge></div>
                    @endforeach
                    <p class="muted text-xs mt-3">Beberapa peringatan dalam 3 detik dihitung satu kejadian.</p>
                </x-ui.card>
            </div>
        </div>

        <div @class(['hidden' => $tab !== 'perangkat'])>
            <x-ui.card title="Perangkat, koneksi & lokasi">
                <div class="form-grid">
                    <div class="field full"><label>Browser yang diizinkan</label>
                        <div class="flex flex-wrap gap-4">@foreach (\App\Support\ExamSettings::BROWSERS as $k => $label)<x-ui.checkbox wire:model="settings.browsers" value="{{ $k }}" :label="$label" />@endforeach</div>
                        <span class="hint">Default Chrome, Safari, Samsung Internet, Huawei Browser. Edge/Opera/Brave punya asisten AI bawaan.</span>
                        @error('settings.browsers')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <x-ui.switch wire:model="settings.ios_require_standalone" label="iPhone wajib mode aplikasi (Layar Utama)" />
                    <x-ui.switch wire:model="settings.require_fullscreen" label="Wajib layar penuh" hint="Android, HarmonyOS, iPad, laptop" />
                    <x-ui.select wire:model="settings.physical_keyboard_mobile" label="Keyboard fisik di HP/tablet" :options="['block' => 'Blokir', 'allow' => 'Izinkan']" />
                    <x-ui.select wire:model="settings.physical_keyboard_desktop" label="Keyboard fisik di laptop" :options="['allow' => 'Izinkan (mengetik jawaban)', 'block' => 'Blokir']" />
                    <x-ui.select wire:model.live="settings.offline_mode" label="Putus koneksi" :options="['log' => 'Hanya catat (default)', 'lock' => 'Kunci setelah N detik']" />
                    @if ($settings['offline_mode'] === 'lock')<x-ui.input type="number" wire:model="settings.offline_lock_after_sec" label="Kunci setelah (detik)" />@endif
                    <div class="full"><x-ui.switch wire:model="settings.ip_restrict" label="Batasi lokasi ke IP sekolah" hint="Pakai daftar IP/CIDR di jadwal, atau KITAQ_IP_ALLOWLIST di .env bila jadwal kosong." /></div>
                </div>
            </x-ui.card>
        </div>

        <div class="card card-body flex justify-end gap-2" style="position:sticky;bottom:12px;z-index:5">
            <x-ui.button variant="secondary" :href="route('admin.exams')">Batal</x-ui.button>
            <x-ui.button type="submit" icon="save" loading="save">Simpan</x-ui.button>
        </div>
    </form>
</div>
