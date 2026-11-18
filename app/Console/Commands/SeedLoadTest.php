<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Data uji beban untuk tests/load/k6-exam.js (docs/UJI_BEBAN.md):
 *  - N siswa dummy, NISN = 10 digit terakhir dari 99000000000 + i (9000000001, …), tanggal lahir 01-01-2008 (password 01012008);
 *  - bank soal 20 PG + ujian + jadwal TERBUKA sekarang (tanpa token wajib) di kelas "UJI BEBAN".
 * Jalankan di luar jam ujian sungguhan, lalu hapus dengan --hapus.
 */
class SeedLoadTest extends Command
{
    protected $signature = 'kitaq:seed-load {count=300 : jumlah siswa dummy} {--hapus : hapus data uji beban}';

    protected $description = 'Siapkan (atau hapus) siswa dummy + ujian untuk uji beban k6';

    public function handle(): int
    {
        $class = Classroom::query()->firstOrCreate(['name' => 'UJI BEBAN']);
        if ($this->option('hapus')) {
            $n = Student::query()->where('classroom_id', $class->id)->delete();
            ExamSchedule::query()->where('classroom_id', $class->id)->delete();
            Exam::query()->where('name', 'UJI BEBAN k6')->delete();
            QuestionBank::query()->where('name', 'UJI BEBAN k6')->delete();
            $class->delete();
            $this->info("Data uji beban dihapus ({$n} siswa).");

            return self::SUCCESS;
        }

        $count = max(1, min(5000, (int) $this->argument('count')));
        $hash = Hash::make('01012008');
        $now = now();
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'classroom_id' => $class->id, 'nisn' => substr((string) (99000000000 + $i), -10), 'name' => "Siswa Uji {$i}",
                'gender' => $i % 2 ? 'L' : 'P', 'birth_date' => '2008-01-01', 'password' => $hash, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Student::query()->upsert($chunk, ['nisn'], ['classroom_id', 'password', 'is_active', 'updated_at']);
        }

        $subject = Subject::query()->firstOrCreate(['code' => 'UJI'], ['name' => 'Uji Beban']);
        $bank = QuestionBank::query()->firstOrCreate(['name' => 'UJI BEBAN k6'], ['subject_id' => $subject->id]);
        if (! $bank->questions()->exists()) {
            for ($n = 1; $n <= 20; $n++) {
                $q = Question::query()->create(['question_bank_id' => $bank->id, 'type' => 'pg', 'content' => "<p>Soal uji beban nomor {$n}: {$n} + {$n} = …</p>", 'answer_key' => 'A', 'position' => $n]);
                foreach (['A' => $n * 2, 'B' => $n * 2 + 1, 'C' => $n * 2 - 1, 'D' => $n] as $k => $v) {
                    $q->options()->create(['key' => $k, 'content' => "<p>{$v}</p>", 'position' => ord($k)]);
                }
            }
        }
        $exam = Exam::query()->firstOrCreate(['name' => 'UJI BEBAN k6'], [
            'type' => 'LAIN', 'subject_id' => $subject->id, 'question_bank_id' => $bank->id, 'duration_minutes' => 120,
            'settings' => ['token_required' => false, 'shuffle_questions' => true, 'preset' => 'longgar'],
        ]);
        $schedule = ExamSchedule::query()->create([
            'exam_id' => $exam->id, 'classroom_id' => $class->id, 'date' => today(), 'opens_at' => now()->subMinute(),
            'closes_at' => now()->addHours(3), 'status' => 'open', 'token_required' => false,
        ]);

        $this->info("{$count} siswa dummy siap (password 01012008). Jadwal uji beban ID: {$schedule->id}");
        $this->line("Aktifkan KITAQ_LOAD_TEST=true di .env selama uji (melewati gerbang browser), lalu:");
        $this->line("  k6 run -e BASE=" . rtrim((string) config('app.url'), '/') . " -e VUS={$count} -e SCHEDULE={$schedule->id} tests/load/k6-exam.js");
        $this->warn('Setelah selesai: php artisan kitaq:seed-load --hapus dan kembalikan KITAQ_LOAD_TEST=false.');

        return self::SUCCESS;
    }
}
