<?php

namespace App\Livewire\Admin;

use App\Jobs\ExportReport;
use App\Livewire\Concerns\PicksSchedule;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\ReportExport;
use App\Services\Reports\RecapService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rekap Nilai')]
class Recap extends Component
{
    use PicksSchedule, WithToast;

    public function export(string $type): void
    {
        abort_unless(in_array($type, ['recap_xlsx', 'recap_pdf', 'erapor_xlsx', 'item_analysis_xlsx', 'violations_xlsx'], true), 404);
        $s = $this->pickedSchedule();
        abort_unless($s, 404);
        $exp = ReportExport::query()->create(['user_id' => auth()->id(), 'type' => $type, 'params' => ['schedule' => $s->id]]);
        ActivityLog::record('export', $exp->label() . ' · ' . $s->title(), $exp);
        ExportReport::dispatch($exp->id);
        $this->toast('info', 'Ekspor diproses di antrean', 'Unduh dari menu Unduhan setelah selesai (diproses cron tiap menit).');
    }

    public function render(RecapService $recap)
    {
        $s = $this->pickedSchedule();
        $rows = $s ? $recap->recap($s) : collect();

        return view('livewire.admin.recap', [
            'options' => $this->scheduleOptions(),
            'schedule' => $s,
            'rows' => $rows,
            'summary' => $recap->summary($rows),
        ]);
    }
}
