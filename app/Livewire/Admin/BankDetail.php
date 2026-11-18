<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Stimulus;
use App\Services\Questions\HtmlCleaner;
use App\Services\Questions\ImageOptimizer;
use App\Services\Questions\QuestionImporter;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Isi bank soal: daftar soal, stimulus bersama, pratinjau, dan import Excel + ZIP gambar. */
#[Title('Isi Bank Soal')]
class BankDetail extends Component
{
    use WithFileUploads, WithPagination, WithToast;

    public QuestionBank $bank;
    public string $tab = 'soal';
    public string $type = '';
    public string $search = '';

    // pratinjau
    public ?int $previewId = null;
    public bool $showPreview = false;

    // stimulus
    public bool $showStimulus = false;
    public ?int $stimulusId = null;
    public string $stimTitle = '';
    public string $stimContent = '';
    public $editorUpload;

    // import
    public bool $showImport = false;
    public int $importStep = 1;
    public $xlsx;
    public $zip;
    public array $importRows = [];
    public array $importStimuli = [];
    public array $zipErrors = [];
    public ?string $importDir = null;

    public bool $confirmDelete = false;
    public ?int $deleteId = null;

    public function mount(QuestionBank $bank): void
    {
        abort_unless($bank->canBeManagedBy(auth()->user()), 403);
        $this->bank = $bank;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function preview(int $id): void
    {
        $this->previewId = $id;
        $this->showPreview = true;
    }

    public function move(int $id, int $dir): void
    {
        $qs = $this->bank->questions()->get(['id', 'position'])->values();
        $i = $qs->search(fn ($q) => $q->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $qs->count()) {
            return;
        }
        foreach ($qs as $k => $q) {
            $pos = $k === $i ? $j : ($k === $j ? $i : $k);
            Question::query()->whereKey($q->id)->update(['position' => $pos + 1]);
        }
    }

    public function duplicate(int $id): void
    {
        $q = Question::query()->where('question_bank_id', $this->bank->id)->with('options')->findOrFail($id);
        $copy = $q->replicate();
        $copy->position = (int) $this->bank->questions()->max('position') + 1;
        $copy->save();
        foreach ($q->options as $o) {
            $copy->options()->create($o->only(['key', 'side', 'content', 'image', 'position']));
        }
        $this->toast('success', 'Soal digandakan');
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        Question::query()->where('question_bank_id', $this->bank->id)->whereKey($this->deleteId)->delete();
        ActivityLog::record('question_deleted', "Soal #{$this->deleteId} dari {$this->bank->name}", $this->bank);
        $this->confirmDelete = false;
        $this->toast('success', 'Soal dihapus');
    }

    /* ---------- Stimulus ---------- */
    public function editStimulus(?int $id = null): void
    {
        $s = $id ? Stimulus::query()->where('question_bank_id', $this->bank->id)->findOrFail($id) : null;
        $this->stimulusId = $s?->id;
        $this->stimTitle = (string) $s?->title;
        $this->stimContent = (string) $s?->content;
        $this->showStimulus = true;
    }

    public function storeEditorImage(): ?string
    {
        $this->validate(['editorUpload' => ['required', 'image', 'max:4096']]);
        $path = app(ImageOptimizer::class)->storeUpload($this->editorUpload);
        $this->editorUpload = null;

        return ImageOptimizer::adminUrl($path);
    }

    public function saveStimulus(HtmlCleaner $cleaner): void
    {
        $this->stimContent = $cleaner->clean($this->stimContent);
        $this->validate(['stimTitle' => ['nullable', 'string', 'max:150'], 'stimContent' => ['required', 'string']], [], ['stimContent' => 'teks stimulus']);
        Stimulus::query()->updateOrCreate(['id' => $this->stimulusId, 'question_bank_id' => $this->bank->id], ['title' => $this->stimTitle ?: null, 'content' => $this->stimContent]);
        $this->showStimulus = false;
        $this->toast('success', 'Stimulus tersimpan');
    }

    public function deleteStimulus(int $id): void
    {
        Stimulus::query()->where('question_bank_id', $this->bank->id)->whereKey($id)->delete();
        $this->toast('success', 'Stimulus dihapus', 'Soal terkait tetap ada tanpa stimulus.');
    }

    /* ---------- Import Excel + ZIP ---------- */
    public function analyzeImport(QuestionImporter $importer): void
    {
        $this->validate([
            'xlsx' => ['required', 'file', 'max:10240', 'mimes:xlsx'],
            'zip' => ['nullable', 'file', 'max:51200', 'mimes:zip'],
        ], [], ['xlsx' => 'berkas Excel', 'zip' => 'berkas ZIP gambar']);
        $dir = storage_path('app/private-import/' . auth()->id() . '-' . uniqid());
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/soal.xlsx', $this->xlsx->get());
        $zipPath = null;
        if ($this->zip) {
            file_put_contents($dir . '/gambar.zip', $this->zip->get());
            $zipPath = $dir . '/gambar.zip';
        }
        $this->importDir = $dir;
        $res = $importer->analyze($dir . '/soal.xlsx', $zipPath);
        $this->importRows = $res['rows'];
        $this->importStimuli = $res['stimuli'];
        $this->zipErrors = $res['zip_errors'];
        if (! $this->importRows) {
            $this->addError('xlsx', 'Tidak ada baris soal. Pastikan memakai template (sheet "soal") dan hapus tanda "#" pada baris yang ingin diimport.');

            return;
        }
        $this->importStep = 2;
    }

    public function runImport(QuestionImporter $importer): void
    {
        abort_unless($this->importDir && str_starts_with(realpath($this->importDir) ?: '', realpath(storage_path('app/private-import')) ?: '#'), 400);
        $zip = is_file($this->importDir . '/gambar.zip') ? $this->importDir . '/gambar.zip' : null;
        $n = $importer->import($this->bank, $this->importRows, $this->importStimuli, $zip);
        ActivityLog::record('question_import', "Import {$n} soal ke {$this->bank->name}", $this->bank);
        $this->cleanupImport();
        $this->importStep = 3;
        $this->importRows = array_map(fn ($r) => ['line' => $r['line'], 'errors' => $r['errors']], $this->importRows);
        $this->toast('success', "{$n} soal diimport");
    }

    public function closeImport(): void
    {
        $this->cleanupImport();
        $this->reset(['showImport', 'importStep', 'xlsx', 'zip', 'importRows', 'importStimuli', 'zipErrors']);
    }

    private function cleanupImport(): void
    {
        if ($this->importDir && is_dir($this->importDir)) {
            array_map('unlink', glob($this->importDir . '/*') ?: []);
            @rmdir($this->importDir);
        }
        $this->importDir = null;
    }

    public function render()
    {
        $q = $this->bank->questions()->with(['options', 'stimulus']);
        if ($this->type) {
            $q->where('type', $this->type);
        }
        if (trim($this->search) !== '') {
            $q->where('content', 'like', '%' . $this->search . '%');
        }
        $counts = Question::query()->where('question_bank_id', $this->bank->id)->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');

        return view('livewire.admin.bank-detail', [
            'questions' => $q->paginate(20),
            'counts' => $counts,
            'stimuli' => $this->bank->stimuli()->withCount('questions')->get(),
            'previewQ' => $this->previewId ? Question::query()->with(['options', 'stimulus'])->find($this->previewId) : null,
        ]);
    }
}
