<?php

use App\Jobs\ExportReport;
use App\Livewire\Admin\Grading;
use App\Models\AttemptAnswer;
use App\Models\AttemptInstance;
use App\Models\ExamAttempt;
use App\Models\IntegrityFlag;
use App\Models\Question;
use App\Models\ReportExport;
use App\Models\Student;
use App\Services\AttemptService;
use App\Services\IntegrityAnalyzer;
use App\Services\Reports\RecapService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Siapkan N siswa yang sudah mengumpulkan dengan jawaban tertentu (kunci asli). */
function finishedAttempts(\App\Models\ExamSchedule $s, array $answersPerStudent): array
{
    $svc = app(AttemptService::class);
    $out = [];
    foreach ($answersPerStudent as $i => $answers) {
        $st = Student::query()->create(['classroom_id' => $s->classroom_id, 'nisn' => sprintf('00900000%02d', $i), 'name' => "Siswa {$i}", 'gender' => 'L', 'birth_date' => '2008-01-01', 'password' => '01012008']);
        $a = $svc->start($s, $st);
        foreach ($answers as $qid => $ans) {
            AttemptAnswer::query()->create(['exam_attempt_id' => $a->id, 'question_id' => $qid, 'answer' => $ans, 'rev' => 1, 'first_answered_sec' => 30 + $i]);
        }
        $svc->submit($a);
        $out[] = $a->fresh();
    }

    return $out;
}

beforeEach(function () {
    $this->schedule = examSchedule();
    $this->q = Question::query()->get()->keyBy('type');
});

it('rekap nilai & ringkasan (rata-rata, tertinggi, terendah, % tuntas)', function () {
    finishedAttempts($this->schedule, [
        [$this->q['pg']->id => 'C', $this->q['isian']->id => 'Serang'],
        [$this->q['pg']->id => 'A'],
    ]);
    $svc = app(RecapService::class);
    $rows = $svc->recap($this->schedule);
    $sum = $svc->summary($rows);
    expect($rows)->toHaveCount(2)->and($rows->first()['score'])->toBeGreaterThan($rows->last()['score'])
        ->and($sum['count'])->toBe(2)->and($sum['max'])->toBe($rows->first()['score']);
    $this->actingAs(staff())->get(route('admin.recap', ['jadwal' => $this->schedule->id]))->assertOk()->assertSee('Siswa 0');
});

it('analisis butir menghitung kesukaran, daya beda, dan pengecoh', function () {
    finishedAttempts($this->schedule, [
        [$this->q['pg']->id => 'C', $this->q['isian']->id => 'Serang'],
        [$this->q['pg']->id => 'C', $this->q['isian']->id => 'x'],
        [$this->q['pg']->id => 'A'],
        [$this->q['pg']->id => 'B'],
    ]);
    $items = collect(app(RecapService::class)->itemAnalysis($this->schedule))->keyBy('type');
    expect($items['pg']['p'])->toBe(0.5)->and($items['pg']['distractors']['C']['key'])->toBeTrue()->and($items['pg']['distractors']['A']['pct'])->toBe(25.0);
    $this->actingAs(staff())->get(route('admin.item-analysis', ['jadwal' => $this->schedule->id]))->assertOk()->assertSee('Daya beda');
});

it('koreksi uraian menghitung ulang nilai siswa', function () {
    [$a] = finishedAttempts($this->schedule, [[$this->q['pg']->id => 'C', $this->q['uraian']->id => 'Esai saya']]);
    expect($a->needs_grading)->toBeTrue();
    $ans = AttemptAnswer::query()->where('exam_attempt_id', $a->id)->where('question_id', $this->q['uraian']->id)->first();
    Livewire::actingAs(staff())->test(Grading::class)->set('scheduleId', $this->schedule->id)
        ->set("scores.{$ans->id}", '3')->call('grade', $ans->id)->assertHasErrors()
        ->set("scores.{$ans->id}", '2')->call('grade', $ans->id)->assertHasNoErrors();
    $a->refresh();
    // pg 1 + uraian 2 dari total bobot 7 → 42.86
    expect($a->needs_grading)->toBeFalse()->and($a->score)->toBe(42.86);
});

