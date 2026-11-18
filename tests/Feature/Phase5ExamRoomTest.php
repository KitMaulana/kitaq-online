<?php

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;

beforeEach(function () {
    $this->schedule = examSchedule();
    $this->attempt = startedAttempt($this, $this->schedule);
});

it('ruang ujian tanpa input/textarea/contenteditable/Livewire/alert/confirm dan ber-CSP ketat', function () {
    $res = $this->get("/ujian/{$this->schedule->id}/kerjakan")->assertOk()->assertSee('Saya Mengerti');
    $html = $res->getContent();
    expect($html)->not->toMatch('/<input\b/i')->not->toMatch('/<textarea\b/i')->not->toContain('contenteditable')
        ->not->toContain('wire:')->not->toContain('livewire')->not->toContain('alert(')->not->toContain('confirm(')
        ->toContain('translate="no"')->toContain('notranslate')->toContain('"attemptId":' . $this->attempt->id);
    $csp = $res->headers->get('Content-Security-Policy');
    expect($csp)->toContain("script-src 'self' 'nonce-")->not->toContain('unsafe-eval')
        ->and($res->headers->get('Cache-Control'))->toContain('no-store');
});

it('respons API soal & peta tidak pernah memuat kunci jawaban atau pembahasan', function () {
    $keys = Question::query()->get()->map(fn ($q) => $q->answer_key);
    Question::query()->update(['explanation' => '<p>PEMBAHASAN-RAHASIA</p>']);
    for ($no = 1; $no <= $this->attempt->questionCount(); $no++) {
        $res = $this->getJson("/api/attempts/{$this->attempt->id}/questions/{$no}")->assertOk();
        $json = $res->getContent();
        expect($json)->not->toContain('answer_key')->not->toContain('is_correct')->not->toContain('PEMBAHASAN-RAHASIA')->not->toContain('Kota Serang');
        expect($res->json())->toHaveKeys(['id', 'no', 'type', 'text', 'options', 'answer', 'flagged'])
            ->and($res->headers->get('Cache-Control'))->toContain('no-store');
    }
    $this->getJson("/api/attempts/{$this->attempt->id}/map")->assertOk()->assertJsonCount($this->attempt->questionCount(), 'items');
});

it('opsi dikirim dengan kunci tampilan dan dipetakan kembali ke kunci asli di server', function () {
    $pgId = Question::query()->where('type', 'pg')->value('id');
    $no = array_search($pgId, $this->attempt->question_order, true) + 1;
    $q = $this->getJson("/api/attempts/{$this->attempt->id}/questions/{$no}")->json();
    $map = $this->attempt->option_order[(string) $pgId];
    $displayForC = array_search('C', $map, true);
    expect(collect($q['options'])->pluck('key')->sort()->values()->all())->toBe(['A', 'B', 'C', 'D']);
    $this->postJson("/api/attempts/{$this->attempt->id}/answers", ['answers' => [['question_id' => $pgId, 'answer' => $displayForC, 'flagged' => false, 'rev' => 1]]])
        ->assertOk()->assertJson(['saved' => [['question_id' => $pgId, 'rev' => 1]]]);
    expect(AttemptAnswer::query()->where('question_id', $pgId)->value('answer'))->toBe('C');
});

it('simpan jawaban idempoten dan menolak revisi lama', function () {
    $isian = Question::query()->where('type', 'isian')->value('id');
    $url = "/api/attempts/{$this->attempt->id}/answers";
    $this->postJson($url, ['answers' => [['question_id' => $isian, 'answer' => 'Serang', 'rev' => 3]]])->assertOk();
    $this->postJson($url, ['answers' => [['question_id' => $isian, 'answer' => 'Serang', 'rev' => 3]]])->assertOk()->assertJson(['saved' => [['rev' => 3]]]);
    $this->postJson($url, ['answers' => [['question_id' => $isian, 'answer' => 'Jakarta', 'rev' => 2]]])->assertOk()->assertJson(['saved' => [['rev' => 3]]]);
    $row = AttemptAnswer::query()->where('question_id', $isian)->first();
    expect($row->answer)->toBe('Serang')->and($row->rev)->toBe(3)->and(AttemptAnswer::query()->count())->toBe(1);
});

it('menolak jawaban untuk soal di luar urutan attempt', function () {
    $other = Question::query()->create(['question_bank_id' => bankWithQuestions()->id, 'type' => 'isian', 'content' => 'x', 'answer_key' => ['x']]);
    $this->postJson("/api/attempts/{$this->attempt->id}/answers", ['answers' => [['question_id' => $other->id, 'answer' => 'x', 'rev' => 1]]])
        ->assertOk()->assertJson(['saved' => []]);
});

