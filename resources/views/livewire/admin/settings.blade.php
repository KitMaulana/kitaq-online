<div x-data="dirtyGuard" data-clean-on-save>
    <x-ui.page-header title="Pengaturan Sekolah" subtitle="Identitas sekolah tampil di login siswa, kartu login, berita acara, dan laporan." :crumbs="['Dashboard' => route('admin.dashboard'), 'Pengaturan']" />
    <form wire:submit="save" class="space-y-4">
        <div class="grid g-2b">
            <x-ui.card title="Identitas sekolah">
                <div class="form-grid">
                    <x-ui.input wire:model="form.school_name" label="Nama sekolah" class="full" />
                    <x-ui.input wire:model="form.school_address" label="Alamat" class="full" />
                    <x-ui.input wire:model="form.school_city" label="Kota/kabupaten" />
                    <x-ui.input wire:model="form.site_url" label="Alamat situs ujian" placeholder="https://ujian.sekolah.sch.id" hint="Untuk kartu login & layar proyektor" />
                    <div class="full"><x-ui.textarea wire:model="form.school_letterhead" label="Kop surat (teks)" rows="2" /></div>
                    <div class="full">
                        <label class="text-sm font-semibold">Logo sekolah</label>
                        <div class="flex items-center gap-3 mt-2">
                            @if ($form['school_logo'])<img src="{{ route('school.logo') }}?v={{ md5((string) $form['school_logo']) }}" alt="Logo" style="height:56px">@endif
                            <div class="flex-1"><x-ui.file-drop wire:model="logo" accept="image/*" label="Unggah logo (maks 1 MB)" /></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
            <div class="space-y-4">
                <x-ui.card title="Kepala sekolah">
                    <div class="form-grid">
                        <x-ui.input wire:model="form.principal_name" label="Nama" class="full" />
                        <x-ui.input wire:model="form.principal_nip" label="NIP" />
                    </div>
                </x-ui.card>
                <x-ui.card title="Login & ujian">
                    <div class="space-y-4">
                        <x-ui.switch wire:model="form.force_change_password" label="Siswa wajib ganti password saat login pertama" hint="Default mati (sesuai keputusan sekolah)" />
                        <x-ui.select wire:model="form.default_preset" label="Profil keketatan default ujian baru" :options="\App\Support\ExamSettings::PRESETS" />
                    </div>
                </x-ui.card>
            </div>
        </div>
        <div class="card card-body flex justify-end"><x-ui.button type="submit" icon="save" loading="save">Simpan</x-ui.button></div>
    </form>
</div>