it('IntegrityAnalyzer menandai jawaban salah identik dan perangkat dipakai banyak NISN (perlu ditinjau)', function () {
    $wrongSet = [$this->q['pg']->id => 'A', $this->q['pgk']->id => ['B'], $this->q['bs']->id => ['1' => false, '2' => true], $this->q['isian']->id => 'Jakarta', $this->q['jodoh']->id => ['1' => 'a', '2' => 'b']];
    $attempts = finishedAttempts($this->schedule, [
        $wrongSet, $wrongSet,
        [$this->q['pg']->id => 'C', $this->q['isian']->id => 'Serang'],
        [$this->q['pg']->id => 'D', $this->q['isian']->id => 'Bandung'],
        [$this->q['pg']->id => 'C'],
        [$this->q['pg']->id => 'B'],
    ]);
    foreach ([$attempts[2], $attempts[3]] as $a) {
        AttemptInstance::query()->create(['exam_attempt_id' => $a->id, 'instance_id' => 'i' . $a->id, 'fingerprint' => 'sama', 'ip' => '1.2.3.4', 'first_seen_at' => now()]);
    }
    app(IntegrityAnalyzer::class)->analyze($this->schedule);
    $flags = IntegrityFlag::query()->get();
    $identical = $flags->firstWhere('kind', 'identical_wrong');
    expect($identical)->not->toBeNull()->and($identical->student_ids)->toEqualCanonicalizing([$attempts[0]->student_id, $attempts[1]->student_id])
        ->and($identical->verdict)->toBeNull()
        ->and($flags->firstWhere('kind', 'shared_device')->student_ids)->toEqualCanonicalizing([$attempts[2]->student_id, $attempts[3]->student_id]);
    $this->actingAs(staff())->get(route('admin.integrity', ['jadwal' => $this->schedule->id]))->assertOk()->assertSee('PERLU DITINJAU');
});

it('ekspor Excel & PDF diproses job antrean dan bisa diunduh pemiliknya', function () {
    Storage::fake('local');
    finishedAttempts($this->schedule, [[$this->q['pg']->id => 'C']]);
    $user = staff();
    foreach (['recap_xlsx', 'recap_pdf', 'erapor_xlsx', 'violations_xlsx', 'item_analysis_xlsx'] as $type) {
        $exp = ReportExport::query()->create(['user_id' => $user->id, 'type' => $type, 'params' => ['schedule' => $this->schedule->id]]);
        app()->call([new ExportReport($exp->id), 'handle']);
        $exp->refresh();
        expect($exp->status)->toBe('done', $type . ': ' . $exp->error);
        Storage::disk('local')->assertExists($exp->path);
    }
    $this->actingAs($user)->get(route('admin.exports.download', $exp))->assertOk();
    $this->actingAs(staff('guru'))->get(route('admin.exports.download', $exp))->assertForbidden();
});

it('berita acara & daftar hadir PDF', function () {
    finishedAttempts($this->schedule, [[$this->q['pg']->id => 'C']]);
    $admin = staff();
    $this->actingAs($admin)->get(route('admin.minutes.show', $this->schedule))->assertOk()->assertSee('Daftar hadir');
    $res = $this->actingAs($admin)->get(route('admin.minutes.pdf', $this->schedule) . '?catatan=Lancar');
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('pdf');
});

it('rekap pelanggaran per jenis & per jam', function () {
    $a = startedAttempt($this, $this->schedule);
    $this->postJson("/api/attempts/{$a->id}/events", ['instance_id' => 'inst-A', 'client_now' => now()->getTimestampMs(), 'events' => [['seq' => 7, 'type' => 'tab_hidden', 'at' => now()->getTimestampMs()]]]);
    $v = app(RecapService::class)->violations($this->schedule);
    expect($v['total'])->toBe(1)->and($v['byType'][0]['type'])->toBe('tab_hidden');
    $this->actingAs(staff())->get(route('admin.violations', ['jadwal' => $this->schedule->id]))->assertOk()->assertSee('Pindah tab/aplikasi');
});
