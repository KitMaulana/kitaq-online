<?php

use App\Jobs\AutoSubmitExpiredAttempts;
use App\Jobs\DetectHeartbeatGaps;
use App\Models\ExamAttempt;
use App\Models\MonitorAlert;
use App\Models\ShieldEvent;
use App\Services\Shield\ShieldService;

beforeEach(function () {
    $this->schedule = examSchedule();
    $this->attempt = startedAttempt($this, $this->schedule);
    $this->url = "/api/attempts/{$this->attempt->id}/events";
    $this->ev = function (array $events, string $instance = 'inst-A') {
        return $this->postJson($this->url, ['instance_id' => $instance, 'client_now' => now()->getTimestampMs(), 'events' => $events]);
    };
});

it('mengabaikan seq ganda (idempoten) dan tetap meng-ack', function () {
    $at = now()->getTimestampMs();
    ($this->ev)([['seq' => 2, 'type' => 'tab_hidden', 'at' => $at]])->assertOk()->assertJson(['acked' => [2], 'state' => ['status' => 'warned', 'warnings' => 1]]);
    ($this->ev)([['seq' => 2, 'type' => 'tab_hidden', 'at' => $at]])->assertOk()->assertJson(['acked' => [2], 'state' => ['warnings' => 1]]);
    expect(ShieldEvent::query()->where('exam_attempt_id', $this->attempt->id)->where('seq', 2)->count())->toBe(1);
});

it('peringatan 1 → 2 → pelanggaran ke-3 mengunci (profil standar) dan memberi alarm pengawas', function () {
    $this->attempt->forceFill(['started_at' => now()->subMinutes(5)])->save();
    $t = now()->getTimestampMs();
    ($this->ev)([['seq' => 2, 'type' => 'tab_hidden', 'at' => $t - 20000]])->assertJson(['state' => ['status' => 'warned', 'warnings' => 1, 'new_warning' => true]]);
    ($this->ev)([['seq' => 3, 'type' => 'window_blur', 'at' => $t - 10000]])->assertJson(['state' => ['status' => 'warned', 'warnings' => 2]]);
    ($this->ev)([['seq' => 4, 'type' => 'fullscreen_exit', 'at' => $t]])->assertJson(['state' => ['status' => 'locked']]);
    $a = $this->attempt->fresh();
    expect($a->status)->toBe('locked')->and($a->warning_count)->toBe(3)
        ->and(MonitorAlert::query()->where('kind', 'locked')->where('exam_attempt_id', $a->id)->exists())->toBeTrue();
    // Terkunci → soal & jawaban 423
    $this->getJson("/api/attempts/{$a->id}/questions/1")->assertStatus(423);
    $this->postJson("/api/attempts/{$a->id}/answers", ['answers' => [['question_id' => $a->question_order[0], 'answer' => 'x', 'rev' => 9]]])->assertStatus(423);
});

it('menggabungkan event peringatan dalam 3 detik menjadi satu kejadian', function () {
    $t = now()->getTimestampMs();
    ($this->ev)([
        ['seq' => 2, 'type' => 'window_blur', 'at' => $t - 1500],
        ['seq' => 3, 'type' => 'tab_hidden', 'at' => $t - 1000],
        ['seq' => 4, 'type' => 'fullscreen_exit', 'at' => $t - 500],
    ])->assertJson(['acked' => [2, 3, 4], 'state' => ['warnings' => 1]]);
    expect(ShieldEvent::query()->where('merged', true)->count())->toBe(2);
});

it('terjemahan terdeteksi langsung mengunci pada profil standar', function () {
    ($this->ev)([['seq' => 2, 'type' => 'translate_detected', 'at' => now()->getTimestampMs()]])->assertJson(['state' => ['status' => 'locked', 'reason_text' => 'Fitur terjemahan browser terdeteksi.']]);
});

