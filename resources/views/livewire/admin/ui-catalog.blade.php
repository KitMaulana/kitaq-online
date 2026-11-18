<div class="space-y-4">
    <x-ui.page-header title="Katalog UI" subtitle="Semua komponen x-ui.* (hanya lingkungan lokal)" />
    <x-ui.card title="Tombol & badge">
        <div class="flex flex-wrap gap-2 mb-4">
            <x-ui.button icon="plus">Primary</x-ui.button><x-ui.button variant="secondary">Secondary</x-ui.button>
            <x-ui.button variant="ghost">Ghost</x-ui.button><x-ui.button variant="danger" icon="trash">Danger</x-ui.button>
            <x-ui.button variant="success">Success</x-ui.button><x-ui.button size="sm">Small</x-ui.button><x-ui.button size="lg">Large</x-ui.button>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['neutral', 'success', 'warning', 'danger', 'info', 'brand'] as $t)<x-ui.badge :tone="$t" dot>{{ $t }}</x-ui.badge>@endforeach
            @foreach (['not_started', 'in_progress', 'warned', 'locked', 'disconnected', 'submitted'] as $s)<x-ui.status-chip :status="$s" />@endforeach
        </div>
    </x-ui.card>
    <div class="grid g-stats">
        <x-ui.stat label="Siswa aktif" value="1.284" icon="users" delta="+12 minggu ini" delta-tone="up" />
        <x-ui.stat label="Terkunci" value="3" icon="lock" tone="danger" />
    </div>
    <x-ui.card title="Form">
        <div class="form-grid">
            <x-ui.input wire:model="name" label="Nama" hint="Contoh petunjuk" icon="user" />
            <x-ui.select label="Pilihan" :options="['a' => 'Satu', 'b' => 'Dua']" placeholder="Pilih…" />
            <x-ui.textarea label="Catatan" class="full" />
            <x-ui.switch wire:model="flag" label="Wajib token" hint="Default menyala" />
            <x-ui.checkbox label="Centang" />
            <div class="full"><x-ui.file-drop accept=".xlsx" hint="Maks 10 MB" /></div>
        </div>
    </x-ui.card>
    <x-ui.card title="Lain-lain">
        <x-ui.stepper :steps="['Unggah', 'Pratinjau', 'Hasil']" :current="2" />
        <x-ui.progress :value="64" />
        <div class="mt-4"><x-ui.alert tone="warning" title="Cron terlambat">Cron terakhir jalan 9 menit lalu.</x-ui.alert></div>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" wire:click="$set('showModal', true)">Buka modal</x-ui.button>
            <x-ui.button variant="secondary" wire:click="$set('showConfirm', true)">Konfirmasi</x-ui.button>
            <x-ui.button variant="secondary" wire:click="$set('showDrawer', true)">Drawer</x-ui.button>
            <x-ui.dropdown><a href="#">Lihat</a><button class="danger">Hapus</button></x-ui.dropdown>
            <x-ui.kbd>Ctrl</x-ui.kbd><x-ui.kbd>K</x-ui.kbd>
        </div>
        <x-ui.skeleton :rows="3" />
        <x-ui.empty-state title="Belum ada data" text="Contoh state kosong." />
        <x-ui.chart type="doughnut" :labels="['Mengerjakan', 'Selesai', 'Terkunci']" :datasets="[['data' => [20, 12, 2]]]" :height="200" />
    </x-ui.card>
    <x-ui.modal wire:model="showModal" title="Contoh modal"><p>Isi modal.</p><x-slot:footer><x-ui.button wire:click="$set('showModal', false)">Tutup</x-ui.button></x-slot:footer></x-ui.modal>
    <x-ui.confirm wire:model="showConfirm" title="Hapus data?" message="Data yang dihapus tidak dapat dikembalikan." action="ping" confirm-label="Hapus" />
    <x-ui.drawer wire:model="showDrawer" title="Detail"><p>Isi drawer.</p></x-ui.drawer>
</div>
