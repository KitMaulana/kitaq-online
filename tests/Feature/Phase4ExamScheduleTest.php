<?php

use App\Livewire\Admin\ExamForm;
use App\Livewire\Admin\Schedules;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Services\ExamTokenService;
use App\Services\ScheduleService;
use App\Support\ExamSettings;
use Livewire\Livewire;

it('menyimpan pengaturan ujian JSON lengkap (profil, pengecualian, browser, kanvas, IP, offline)', function () {
    $bank = bankWithQuestions();
    Livewire::actingAs(staff())->test(ExamForm::class)
        ->set('form.name', 'PAS Matematika')->set('form.subject_id', (string) $bank->subject_id)->set('form.question_bank_id', (string) $bank->id)
        ->set('form.question_count', 5)->set('form.duration_minutes', 90)
        ->set('settings.preset', 'ketat')->set('settings.overrides.window_blur', 'log')->set('settings.max_warnings', 3)
        ->set('settings.browsers', ['chrome', 'safari'])->set('settings.canvas_mode', true)->set('settings.ip_restrict', true)
        ->set('settings.offline_mode', 'lock')->set('settings.offline_lock_after_sec', 120)->set('settings.timer_when_locked', 'pause')
        ->call('save')->assertHasNoErrors();
    $exam = Exam::query()->where('name', 'PAS Matematika')->firstOrFail();
    $s = $exam->resolvedSettings();
    expect($s['preset'])->toBe('ketat')->and($s['overrides'])->toBe(['window_blur' => 'log'])->and($s['max_warnings'])->toBe(3)
        ->and($s['browsers'])->toBe(['chrome', 'safari'])->and($s['canvas_mode'])->toBeTrue()->and($s['offline_mode'])->toBe('lock');
    expect($exam->policy()->actionFor('window_blur'))->toBe('log')->and($exam->policy()->actionFor('split_screen'))->toBe('lock')
        ->and($exam->policy()->actionFor('offline_lock'))->toBe('lock');
});

it('default pengaturan mengikuti config (standar, 2 peringatan, wajib token)', function () {
    $d = ExamSettings::resolve(null);
    expect($d['preset'])->toBe('standar')->and($d['max_warnings'])->toBe(2)->and($d['token_required'])->toBeTrue()
        ->and($d['browsers'])->toBe(['chrome', 'safari', 'samsung', 'huawei']);
});

it('membuat jadwal per kelas (satu jadwal per kelas) dengan token_key acak', function () {
    $bank = bankWithQuestions();
    $exam = Exam::query()->create(['name' => 'UH', 'subject_id' => $bank->subject_id, 'question_bank_id' => $bank->id, 'duration_minutes' => 30]);
    $a = classroom('X-1');
    $b = classroom('X-2');
    Livewire::actingAs(staff('operator'))->test(Schedules::class)
        ->call('openCreate')
        ->set('form.exam_id', (string) $exam->id)->set('form.classrooms', [(string) $a->id, (string) $b->id])
        ->set('form.date', today()->addDay()->format('Y-m-d'))->set('form.opens', '08:00')->set('form.closes', '09:30')
        ->set('form.ip_allowlist', "103.10.20.0/24\n36.1.2.3")
        ->call('save')->assertHasNoErrors();
    $s = ExamSchedule::query()->where('exam_id', $exam->id)->get();
    expect($s)->toHaveCount(2)->and($s[0]->token_key)->not->toBe($s[1]->token_key)->and(strlen($s[0]->token_key))->toBe(40)
        ->and($s[0]->ip_allowlist)->toBe(['103.10.20.0/24', '36.1.2.3'])->and($s[0]->status)->toBe('scheduled');
});

it('scheduler membuka dan menutup jadwal otomatis serta mengumpulkan peserta aktif', function () {
    $s = examSchedule([], ['status' => 'scheduled', 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHour()]);
    app(ScheduleService::class)->tick();
    expect($s->fresh()->status)->toBe('open');

    $attempt = startedAttempt($this, $s->fresh());
    $s->fresh()->forceFill(['closes_at' => now()->subSecond()])->save();
    $this->artisan('kitaq:tick')->assertSuccessful();
    expect($s->fresh()->status)->toBe('closed')->and($attempt->fresh()->status)->toBe('submitted');
});

it('token: salah ditolak, benar diterima, token kedaluwarsa (2 jendela lalu) ditolak', function () {
    $s = examSchedule();
    student();
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008']);
    $this->post("/ujian/{$s->id}/mulai", ['token' => 'ZZZZZZ'])->assertSessionHasErrors('token');

    $svc = app(ExamTokenService::class);
    $expired = $svc->forWindow($s->token_key, $svc->window() - 2);
    $this->post("/ujian/{$s->id}/mulai", ['token' => $expired])->assertSessionHasErrors('token');

    $this->post("/ujian/{$s->id}/mulai", ['token' => strtolower($s->currentToken())])->assertRedirect("/ujian/{$s->id}/kerjakan");
});

it('tanpa token yang benar ujian tidak bisa dimulai walau NISN & tanggal lahir benar', function () {
    $s = examSchedule();
    student();
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008']);
    $this->post("/ujian/{$s->id}/mulai", ['token' => ''])->assertSessionHasErrors('token');
    $this->get("/ujian/{$s->id}/kerjakan")->assertRedirect("/ujian/{$s->id}");
    expect(\App\Models\ExamAttempt::query()->whereNotNull('started_at')->count())->toBe(0);
});

it('menampilkan halaman Sesi & Token, layar proyektor, dan token JSON untuk pengawas yang ditugaskan', function () {
    $s = examSchedule();
    $pengawas = staff('pengawas');
    $this->actingAs($pengawas)->get(route('admin.token', $s))->assertForbidden();
    $s->supervisors()->attach($pengawas->id);
    $this->actingAs($pengawas)->get(route('admin.token', $s))->assertOk()->assertJson(['token' => $s->currentToken()]);
    $this->actingAs($pengawas)->get(route('admin.projector', $s))->assertOk()->assertSee($s->currentToken())->assertSee(site_host());
    $this->actingAs($pengawas)->get(route('admin.sessions', ['schedule' => $s->id]))->assertOk()->assertSee('Token aktif');
});

it('jeda semua → lanjutkan mengembalikan waktu; perpanjang menambah batas', function () {
    $s = examSchedule();
    $attempt = startedAttempt($this, $s);
    $deadline = $attempt->deadline_at->copy();
    $admin = staff();
    $svc = app(ScheduleService::class);
    $svc->pause($s->fresh(), $admin);
    $this->travel(5)->minutes();
    $svc->resume($s->fresh(), $admin);
    expect($attempt->fresh()->deadline_at->diffInSeconds($deadline, true))->toBeGreaterThanOrEqual(299);
    $svc->extend($s->fresh(), 15, $admin);
    expect($attempt->fresh()->deadline_at->gt($deadline->copy()->addMinutes(19)))->toBeTrue();
});

it('halaman ujian, form ujian, dan jadwal tampil', function () {
    examSchedule();
    $admin = staff();
    $this->actingAs($admin)->get(route('admin.exams'))->assertOk()->assertSee('PTS Uji');
    $this->actingAs($admin)->get(route('admin.exams.edit', Exam::first()))->assertOk()->assertSee('Profil keketatan');
    $this->actingAs($admin)->get(route('admin.schedules'))->assertOk()->assertSee('PTS Uji');
});