it('menjepit waktu klien ke rentang [started_at, now]', function () {
    ($this->ev)([['seq' => 2, 'type' => 'context_menu', 'at' => now()->getTimestampMs() - 86_400_000 * 3]]);
    ($this->ev)([['seq' => 3, 'type' => 'context_menu', 'at' => now()->getTimestampMs() + 999_999]]);
    $e = ShieldEvent::query()->where('seq', 2)->first();
    expect($e->client_at->gte($this->attempt->started_at->copy()->startOfSecond()))->toBeTrue();
    expect(ShieldEvent::query()->where('seq', 3)->first()->client_at->lte(now()->addSecond()))->toBeTrue();
});

it('second_instance: perangkat/tab kedua saat yang pertama hidup → terkunci (standar) dan instance kedua diblokir', function () {
    $this->postJson("/api/attempts/{$this->attempt->id}/heartbeat", ['instance_id' => 'inst-A'])->assertOk();
    $res = ($this->ev)([['seq' => 1, 'type' => 'exam_started', 'at' => now()->getTimestampMs()]], 'inst-B');
    $res->assertOk()->assertJson(['state' => ['status' => 'blocked_instance']]);
    $a = $this->attempt->fresh();
    expect($a->status)->toBe('locked')->and($a->lock_reason)->toBe('second_instance')->and($a->active_instance_id)->toBe('inst-A')
        ->and(MonitorAlert::query()->where('kind', 'second_instance')->exists())->toBeTrue();
});

it('login dari perangkat lain saat ujian ditolak dan pengawas diberi tahu', function () {
    $this->newDevice();
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008'])->assertSessionHasErrors('nisn');
    expect(MonitorAlert::query()->where('kind', 'second_instance')->exists())->toBeTrue()
        ->and(ShieldEvent::query()->where('type', 'second_instance')->where('action', 'log')->exists())->toBeTrue();
});

it('reset login: instance aktif dikosongkan; perangkat baru wajib token lalu menjadi aktif', function () {
    app(ShieldService::class)->resetLogin($this->attempt->fresh(), staff('pengawas'));
    expect($this->attempt->fresh()->active_instance_id)->toBeNull();
    $this->newDevice();
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008'])->assertRedirect('/siswa');
    $this->get("/ujian/{$this->schedule->id}")->assertOk()->assertSee('Token ujian');
    $this->postJson($this->url, ['instance_id' => 'inst-HP-baru', 'events' => []])->assertJson(['state' => ['status' => 'blocked_instance']]);
    $this->post("/ujian/{$this->schedule->id}/mulai", ['token' => $this->schedule->currentToken()])->assertRedirect();
    $this->postJson("/api/attempts/{$this->attempt->id}/heartbeat", ['instance_id' => 'inst-HP-baru'])->assertOk()->assertJson(['state' => ['status' => 'active']]);
    expect($this->attempt->fresh()->active_instance_id)->toBe('inst-HP-baru');
});

it('buka kunci oleh pengawas wajib alasan; jawaban utuh', function () {
    ($this->ev)([['seq' => 2, 'type' => 'multi_tab', 'at' => now()->getTimestampMs()]]);
    $a = $this->attempt->fresh();
    expect($a->status)->toBe('locked');
    $pengawas = staff('pengawas');
    $this->schedule->supervisors()->attach($pengawas->id);
    $this->actingAs($pengawas, 'web')->postJson("/api/monitor/attempts/{$a->id}/unlock", ['reason' => ''])->assertStatus(422);
    $this->actingAs($pengawas, 'web')->postJson("/api/monitor/attempts/{$a->id}/unlock", ['reason' => 'Sudah diperiksa, tab ditutup'])->assertOk();
    $a->refresh();
    expect($a->status)->toBe('in_progress')->and($a->warning_count)->toBe(0)->and($a->unlockLogs()->first()->reason)->toBe('Sudah diperiksa, tab ditutup');
});

