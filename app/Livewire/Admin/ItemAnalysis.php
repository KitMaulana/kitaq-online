<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\PicksSchedule;
use App\Services\Reports\RecapService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Analisis Butir')]
class ItemAnalysis extends Component
{
    use PicksSchedule, \App\Livewire\Concerns\WithToast;

    public function export(): void
    {
        $s = $this->pickedSchedule();
        abort_unless($s, 404);
        $exp = \App\Models\ReportExport::query()->create(['user_id' => auth()->id(), 'type' => 'item_analysis_xlsx', 'params' => ['schedule' => $s->id]]);
        \App\Models\ActivityLog::record('export', $exp->label() . ' · ' . $s->title(), $exp);
        \App\Jobs\ExportReport::dispatch($exp->id);
        $this->toast('info', 'Ekspor diproses di antrean', 'Unduh dari menu Unduhan.');
    }

    public function render(RecapService $recap)
    {
        $s = $this->pickedSchedule();

        return view('livewire.admin.item-analysis', [
            'options' => $this->scheduleOptions(),
            'schedule' => $s,
            'items' => $s ? $recap->itemAnalysis($s) : [],
        ]);
    }
}
