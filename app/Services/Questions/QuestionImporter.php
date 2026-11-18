<?php

namespace App\Services\Questions;

use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Stimulus;
use App\Support\Xlsx;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Import soal dari templates/template_soal.xlsx (+ ZIP gambar, sama dengan KITAQ offline).
 * Format kunci — PG: B · PGK: A,C · BS: B,S,B · JODOH: 1-C,2-A,3-B · ISIAN: Serang|Kota Serang · URAIAN: kosong.
 * Keamanan ZIP: hanya nama berkas datar (tanpa folder / "..", cegah zip-slip), ekstensi gambar, maks 2 MB per gambar,
 * gambar TIDAK diekstrak ke disk apa adanya — dibaca ke memori lalu dikonversi WebP ≤ 1600 px.
 */
class QuestionImporter
{
    private const TYPES = ['PG' => 'pg', 'PGK' => 'pgk', 'BS' => 'bs', 'JODOH' => 'jodoh', 'ISIAN' => 'isian', 'URAIAN' => 'uraian'];
    private const LETTERS = ['a', 'b', 'c', 'd', 'e'];

    /** @var array<string,string> nama berkas (huruf kecil) → path media tersimpan */
    private array $stored = [];

    public function __construct(private HtmlCleaner $cleaner, private ImageOptimizer $images)
    {
    }

