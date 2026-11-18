<?php

/*
| Pest — KITAQ ONLINE.
| Unit test bawaan starter kit tidak butuh Laravel; Feature test memakai TestCase + RefreshDatabase.
*/

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\ShieldService;
use Database\Seeders\RoleSeeder;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RoleSeeder::class))
    ->in('Feature');

function staff(string $role = 'super_admin', array $attrs = []): User
{
    $u = User::query()->create(array_merge([
        'name' => ucfirst($role) . ' Uji',
        'username' => $role . random_int(1000, 99999),
        'password' => 'Rahasia#2026kq',
        'is_active' => true,
    ], $attrs));
    $u->assignRole($role);

    return $u;
}

function classroom(string $name = 'XII IPA 1'): Classroom
{
    return Classroom::query()->firstOrCreate(['name' => $name]);
}

function student(array $attrs = []): Student
{
    return Student::query()->create(array_merge([
        'classroom_id' => classroom()->id,
        'nisn' => '0081234567',
        'name' => 'Aisyah Putri',
        'gender' => 'P',
        'birth_date' => '2008-10-20',
        'password' => '20102008',
    ], $attrs));
}

/** Bank soal berisi 1 soal per tipe (pg, pgk, bs, jodoh, isian, uraian). */
function bankWithQuestions(): QuestionBank
{
    $subject = Subject::query()->firstOrCreate(['code' => 'MTK'], ['name' => 'Matematika']);
    $bank = QuestionBank::query()->create(['subject_id' => $subject->id, 'name' => 'Bank Uji']);
    $mk = function (string $type, $key, array $opts = [], array $extra = []) use ($bank) {
        $q = Question::query()->create(array_merge(['question_bank_id' => $bank->id, 'type' => $type, 'content' => "<p>Soal {$type}</p>", 'answer_key' => $key, 'weight' => 1], $extra));
        foreach ($opts as $i => $o) {
            $q->options()->create(['key' => $o[0], 'content' => $o[1], 'side' => $o[2] ?? null, 'position' => $i]);
        }

        return $q;
    };
    $mk('pg', 'C', [['A', 'satu'], ['B', 'dua'], ['C', 'tiga'], ['D', 'empat']]);
    $mk('pgk', ['A', 'C'], [['A', 'a'], ['B', 'b'], ['C', 'c']]);
    $mk('bs', ['1' => true, '2' => false], [['1', 'P1'], ['2', 'P2']]);
    $mk('jodoh', ['1' => 'b', '2' => 'a'], [['1', 'L1', 'left'], ['2', 'L2', 'left'], ['a', 'R1', 'right'], ['b', 'R2', 'right']]);
    $mk('isian', ['Serang', 'Kota Serang']);
    $mk('uraian', null, [], ['weight' => 2]);

    return $bank;
}

function examSchedule(array $settings = [], array $attrs = []): ExamSchedule
{
    $bank = bankWithQuestions();
    $exam = Exam::query()->create([
        'name' => 'PTS Uji', 'type' => 'PTS', 'subject_id' => $bank->subject_id, 'question_bank_id' => $bank->id,
        'duration_minutes' => 60, 'kkm' => 75, 'settings' => $settings,
    ]);

    return ExamSchedule::query()->create(array_merge([
        'exam_id' => $exam->id, 'classroom_id' => classroom()->id, 'date' => today(),
        'opens_at' => now()->subMinutes(10), 'closes_at' => now()->addHours(2), 'status' => 'open', 'token_required' => true,
    ], $attrs));
}

/** Siswa login + attempt sudah dimulai (lewat token) + instance aktif terdaftar. */
function startedAttempt(\Tests\TestCase $test, ?ExamSchedule $schedule = null, string $instance = 'inst-A'): ExamAttempt
{
    $schedule = $schedule ?? examSchedule();
    $student = Student::query()->first() ?? student();
    $test->post('/login', ['nisn' => $student->nisn, 'password' => '20102008']);
    $test->post("/ujian/{$schedule->id}/mulai", ['token' => $schedule->currentToken()]);
    $attempt = ExamAttempt::query()->where('student_id', $student->id)->where('exam_schedule_id', $schedule->id)->firstOrFail();
    $test->postJson("/api/attempts/{$attempt->id}/events", ['instance_id' => $instance, 'client_now' => now()->getTimestampMs(), 'events' => [
        ['seq' => 1, 'type' => 'exam_started', 'at' => now()->getTimestampMs(), 'detail' => ['os' => 'android', 'browser' => 'chrome', 'device' => 'phone', 'standalone' => false]],
    ]])->assertOk();

    return $attempt->fresh();
}
