<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithImport;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Auth\StudentCredential;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Import siswa dari templates/template_siswa.xlsx (Spesifikasi 8.1):
 * nisn*, nama*, tanggal_lahir*, jenis_kelamin*, kelas*, nis, tempat_lahir.
 * Tanggal lahir dibaca dari berbagai format (StudentCredential::parseBirthDate), nol di depan NISN dipulihkan.
 */
#[Title('Import Siswa')]
class StudentImport extends Component
{
    use WithImport, WithToast;

    public bool $createClasses = true;

    /** @var array<string,int> */
    private array $classCache = [];

    /** @return array{data:array,errors:list<string>,warnings:list<string>} */
    protected function validateImportRow(array $row, int $line, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $rawNisn = $row['nisn'] ?? null;
        $nisn = StudentCredential::normalizeNisn(is_numeric($rawNisn) && ! is_string($rawNisn) ? $rawNisn : (string) $rawNisn);
        if ($nisn === null) {
            $errors[] = 'NISN harus 10 digit';
        } elseif (isset($seen[$nisn])) {
            $errors[] = "NISN ganda (sama dengan baris {$seen[$nisn]})";
        } else {
            $seen[$nisn] = $line;
            if (strlen(preg_replace('/\D/', '', (string) $rawNisn)) < 10) {
                $warnings[] = 'Nol di depan NISN dipulihkan';
            }
        }

        $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Nama kosong';
        }

        $birth = StudentCredential::parseBirthDate($row['tanggal_lahir'] ?? $row['tgl_lahir'] ?? null);
        if ($birth === null) {
            $errors[] = 'Tanggal lahir tidak valid';
        }

        $g = strtoupper(substr(trim((string) ($row['jenis_kelamin'] ?? $row['jk'] ?? '')), 0, 1));
        if (! in_array($g, ['L', 'P'], true)) {
            $errors[] = 'Jenis kelamin harus L atau P';
        }

        $class = trim((string) ($row['kelas'] ?? ''));
        if ($class === '') {
            $errors[] = 'Kelas kosong';
        } elseif (! $this->createClasses && ! Classroom::query()->where('name', $class)->exists()) {
            $errors[] = "Kelas \"{$class}\" belum ada";
        } elseif (! Classroom::query()->where('name', $class)->exists()) {
            $warnings[] = "Kelas \"{$class}\" akan dibuat";
        }

        if ($nisn && Student::query()->where('nisn', $nisn)->exists()) {
            $warnings[] = $this->mode === 'update' ? 'Sudah ada → diperbarui' : 'Sudah ada → dilewati';
        }

        return [
            'data' => [
                'nisn' => $nisn, 'name' => $name, 'birth_date' => $birth?->format('Y-m-d'), 'gender' => $g, 'kelas' => $class,
                'nis' => isset($row['nis']) && $row['nis'] !== '' ? (string) (is_float($row['nis']) ? (int) $row['nis'] : $row['nis']) : null,
                'birth_place' => ($row['tempat_lahir'] ?? null) ? (string) $row['tempat_lahir'] : null,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    protected function persistImportRow(array $d, string $mode): string
    {
        $classId = $this->classCache[$d['kelas']] ??= Classroom::query()->firstOrCreate(['name' => $d['kelas']], [
            'grade' => preg_match('/^(X{1,3}I{0,2}|I{1,3}|IV|V|VI|VII|VIII|IX|\d+)\b/', $d['kelas'], $m) ? $m[1] : null,
        ])->id;
        $existing = Student::query()->where('nisn', $d['nisn'])->first();
        if ($existing && $mode === 'skip') {
            return 'skipped';
        }
        $student = $existing ?? new Student(['nisn' => $d['nisn']]);
        $birthChanged = ! $existing || $existing->birth_date?->format('Y-m-d') !== $d['birth_date'];
        $student->fill([
            'name' => $d['name'], 'gender' => $d['gender'], 'birth_date' => $d['birth_date'], 'classroom_id' => $classId,
            'nis' => $d['nis'], 'birth_place' => $d['birth_place'],
        ]);
        if ($birthChanged) {
            $student->password = StudentCredential::passwordFromBirthDate(new \DateTimeImmutable($d['birth_date']));
        }
        $student->save();

        return $existing ? 'updated' : 'created';
    }

    protected function importSheet(): ?string
    {
        return 'siswa';
    }

    protected function afterImport(): void
    {
        ActivityLog::record('student_import', "Import siswa: {$this->result['created']} baru, {$this->result['updated']} diperbarui, {$this->result['failed']} gagal");
        $this->toast('success', 'Import selesai', "{$this->result['created']} baru · {$this->result['updated']} diperbarui");
    }

    public function render()
    {
        return view('livewire.admin.student-import');
    }
}