    /**
     * @return array{rows:list<array{line:int,data:array,errors:list<string>,warnings:list<string>}>,stimuli:array<string,array>,zip_errors:list<string>}
     */
    public function analyze(string $xlsxPath, ?string $zipPath = null): array
    {
        $zipNames = [];
        $zipErrors = [];
        if ($zipPath) {
            [$zipNames, $zipErrors] = $this->inspectZip($zipPath);
        }
        $stimuli = [];
        foreach (Xlsx::readAssoc($xlsxPath, 'stimulus') as $r) {
            $code = strtoupper(trim((string) ($r['kode'] ?? '')));
            if ($code !== '' && ! array_key_exists('tipe', $r)) {
                $stimuli[$code] = ['title' => (string) ($r['judul'] ?? ''), 'text' => (string) ($r['teks'] ?? ''), 'image' => (string) ($r['gambar'] ?? '')];
            }
        }

        $rows = [];
        foreach (Xlsx::readAssoc($xlsxPath, 'soal') as $i => $r) {
            if (! array_filter($r, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $rows[] = $this->validateRow($r, $i + 2, $zipNames, $stimuli);
        }

        return ['rows' => $rows, 'stimuli' => $stimuli, 'zip_errors' => $zipErrors];
    }

    /** @return array{0:array<string,bool>,1:list<string>} */
    public function inspectZip(string $zipPath): array
    {
        $names = [];
        $errors = [];
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [[], ['File ZIP tidak dapat dibuka.']];
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $name = (string) $st['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            if (! self::safeName($name)) {
                $errors[] = "Berkas \"{$name}\" ditolak (harus tanpa folder dan tanpa \"..\").";
                continue;
            }
            if (! preg_match('/\.(jpe?g|png|webp|gif)$/i', $name)) {
                $errors[] = "Berkas \"{$name}\" bukan gambar.";
                continue;
            }
            if ((int) $st['size'] > ImageOptimizer::MAX_BYTES) {
                $errors[] = "Gambar \"{$name}\" lebih dari 2 MB.";
                continue;
            }
            $names[strtolower($name)] = true;
        }
        $zip->close();

        return [$names, $errors];
    }

    /** Nama aman: datar, tanpa traversal, tanpa path absolut/drive. */
    public static function safeName(string $name): bool
    {
        return $name !== '' && ! str_contains($name, '..') && ! preg_match('#[/\\\\:]#', $name) && ! str_starts_with($name, '.');
    }

    private function validateRow(array $r, int $line, array $zipNames, array $stimuli): array
    {
        $errors = [];
        $warnings = [];
        $type = self::TYPES[strtoupper(trim((string) ($r['tipe'] ?? '')))] ?? null;
        if (! $type) {
            $errors[] = 'Tipe tidak dikenal (PG/PGK/BS/JODOH/ISIAN/URAIAN)';
        }
        $text = trim((string) ($r['soal'] ?? ''));
        if ($text === '' && empty($r['gambar_soal'])) {
            $errors[] = 'Teks soal kosong';
        }

        $options = [];
        foreach (self::LETTERS as $l) {
            $t = trim((string) ($r['opsi_' . $l] ?? ''));
            $img = trim((string) ($r['gambar_' . $l] ?? ''));
            if ($t !== '' || $img !== '') {
                $options[strtoupper($l)] = ['text' => $t, 'image' => $img];
            }
        }

        $images = array_filter(array_merge([(string) ($r['gambar_soal'] ?? '')], array_column($options, 'image')));
        foreach ($images as $img) {
            if (! self::safeName($img)) {
                $errors[] = "Nama gambar \"{$img}\" tidak valid";
            } elseif (! isset($zipNames[strtolower($img)])) {
                $errors[] = "Gambar \"{$img}\" tidak ada di ZIP";
            }
        }

        $stim = strtoupper(trim((string) ($r['stimulus_kode'] ?? '')));
        if ($stim !== '' && ! isset($stimuli[$stim])) {
            $errors[] = "Stimulus {$stim} tidak ada di sheet stimulus";
        }

        $keyRaw = trim((string) ($r['kunci'] ?? ''));
        $key = null;
        if ($type) {
            [$key, $keyErr] = $this->parseKey($type, $keyRaw, $options, $text);
            if ($keyErr) {
                $errors[] = $keyErr;
            }
        }
        if (in_array($type, ['pg', 'pgk'], true) && count($options) < 2) {
            $errors[] = 'Minimal 2 opsi';
        }

        $weight = $r['bobot'] ?? 1;
        if (! is_numeric($weight) || (float) $weight <= 0) {
            $warnings[] = 'Bobot tidak valid → 1';
            $weight = 1;
        }

        return [
            'line' => $line,
            'data' => [
                'no' => (string) ($r['no'] ?? ''), 'type' => $type, 'text' => $text, 'image' => (string) ($r['gambar_soal'] ?? ''),
                'options' => $options, 'key' => $key, 'stimulus' => $stim ?: null, 'weight' => (float) $weight,
                'difficulty' => in_array(strtolower((string) ($r['kesulitan'] ?? '')), ['mudah', 'sedang', 'sulit'], true) ? strtolower((string) $r['kesulitan']) : null,
                'level' => strtoupper(trim((string) ($r['level'] ?? ''))) ?: null,
                'explanation' => (string) ($r['pembahasan'] ?? ''),
                'numeric' => $type === 'isian' && $key && count(array_filter((array) $key, fn ($k) => ! preg_match('/^-?[\d.,]+$/', (string) $k))) === 0,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @return array{0:mixed,1:?string} */
    public function parseKey(string $type, string $raw, array $options, string $text = ''): array
    {
        $up = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
        switch ($type) {
            case 'pg':
                return isset($options[$up]) ? [$up, null] : [null, 'Kunci PG harus salah satu huruf opsi'];
            case 'pgk':
                $keys = array_values(array_unique(array_filter(explode(',', $up))));
                foreach ($keys as $k) {
                    if (! isset($options[$k])) {
                        return [null, "Kunci PGK \"{$k}\" tidak ada di opsi"];
                    }
                }

                return count($keys) ? [$keys, null] : [null, 'Kunci PGK kosong'];
            case 'bs':
                $vals = array_filter(explode(',', $up), fn ($v) => $v !== '');
                if (count($vals) !== count($options) || array_diff($vals, ['B', 'S'])) {
                    return [null, 'Kunci BS harus B/S sebanyak pernyataan (mis. B,S,B)'];
                }
                $out = [];
                foreach (array_values($vals) as $i => $v) {
                    $out[(string) ($i + 1)] = $v === 'B';
                }

                return [$out, null];
            case 'jodoh':
                $out = [];
                foreach (array_filter(explode(',', $up)) as $pair) {
                    if (! preg_match('/^(\d+)-([A-E])$/', $pair, $m) || ! isset($options[$m[2]])) {
                        return [null, "Pasangan \"{$pair}\" tidak valid (format 1-C)"];
                    }
                    $out[$m[1]] = strtolower($m[2]);
                }

                return $out ? [$out, null] : [null, 'Kunci menjodohkan kosong'];
            case 'isian':
                $vars = array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($v) => $v !== ''));

                return $vars ? [$vars, null] : [null, 'Kunci isian kosong'];
            case 'uraian':
                return [null, null];
        }

        return [null, 'Tipe tidak dikenal'];
    }

    /**
     * Simpan baris valid ke bank. @param list<array> $rows
     * @return int jumlah soal tersimpan
     */
    public function import(QuestionBank $bank, array $rows, array $stimuli, ?string $zipPath = null): int
    {
        $zip = null;
        if ($zipPath) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $zip = null;
            }
        }
        $count = 0;
        try {
            DB::transaction(function () use ($bank, $rows, $stimuli, $zip, &$count) {
                $stimIds = [];
                $pos = (int) Question::query()->where('question_bank_id', $bank->id)->max('position');
                foreach ($rows as $row) {
                    if ($row['errors']) {
                        continue;
                    }
                    $d = $row['data'];
                    $stimId = null;
                    if ($d['stimulus']) {
                        $stimId = $stimIds[$d['stimulus']] ??= Stimulus::query()->create([
                            'question_bank_id' => $bank->id,
                            'title' => $stimuli[$d['stimulus']]['title'] ?: $d['stimulus'],
                            'content' => HtmlCleaner::fromPlain($stimuli[$d['stimulus']]['text']),
                            'image' => $this->image($zip, $stimuli[$d['stimulus']]['image'] ?? ''),
                        ])->id;
                    }
                    $content = HtmlCleaner::fromPlain($d['text']);
                    $q = Question::query()->create([
                        'question_bank_id' => $bank->id,
                        'stimulus_id' => $stimId,
                        'type' => $d['type'],
                        'content' => $content,
                        'image' => $this->image($zip, $d['image']),
                        'answer_key' => $d['key'],
                        'weight' => $d['weight'],
                        'difficulty' => $d['difficulty'],
                        'cognitive_level' => $d['level'],
                        'explanation' => $d['explanation'] !== '' ? HtmlCleaner::fromPlain($d['explanation']) : null,
                        'numeric' => (bool) $d['numeric'],
                        'position' => ++$pos,
                    ]);
                    $this->createOptions($q, $d, $zip);
                    $count++;
                }
            });
        } finally {
            $zip?->close();
        }

        return $count;
    }

