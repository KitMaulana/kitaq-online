<?php

use App\Models\AttemptCommand;
use App\Models\ExamAttempt;

beforeEach(function () {
    $this->schedule = examSchedule();
    $this->attempt = startedAttempt($this, $this->schedule);
    $this->pengawas = staff('pengawas');
    $this->schedule->supervisors()->attach($this->pengawas->id);
});

it('API monitoring mengembalikan kartu, ringkasan, feed delta, dan token', function () {
    $this->postJson("/api/attempts/{$this->attempt->id}/heartbeat", ['instance_id' => 'inst-A'])->assertOk();
    $this->postJson("/api/attempts/{$this->attempt->id}/events", ['instance_id' => 'inst-A', 'client_now' => now()->getTimestampMs(), 'events' => [['seq' => 5, 'type' => 'split_screen', 'at' => now()->getTimestampMs()]]]);
    student(['nisn' => '0081234599', 'name' => 'Belum Masuk']);

    $res = $this->actingAs($this->pengawas)->getJson("/api/monitor/{$this->schedule->id}?since=0&alert_since=-1")->assertOk();
    $res->assertJsonStructure(['schedule' => ['status'], 'summary' => ['all', 'in_progress', 'warned', 'locked', 'not_started'], 'cards' => [['name', 'nisn', 'status', 'answered', 'total', 'warnings', 'device', 'ip']], 'events', 'alerts', 'token' => ['token', 'left']]);
    expect($res->json('summary.all'))->toBe(2)->and($res->json('summary.warned'))->toBe(1)->and($res->json('summary.not_started'))->toBe(1)
        ->and($res->json('token.token'))->toBe($this->schedule->currentToken())
        ->and(collect($res->json('events'))->pluck('type'))->toContain('split_screen');
    $card = collect($res->json('cards'))->firstWhere('nisn', '0081234567');
    expect($card['device'])->toContain('Android')->toContain('Chrome');

    $last = collect($res->json('events'))->last()['id'];
    $this->actingAs($this->pengawas)->getJson("/api/monitor/{$this->schedule->id}?since={$last}&alert_since=0")->assertOk()->assertJsonCount(0, 'events');
});

it('pengawas hanya bisa memantau sesi yang ditugaskan', function () {
    $lain = staff('pengawas');
    $this->actingAs($lain)->getJson("/api/monitor/{$this->schedule->id}")->assertForbidden();
    $this->actingAs($lain)->get(route('admin.monitoring.show', $this->schedule))->assertForbidden();
    $this->actingAs($this->pengawas)->get(route('admin.monitoring.show', $this->schedule))->assertOk()->assertSee('Feed pelanggaran');
});

it('aksi tambah waktu, kirim pesan, reset login, dan kumpulkan paksa', function () {
    $deadline = $this->attempt->deadline_at->copy();
    $base = "/api/monitor/attempts/{$this->attempt->id}";
    $this->actingAs($this->pengawas)->postJson("{$base}/extend", ['minutes' => 10])->assertOk();
    expect($this->attempt->fresh()->deadline_at->diffInMinutes($deadline, true))->toBeGreaterThanOrEqual(9.9);
    $this->actingAs($this->pengawas)->postJson("{$base}/message", ['message' => 'Fokus ya'])->assertOk();
    expect(AttemptCommand::query()->where('type', 'message')->first()->payload['text'])->toBe('Fokus ya');
    $this->actingAs($this->pengawas)->postJson("{$base}/reset-login")->assertOk();
    expect($this->attempt->fresh()->active_instance_id)->toBeNull();
    $this->actingAs($this->pengawas)->postJson("{$base}/force-submit")->assertOk();
    expect($this->attempt->fresh()->status)->toBe('force_submitted');
    expect(\App\Models\ActivityLog::query()->whereIn('action', ['attempt_extended', 'reset_login', 'force_submit'])->count())->toBe(3);
});

it('aksi massal jeda/lanjutkan/tutup lewat monitoring', function () {
    $url = "/api/monitor/{$this->schedule->id}/jadwal";
    $this->actingAs($this->pengawas)->postJson("{$url}/pause")->assertOk();
    expect($this->schedule->fresh()->status)->toBe('paused');
    $this->postJson("/api/attempts/{$this->attempt->id}/heartbeat", ['instance_id' => 'inst-A'])->assertJson(['state' => ['status' => 'paused']]);
    $this->actingAs($this->pengawas)->postJson("{$url}/resume")->assertOk();
    $this->actingAs($this->pengawas)->postJson("{$url}/close")->assertOk();
    expect($this->attempt->fresh()->isDone())->toBeTrue()->and($this->schedule->fresh()->status)->toBe('closed');
});

it('linimasa perangkat & log satu siswa', function () {
    $this->actingAs($this->pengawas)->getJson("/api/monitor/attempts/{$this->attempt->id}/detail")->assertOk()
        ->assertJsonStructure(['events', 'instances' => [['device', 'ip', 'active']], 'unlocks']);
});
