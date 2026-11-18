<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Models\ReportExport;
use App\Models\Student;
use App\Services\Reports\RecapService;
use App\Support\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Ekspor Excel/PDF lewat antrean (dikerjakan queue:work dari cron — tanpa Supervisor). */
class ExportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 45;
    public int $tries = 2;

    public function __construct(public int $exportId)
    {
    }

    public function handle(RecapService $recap): void
    {
        $exp = ReportExport::query()->find($this->exportId);
        if (! $exp || $exp->status === 'done') {
            return;
        }
        $exp->update(['status' => 'running']);
        try {
            $p = (array) $exp->params;
            $schedule = isset($p['schedule']) ? ExamSchedule::query()->with('exam.subject', 'classroom')->find($p['schedule']) : null;
            $name = Str::slug($exp->label() . ' ' . ($schedule?->title() ?? '')) . '-' . now()->format('Ymd-His');
            $dir = 'exports/' . $exp->user_id;
            Storage::disk('local')->makeDirectory($dir);

            switch ($exp->type) {
                case 'recap_xlsx':
                case 'erapor_xlsx':
                    $rows = $recap->recap($schedule)->values()->map(function ($r, $i) use ($exp) {
                        return $exp->type === 'erapor_xlsx'
                            ? ['no' => $i + 1, 'nisn' => $r['nisn'], 'nis' => $r['nis'], 'nama' => $r['name'], 'kelas' => $r['class'], 'nilai' => $r['score'] !== null ? round($r['score']) : '']
                            : ['peringkat' => $i + 1, 'nisn' => $r['nisn'], 'nama' => $r['name'], 'kelas' => $r['class'], 'benar' => $r['correct'], 'salah' => $r['wrong'], 'kosong' => $r['blank'], 'nilai' => $r['score'], 'tuntas' => $r['passed'] === null ? '-' : ($r['passed'] ? 'Ya' : 'Tidak'), 'pelanggaran' => $r['violations'], 'status' => $r['status']];
                    })->all();
                    $path = Xlsx::writeTemp($rows ?: [['info' => 'Tidak ada data']]);
                    $this->store($exp, $dir . '/' . $name . '.xlsx', $path);
                    break;
                case 'recap_pdf':
                    $rows = $recap->recap($schedule);
                    $pdf = Pdf::loadView('pdf.recap', ['schedule' => $schedule, 'rows' => $rows, 'summary' => $recap->summary($rows)])->setPaper('a4', 'landscape');
                    Storage::disk('local')->put($dir . '/' . $name . '.pdf', $pdf->output());
                    $exp->update(['status' => 'done', 'path' => $dir . '/' . $name . '.pdf']);
                    break;
                case 'violations_xlsx':
                    $data = $recap->violations($schedule, $p['date'] ?? null);
                    $rows = array_map(fn ($t) => ['jenis' => $t['label'], 'kode' => $t['type'], 'total' => $t['total'], 'peringatan' => $t['warn'], 'kunci' => $t['lock']], $data['byType']);
                    $path = Xlsx::writeTemp($rows ?: [['info' => 'Tidak ada pelanggaran']]);
                    $this->store($exp, $dir . '/' . $name . '.xlsx', $path);
                    break;
                case 'item_analysis_xlsx':
                    $rows = array_map(fn ($r) => ['no' => $r['no'], 'tipe' => $r['type'], 'soal' => $r['text'], 'p_kesukaran' => $r['p'], 'kategori' => $r['difficulty_label'], 'daya_beda' => $r['d'], 'kualitas' => $r['discrimination_label']], $recap->itemAnalysis($schedule));
                    $path = Xlsx::writeTemp($rows ?: [['info' => 'Belum ada data']]);
                    $this->store($exp, $dir . '/' . $name . '.xlsx', $path);
                    break;
                case 'students_xlsx':
                    // Data pribadi: hanya Super Admin/Operator (dicek saat membuat ekspor) dan tercatat di log aktivitas.
                    $rows = Student::query()->with('classroom')->when($p['classroom'] ?? null, fn ($q, $c) => $q->where('classroom_id', $c))->orderBy('name')->get()
                        ->map(fn ($s) => ['nisn' => $s->nisn, 'nama' => $s->name, 'jenis_kelamin' => $s->gender, 'kelas' => $s->classroom?->name, 'nis' => $s->nis, 'tempat_lahir' => $s->birth_place, 'tanggal_lahir' => $s->birth_date?->format('d/m/Y'), 'aktif' => $s->is_active ? 'Ya' : 'Tidak'])->all();
                    $path = Xlsx::writeTemp($rows ?: [['info' => 'Tidak ada data']]);
                    $this->store($exp, $dir . '/' . $name . '.xlsx', $path);
                    break;
                default:
                    throw new \RuntimeException('Jenis ekspor tidak dikenal');
            }
        } catch (\Throwable $e) {
            report($e);
            $exp->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private function store(ReportExport $exp, string $target, string $tmp): void
    {
        Storage::disk('local')->put($target, file_get_contents($tmp));
        @unlink($tmp);
        $exp->update(['status' => 'done', 'path' => $target]);
    }
}
