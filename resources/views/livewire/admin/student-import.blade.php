<div>
    <x-ui.page-header title="Import Siswa" subtitle="Dari Excel (template_siswa.xlsx). Password awal = tanggal lahir DDMMYYYY." :crumbs="['Siswa' => route('admin.students'), 'Import']" />
    @include('livewire.admin.partials.import-wizard', [
        'templateUrl' => route('admin.templates', ['file' => 'siswa']),
        'doneUrl' => route('admin.students'),
        'columns' => 'Kolom: nisn*, nama*, tanggal_lahir* (20/10/2008, 2008-10-20, 20 Oktober 2008, atau sel Tanggal), jenis_kelamin* (L/P), kelas*, nis, tempat_lahir. Format kolom NISN sebagai Teks.',
        'previewCols' => ['nisn' => 'NISN', 'name' => 'Nama', 'birth_date' => 'Tgl lahir', 'gender' => 'JK', 'kelas' => 'Kelas'],
        'optionsView' => 'livewire.admin.partials.import-opt-classes',
    ])
</div>
