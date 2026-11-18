<?php

use App\Models\ActivityLog;
use App\Models\ExamSchedule;
use App\Models\ShieldEvent;
use App\Models\Student;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

it('halaman Kesehatan Sistem menampilkan cron, antrean, PHP, database', function () {
    $this->artisan('kitaq:tick')->assertSuccessful();
    $this->actingAs(staff('operator'))->get(route('admin.health'))->assertOk()
        ->assertSee('Cron (schedule:run)')->assertSee('Antrean (jobs)')->assertSee(PHP_VERSION)->assertSee('schedule:run');
    $this->actingAs(staff('pengawas'))->get(route('admin.health'))->assertForbidden();
});

it('backup membuat ZIP terenkripsi dan menghapus backup melewati retensi', function () {
    Storage::fake('local');
    student();
    $path = app(BackupService::class)->run();
    Storage::disk('local')->assertExists($path);
    expect($path)->toEndWith('.zip');
    $old = BackupService::DIR . '/kitaq-backup-20000101-000000.zip';
    Storage::disk('local')->put($old, 'x');
    touch(Storage::disk('local')->path($old), now()->subDays(30)->getTimestamp());
    app(BackupService::class)->prune();
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($path);
});

it('pembersihan log lama sesuai retensi', function () {
    $a = startedAttempt($this);
    ShieldEvent::query()->create(['exam_attempt_id' => $a->id, 'type' => 'tab_hidden', 'action' => 'warn', 'received_at' => now()->subDays(400)]);
    ShieldEvent::query()->create(['exam_attempt_id' => $a->id, 'type' => 'tab_hidden', 'action' => 'warn', 'received_at' => now()->subDays(10)]);
    (new ActivityLog())->forceFill(['action' => 'lama', 'created_at' => now()->subDays(400)])->save();
    $this->artisan('kitaq:prune')->assertSuccessful();
    expect(ShieldEvent::query()->where('received_at', '<', now()->subDays(365))->count())->toBe(0)
        ->and(ShieldEvent::query()->where('type', 'tab_hidden')->where('received_at', '>', now()->subDays(30))->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', 'lama')->exists())->toBeFalse();
});

it('kitaq:seed-load membuat siswa dummy yang cocok dengan skrip k6 dan bisa dihapus', function () {
    $this->artisan('kitaq:seed-load', ['count' => 5])->assertSuccessful();
    expect(Student::query()->where('nisn', '9000000001')->exists())->toBeTrue()->and(Student::query()->count())->toBe(5);
    $s = ExamSchedule::query()->latest('id')->first();
    expect($s->isOpenNow())->toBeTrue();
    config(['kitaq.load_test' => true]);
    $this->withHeaders(['User-Agent' => 'k6/0.50 (https://k6.io/)'])->post('/login', ['nisn' => '9000000003', 'password' => '01012008'])->assertRedirect('/siswa');
    $this->post("/ujian/{$s->id}/mulai", ['token' => 'UJIBEB'])->assertRedirect("/ujian/{$s->id}/kerjakan");
    $this->artisan('kitaq:seed-load', ['--hapus' => true])->assertSuccessful();
    expect(Student::query()->count())->toBe(0);
});

it('rute install darurat: nonaktif tanpa token, menolak token salah, berjalan sekali lalu terkunci', function () {
    @unlink(storage_path(\App\Http\Controllers\InstallController::LOCK));
    $this->get('/install?token=apa')->assertNotFound();
    config(['kitaq.install_token' => str_repeat('a', 32)]);
    $this->get('/install?token=salah')->assertNotFound();
    $this->get('/install?token=' . str_repeat('a', 32))->assertOk()->assertSee('SELESAI');
    expect(\App\Models\User::query()->where('username', 'superadmin')->exists())->toBeTrue();
    $this->get('/install?token=' . str_repeat('a', 32))->assertStatus(410);
    @unlink(storage_path(\App\Http\Controllers\InstallController::LOCK));
});

it('semua halaman admin tampil untuk Super Admin (smoke test)', function () {
    $s = examSchedule();
    startedAttempt($this, $s);
    $admin = staff('super_admin');
    $bank = \App\Models\QuestionBank::query()->first();
    $urls = [
        route('admin.dashboard'), route('admin.profile'), route('admin.about'), route('admin.students'), route('admin.students.import'),
        route('admin.classrooms'), route('admin.subjects'), route('admin.rooms'), route('admin.academic-years'), route('admin.users'),
        route('admin.banks'), route('admin.banks.show', $bank), route('admin.banks.question', $bank), route('admin.exams'), route('admin.exams.create'),
        route('admin.schedules'), route('admin.sessions'), route('admin.monitoring.show', $s), route('admin.projector', $s),
        route('admin.recap'), route('admin.item-analysis'), route('admin.grading'), route('admin.violations'), route('admin.integrity'),
        route('admin.minutes'), route('admin.minutes.show', $s), route('admin.exports'), route('admin.settings'), route('admin.health'),
        route('admin.backups'), route('admin.activity'),
    ];
    foreach ($urls as $u) {
        $res = $this->actingAs($admin)->get($u);
        expect($res->status())->toBe(200, "Gagal: {$u}");
    }
    // Katalog UI hanya untuk APP_ENV=local.
    $this->actingAs($admin)->get(route('admin.ui'))->assertNotFound();
});

it('pencarian Ctrl+K menemukan siswa & ujian', function () {
    examSchedule();
    student();
    $this->actingAs(staff())->getJson(route('admin.search', ['q' => 'Aisyah']))->assertOk()->assertJsonFragment(['group' => 'Siswa']);
    $this->actingAs(staff())->getJson(route('admin.search', ['q' => 'PTS']))->assertOk()->assertJsonFragment(['group' => 'Ujian']);
});

it('dokumen rilis tersedia dan tidak ada CDN di view/JS', function () {
    expect(file_exists(base_path('docs/RILIS.md')))->toBeTrue();
    $files = array_merge(glob(resource_path('views/**/*.blade.php')) ?: [], glob(resource_path('views/*/*/*.blade.php')) ?: [], glob(resource_path('js/**/*.js')) ?: []);
    foreach ($files as $f) {
        expect(file_get_contents($f))->not->toMatch('#https?://(cdn|unpkg|fonts\.googleapis|cdnjs|jsdelivr)#i', $f);
    }
});