it('heartbeat: nonce bergulir; nonce salah dicatat heartbeat_invalid; perintah pengawas terkirim', function () {
    $url = "/api/attempts/{$this->attempt->id}/heartbeat";
    $n1 = $this->postJson($url, ['instance_id' => 'inst-A', 'nonce' => null])->assertOk()->json('nonce');
    $n2 = $this->postJson($url, ['instance_id' => 'inst-A', 'nonce' => $n1])->json('nonce');
    expect($n1)->not->toBe($n2)->and(ShieldEvent::query()->where('type', 'heartbeat_invalid')->count())->toBe(0);
    $this->postJson($url, ['instance_id' => 'inst-A', 'nonce' => 'palsu'])->assertOk();
    expect(ShieldEvent::query()->where('type', 'heartbeat_invalid')->where('action', 'log')->count())->toBe(1);

    \App\Models\AttemptCommand::query()->create(['exam_attempt_id' => $this->attempt->id, 'type' => 'message', 'payload' => ['text' => 'Halo']]);
    $this->postJson($url, ['instance_id' => 'inst-A', 'nonce' => null])->assertJson(['commands' => [['type' => 'message', 'text' => 'Halo']]]);
    $this->postJson($url, ['instance_id' => 'inst-A', 'nonce' => null])->assertJson(['commands' => []]);
});

it('auto-submit dari server saat waktu habis walau HP mati', function () {
    $this->attempt->forceFill(['deadline_at' => now()->subMinute()])->save();
    app()->call([new AutoSubmitExpiredAttempts(), 'handle']);
    $a = $this->attempt->fresh();
    expect($a->status)->toBe('submitted')->and($a->score)->not->toBeNull();
});

it('heartbeat hilang > 60 dtk → heartbeat_gap (log), terputus, tidak terkunci', function () {
    $this->attempt->forceFill(['last_heartbeat_at' => now()->subMinutes(3)])->save();
    app()->call([new DetectHeartbeatGaps(), 'handle']);
    $a = $this->attempt->fresh();
    expect($a->status)->toBe('in_progress')->and($a->monitorStatus())->toBe('disconnected')
        ->and(ShieldEvent::query()->where('type', 'heartbeat_gap')->where('action', 'log')->exists())->toBeTrue();
});

it('offline terlalu lama mengunci bila ujian diatur "kunci setelah N detik"', function () {
    $this->schedule->exam->update(['settings' => ['offline_mode' => 'lock', 'offline_lock_after_sec' => 120]]);
    $this->attempt->forceFill(['last_heartbeat_at' => now()->subMinutes(5)])->save();
    app()->call([new DetectHeartbeatGaps(), 'handle']);
    expect($this->attempt->fresh()->status)->toBe('locked');
});

it('batasan IP lokasi: di luar daftar ditolak, di dalam diterima', function () {
    $this->schedule->forceFill(['ip_allowlist' => ['10.10.10.0/24']])->save();
    $this->getJson("/api/attempts/{$this->attempt->id}/questions/1")->assertStatus(403)->assertJson(['code' => 'location_blocked']);
    $this->get("/ujian/{$this->schedule->id}")->assertStatus(403)->assertSee('WiFi sekolah');
    $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.7'])->getJson("/api/attempts/{$this->attempt->id}/questions/1")->assertOk();
});

it('gerbang browser saat mulai ujian memakai daftar browser per ujian', function () {
    $this->schedule->exam->update(['settings' => ['browsers' => ['safari']]]);
    $this->get("/ujian/{$this->schedule->id}")->assertStatus(403)->assertSee('Buka di browser');
});

it('header CSP ketat di halaman ujian dan API', function () {
    $res = $this->postJson("/api/attempts/{$this->attempt->id}/heartbeat", ['instance_id' => 'inst-A']);
    expect($res->headers->get('Content-Security-Policy'))->not->toContain('unsafe-eval')->and($res->headers->get('X-Frame-Options'))->toBe('DENY');
});
