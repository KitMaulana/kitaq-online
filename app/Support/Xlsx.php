<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Baca/tulis XLSX streaming (OpenSpout) — hemat memori untuk shared hosting.
 * Sel bertipe tanggal dibaca sebagai DateTimeImmutable (cocok dengan StudentCredential::parseBirthDate).
 */
final class Xlsx
{
    /**
     * Baris pertama = judul kolom (dinormalisasi: huruf kecil, spasi → _).
     * Sheet bernama $sheet (mis. "siswa") bila ada, selain itu sheet pertama yang bukan PETUNJUK.
     * Baris contoh (kolom pertama diawali "#") diabaikan, sesuai petunjuk di template.
     * @return list<array<string,mixed>>
     */
    public static function readAssoc(string $path, ?string $sheet = null, int $maxRows = 20000): array
    {
        $options = new ReaderOptions();
        $options->SHOULD_FORMAT_DATES = false;
        $reader = new Reader($options);
        $reader->open($path);
        $out = [];
        try {
            $target = null;
            $fallback = null;
            foreach ($reader->getSheetIterator() as $sh) {
                $name = mb_strtolower(trim($sh->getName()));
                if ($sheet !== null && $name === mb_strtolower($sheet)) {
                    $target = $sh;
                    break;
                }
                if ($fallback === null && $name !== 'petunjuk') {
                    $fallback = $sh;
                }
            }
            foreach ([$target ?? $fallback] as $sheetObj) {
                if ($sheetObj === null) {
                    break;
                }
                $headers = null;
                foreach ($sheetObj->getRowIterator() as $row) {
                    $values = array_map(fn ($c) => $c->getValue(), $row->getCells());
                    if ($headers === null) {
                        $headers = array_map(fn ($h) => self::normalizeHeader((string) $h), $values);
                        continue;
                    }
                    $first = $values[0] ?? null;
                    if (is_string($first) && str_starts_with(trim($first), '#')) {
                        continue;
                    }
                    $assoc = [];
                    foreach ($headers as $i => $h) {
                        if ($h === '') {
                            continue;
                        }
                        $v = $values[$i] ?? null;
                        $assoc[$h] = is_string($v) ? trim($v) : $v;
                    }
                    $out[] = $assoc;
                    if (count($out) >= $maxRows) {
                        break;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $out;
    }

    public static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = preg_replace('/\*|\(.*?\)/', '', $h) ?? $h;   // "nisn *" / "tanggal_lahir (dd/mm/yyyy)"
        $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? $h;

        return trim($h, '_');
    }

    /**
     * Tulis daftar baris asosiatif ke XLSX sementara.
     * @param list<array<string,mixed>> $rows
     * @param list<string>|null $headers
     */
    public static function writeTemp(array $rows, ?array $headers = null, ?string $path = null): string
    {
        $path = $path ?? tempnam(sys_get_temp_dir(), 'kq') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $headers = $headers ?? array_keys($rows[0] ?? []);
        $bold = (new Style())->setFontBold();
        $writer->addRow(Row::fromValues($headers, $bold));
        foreach ($rows as $r) {
            $vals = [];
            foreach ($headers as $i => $h) {
                $v = array_key_exists($h, $r) ? $r[$h] : ($r[$i] ?? null);
                $vals[] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v);
            }
            $writer->addRow(Row::fromValues($vals));
        }
        $writer->close();

        return $path;
    }
}
