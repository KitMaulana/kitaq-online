<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Auth\StudentCredential;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba KITAQ ONLINE di laptop (APP_ENV=local).
 * Siswa contoh: NISN 0081234501..0081234530, tanggal lahir 20-10-2008 → password 20102008.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Student::query()->exists()) {
            return;
        }

        Setting::put('school_name', 'SMA Negeri Contoh');
        Setting::put('school_city', 'Serang');
        Setting::put('principal_name', 'Dra. Siti Rahmawati, M.Pd.');

        $year = AcademicYear::query()->create(['name' => '2026/2027', 'semester' => 'ganjil', 'is_active' => true]);
        $classes = collect(['XII IPA 1', 'XII IPS 1', 'XI IPA 2'])->map(fn ($n) => Classroom::query()->create([
            'academic_year_id' => $year->id, 'name' => $n, 'grade' => explode(' ', $n)[0],
        ]));
        $mtk = Subject::query()->create(['code' => 'MTK', 'name' => 'Matematika']);
        Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia']);
        Subject::query()->create(['code' => 'BIG', 'name' => 'Bahasa Inggris']);
        Room::query()->create(['name' => 'Ruang 1', 'capacity' => 36]);
        $sesi = ExamSession::query()->create(['name' => 'Sesi 1', 'starts_at' => '07:30', 'ends_at' => '09:30']);

        $guru = User::query()->create(['name' => 'Budi Santoso, S.Pd.', 'username' => 'guru', 'password' => 'Guru#2026kitaq', 'is_active' => true]);
        $guru->assignRole('guru');
        $guru->subjects()->attach($mtk->id);
        $operator = User::query()->create(['name' => 'Operator Sekolah', 'username' => 'operator', 'password' => 'Operator#2026kitaq', 'is_active' => true]);
        $operator->assignRole('operator');
        $pengawas = User::query()->create(['name' => 'Rina Pengawas', 'username' => 'pengawas', 'password' => 'Pengawas#2026kitaq', 'is_active' => true]);
        $pengawas->assignRole('pengawas');

        $names = ['Aisyah Putri Rahayu', 'Bagas Pratama', 'Citra Lestari', 'Dimas Saputra', 'Eka Wulandari', 'Fajar Nugroho', 'Gita Permata', 'Hendra Wijaya', 'Indah Sari', 'Joko Susilo'];
        $birth = StudentCredential::parseBirthDate('20/10/2008');
        $n = 1;
        foreach ($classes as $ci => $class) {
            foreach ($names as $i => $name) {
                Student::query()->create([
                    'classroom_id' => $class->id,
                    'nisn' => sprintf('00812345%02d', $n++),
                    'nis' => (string) (2601000 + $n),
                    'name' => $name . ($ci ? ' ' . chr(65 + $ci) : ''),
                    'gender' => $i % 2 ? 'L' : 'P',
                    'birth_date' => $birth->format('Y-m-d'),
                    'password' => StudentCredential::passwordFromBirthDate($birth),
                ]);
            }
        }

        $bank = QuestionBank::query()->create(['subject_id' => $mtk->id, 'user_id' => $guru->id, 'name' => 'Matematika XII — Contoh', 'grade' => 'XII']);
        $this->sampleQuestions($bank);

        $exam = Exam::query()->create([
            'name' => 'PTS Matematika XII', 'type' => 'PTS', 'subject_id' => $mtk->id, 'question_bank_id' => $bank->id,
            'user_id' => $guru->id, 'question_count' => 0, 'duration_minutes' => 60, 'kkm' => 75, 'settings' => [],
        ]);
        $schedule = ExamSchedule::query()->create([
            'exam_id' => $exam->id, 'classroom_id' => $classes[0]->id, 'exam_session_id' => $sesi->id, 'room_id' => 1,
            'date' => today(), 'opens_at' => now()->startOfHour(), 'closes_at' => now()->startOfHour()->addHours(4),
            'status' => 'open', 'token_required' => true,
        ]);
        $schedule->supervisors()->attach($pengawas->id);
    }

    private function sampleQuestions(QuestionBank $bank): void
    {
        $pos = 1;
        $make = function (string $type, string $content, $key, array $options = [], array $extra = []) use ($bank, &$pos) {
            $q = Question::query()->create(array_merge([
                'question_bank_id' => $bank->id, 'type' => $type, 'content' => $content, 'answer_key' => $key,
                'weight' => 1, 'difficulty' => 'sedang', 'position' => $pos++,
            ], $extra));
            foreach ($options as $i => $o) {
                $q->options()->create(['key' => $o[0], 'side' => $o[2] ?? null, 'content' => $o[1], 'position' => $i]);
            }

            return $q;
        };

        $make('pg', '<p>Hasil dari 12 × 15 adalah …</p>', 'C', [['A', '160'], ['B', '170'], ['C', '180'], ['D', '190'], ['E', '200']]);
        $make('pg', '<p>Nilai x yang memenuhi 2x + 6 = 20 adalah …</p>', 'B', [['A', '6'], ['B', '7'], ['C', '8'], ['D', '13'], ['E', '14']]);
        $make('pg', '<p>Luas lingkaran berjari-jari 7 cm (π = 22/7) adalah …</p>', 'D', [['A', '44 cm²'], ['B', '88 cm²'], ['C', '144 cm²'], ['D', '154 cm²'], ['E', '308 cm²']]);
        $make('pgk', '<p>Manakah bilangan prima di bawah ini? (pilih semua yang benar)</p>', ['A', 'C', 'D'], [['A', '2'], ['B', '9'], ['C', '11'], ['D', '13'], ['E', '15']]);
        $make('bs', '<p>Tentukan benar atau salah pernyataan berikut.</p>', ['1' => true, '2' => false, '3' => true], [['1', 'Jumlah sudut segitiga adalah 180°'], ['2', '√16 = 8'], ['3', '2³ = 8']]);
        $make('jodoh', '<p>Jodohkan bangun datar dengan rumus luasnya.</p>', ['1' => 'b', '2' => 'a', '3' => 'c'], [
            ['1', 'Persegi', 'left'], ['2', 'Segitiga', 'left'], ['3', 'Lingkaran', 'left'],
            ['a', '½ × alas × tinggi', 'right'], ['b', 'sisi × sisi', 'right'], ['c', 'π × r²', 'right'],
        ]);
        $make('isian', '<p>Ibu kota provinsi Banten adalah …</p>', ['Serang', 'Kota Serang']);
        $make('isian', '<p>Hasil dari 125 : 5 adalah …</p>', ['25'], [], ['numeric' => true]);
        $make('uraian', '<p>Jelaskan langkah-langkah menyelesaikan persamaan kuadrat x² − 5x + 6 = 0 dengan pemfaktoran.</p>', null, [], ['weight' => 2]);
        $make('pg', '<p>Median dari data 3, 7, 5, 9, 1 adalah …</p>', 'B', [['A', '3'], ['B', '5'], ['C', '7'], ['D', '9'], ['E', '25']]);
    }
}
