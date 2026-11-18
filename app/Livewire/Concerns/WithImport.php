<?php

namespace App\Livewire\Concerns;

use App\Support\Xlsx;
use Livewire\WithFileUploads;

/**
 * Wizard import 3 langkah (DESIGN_SYSTEM 5.3): Unggah → Pratinjau & validasi → Hasil.
 * Komponen mengimplementasikan:
 *   validateImportRow(array $row, int $line, array &$seen): array{data:array, errors:list<string>, warnings:list<string>}
 *   persistImportRow(array $data, string $mode): string   ('created' | 'updated' | 'skipped')
 */
trait WithImport
{
    use WithFileUploads;

    public int $step = 1;

    public $file;

    public string $mode = 'skip';          // skip | update

    public bool $onlyErrors = false;

    /** @var list<array{line:int,data:array,errors:list<string>,warnings:list<string>,raw:array}> */
    public array $previewRows = [];

    /** @var array{created:int,updated:int,skipped:int,failed:int} */
    public array $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

    /** @var list<array> */
    public array $failedRows = [];

    public function analyze(): void
    {
        $this->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx']], [], ['file' => 'berkas Excel']);
        $rows = Xlsx::readAssoc($this->localCopy($this->file), $this->importSheet());
        $seen = [];
        $this->previewRows = [];
        foreach ($rows as $i => $raw) {
            if (! array_filter($raw, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $line = $i + 2;
            $res = $this->validateImportRow($raw, $line, $seen);
            $this->previewRows[] = ['line' => $line, 'data' => $res['data'], 'errors' => $res['errors'], 'warnings' => $res['warnings'], 'raw' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $raw)];
        }
        if (! $this->previewRows) {
            $this->addError('file', 'Tidak ada baris data. Pastikan memakai template yang benar (baris 1 = judul kolom).');

            return;
        }
        $this->step = 2;
    }

    public function runImport(): void
    {
        $this->result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $this->failedRows = [];
        foreach ($this->previewRows as $row) {
            if ($row['errors']) {
                $this->result['failed']++;
                $this->failedRows[] = $row['raw'] + ['galat' => implode('; ', $row['errors'])];
                continue;
            }
            try {
                $this->result[$this->persistImportRow($row['data'], $this->mode)]++;
            } catch (\Throwable $e) {
                report($e);
                $this->result['failed']++;
                $this->failedRows[] = $row['raw'] + ['galat' => 'Gagal disimpan: ' . $e->getMessage()];
            }
        }
        $this->afterImport();
        $this->step = 3;
    }

    protected function afterImport(): void
    {
    }

    /**
     * Salin unggahan ke path pendek: ZipArchive (libzip) gagal membuka path > 260 karakter di Windows,
     * dan nama berkas sementara Livewire sangat panjang.
     */
    protected function localCopy($upload): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kqi');
        file_put_contents($tmp, $upload->get());
        register_shutdown_function(fn () => @unlink($tmp));

        return $tmp;
    }

    /** Nama sheet data di template (sheet PETUNJUK dilewati). */
    protected function importSheet(): ?string
    {
        return null;
    }

    public function resetImport(): void
    {
        $this->reset(['step', 'file', 'previewRows', 'result', 'failedRows', 'onlyErrors']);
    }

    public function getImportSummaryProperty(): array
    {
        $err = count(array_filter($this->previewRows, fn ($r) => $r['errors']));
        $warn = count(array_filter($this->previewRows, fn ($r) => ! $r['errors'] && $r['warnings']));

        return ['total' => count($this->previewRows), 'errors' => $err, 'warnings' => $warn, 'valid' => count($this->previewRows) - $err];
    }

    /** Unduh laporan galat (baris tidak valid) sebagai XLSX. */
    public function downloadErrors()
    {
        $rows = $this->step === 3 ? $this->failedRows : array_map(
            fn ($r) => $r['raw'] + ['galat' => implode('; ', $r['errors'])],
            array_values(array_filter($this->previewRows, fn ($r) => $r['errors']))
        );
        $path = Xlsx::writeTemp($rows ?: [['galat' => 'Tidak ada galat']]);

        return response()->download($path, 'laporan-galat-import.xlsx')->deleteFileAfterSend();
    }
}