it('mengumpulkan & menilai semua tipe (PGK tepat, BS/jodoh parsial, isian variasi, uraian menunggu guru)', function () {
    $a = $this->attempt;
    $map = $a->option_order;
    $disp = fn ($qid, $orig) => (string) array_search($orig, $map[(string) $qid], true);
    $q = Question::query()->get()->keyBy('type');
    $jm = $map[(string) $q['jodoh']->id];
    $answers = [
        ['question_id' => $q['pg']->id, 'answer' => $disp($q['pg']->id, 'C'), 'rev' => 1],
        ['question_id' => $q['pgk']->id, 'answer' => [$disp($q['pgk']->id, 'A'), $disp($q['pgk']->id, 'C')], 'rev' => 1],
        ['question_id' => $q['bs']->id, 'answer' => [$disp($q['bs']->id, '1') => true, $disp($q['bs']->id, '2') => true], 'rev' => 1],
        ['question_id' => $q['jodoh']->id, 'answer' => [array_search('1', $jm['L'], true) => array_search('b', $jm['R'], true), array_search('2', $jm['L'], true) => array_search('a', $jm['R'], true)], 'rev' => 1],
        ['question_id' => $q['isian']->id, 'answer' => '  kota   SERANG ', 'rev' => 1],
        ['question_id' => $q['uraian']->id, 'answer' => 'Jawaban uraian', 'rev' => 1],
    ];
    $this->postJson("/api/attempts/{$a->id}/answers", ['answers' => $answers])->assertOk();
    $this->postJson("/api/attempts/{$a->id}/submit")->assertOk()->assertJson(['redirect' => route('student.exam.result', $this->schedule->id)]);
    $a->refresh();
    // bobot: 5 soal ×1 + uraian 2 = 7; skor: pg 1 + pgk 1 + bs 0.5 + jodoh 1 + isian 1 = 4.5 → 64.29
    expect($a->status)->toBe('submitted')->and($a->needs_grading)->toBeTrue()->and($a->score)->toBe(64.29)
        ->and($a->score_detail['pending_essay'])->toBeTrue();
    $this->get("/ujian/{$this->schedule->id}/hasil")->assertOk()->assertSee('dikoreksi guru');
});

it('menolak kumpul sebelum waktu minimal', function () {
    $this->schedule->exam->update(['settings' => ['min_minutes_before_submit' => 30]]);
    $this->postJson("/api/attempts/{$this->attempt->id}/submit")->assertStatus(422);
    expect($this->attempt->fresh()->isDone())->toBeFalse();
});

it('siswa lain tidak bisa mengakses attempt orang lain', function () {
    $this->newDevice();
    student(['nisn' => '0099999999', 'name' => 'Lain']);
    $this->post('/login', ['nisn' => '0099999999', 'password' => '20102008']);
    $this->getJson("/api/attempts/{$this->attempt->id}/questions/1")->assertNotFound();
});

it('melanjutkan dari sesi yang sama tidak perlu token lagi', function () {
    $this->get("/ujian/{$this->schedule->id}")->assertRedirect("/ujian/{$this->schedule->id}/kerjakan");
});

it('PWA: manifest, service worker, offline, halaman /pasang dan /cek-perangkat tersedia', function () {
    expect(file_exists(public_path('manifest.webmanifest')))->toBeTrue()->and(file_exists(public_path('sw.js')))->toBeTrue()->and(file_exists(public_path('offline.html')))->toBeTrue();
    $this->get('/pasang')->assertOk()->assertSee('Tambah ke Layar Utama')->assertSee('Huawei');
    $this->get('/cek-perangkat')->assertOk();
    $sw = file_get_contents(public_path('sw.js'));
    expect($sw)->toContain("startsWith('/api/')");
});

it('gambar soal disajikan lewat URL bertanda tangan untuk pemilik attempt', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('media/questions/x.webp', 'img');
    $pg = Question::query()->where('type', 'pg')->first();
    $pg->update(['image' => 'questions/x.webp']);
    $no = array_search($pg->id, $this->attempt->question_order, true) + 1;
    $url = $this->getJson("/api/attempts/{$this->attempt->id}/questions/{$no}")->json('image');
    expect($url)->toContain('signature=');
    $this->get($url)->assertOk();
    $this->get(preg_replace('/signature=[^&]+/', 'signature=palsu', $url))->assertForbidden();
});