    private function createOptions(Question $q, array $d, ?ZipArchive $zip): void
    {
        $i = 0;
        if (in_array($d['type'], ['pg', 'pgk'], true)) {
            foreach ($d['options'] as $letter => $o) {
                $q->options()->create(['key' => $letter, 'content' => HtmlCleaner::fromPlain($o['text']), 'image' => $this->image($zip, $o['image']), 'position' => $i++]);
            }
        } elseif ($d['type'] === 'bs') {
            foreach (array_values($d['options']) as $n => $o) {
                $q->options()->create(['key' => (string) ($n + 1), 'content' => HtmlCleaner::fromPlain($o['text']), 'image' => $this->image($zip, $o['image']), 'position' => $i++]);
            }
        } elseif ($d['type'] === 'jodoh') {
            // Kiri: dari kunci (1,2,3); label diambil dari pola "(1) Sinonim (2) Antonim" di teks soal bila ada.
            preg_match_all('/\((\d+)\)\s*([^()]+?)(?=\s*\(\d+\)|$)/u', $d['text'], $m, PREG_SET_ORDER);
            $labels = [];
            foreach ($m as $x) {
                $labels[$x[1]] = trim($x[2]);
            }
            foreach (array_keys((array) $d['key']) as $left) {
                $q->options()->create(['key' => (string) $left, 'side' => 'left', 'content' => e($labels[$left] ?? ('Pernyataan ' . $left)), 'position' => $i++]);
            }
            foreach ($d['options'] as $letter => $o) {
                $q->options()->create(['key' => strtolower($letter), 'side' => 'right', 'content' => HtmlCleaner::fromPlain($o['text']), 'image' => $this->image($zip, $o['image']), 'position' => $i++]);
            }
        }
    }

    private function image(?ZipArchive $zip, string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || ! $zip || ! self::safeName($name)) {
            return null;
        }
        $key = strtolower($name);
        if (isset($this->stored[$key])) {
            return $this->stored[$key];
        }
        $idx = $zip->locateName($name, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
        if ($idx === false) {
            return null;
        }
        $st = $zip->statIndex($idx);
        if (! self::safeName((string) $st['name']) || (int) $st['size'] > ImageOptimizer::MAX_BYTES) {
            return null;
        }
        $bytes = $zip->getFromIndex($idx);

        return $this->stored[$key] = $bytes !== false ? $this->images->storeFromString($bytes) : null;
    }
}
