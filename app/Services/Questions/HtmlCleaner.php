<?php

namespace App\Services\Questions;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitasi HTML soal (TipTap/Excel). Hanya tag format dasar + gambar dari /admin/media/ (penyaji privat).
 * Skrip, atribut event, iframe, style, dan URL eksternal dibuang.
 */
final class HtmlCleaner
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')->allowElement('em')
            ->allowElement('i')->allowElement('u')->allowElement('s')->allowElement('sub')->allowElement('sup')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')->allowElement('code')
            ->allowElement('pre')->allowElement('h3')->allowElement('h4')->allowElement('hr')
            ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('img', ['src', 'alt'])
            ->allowRelativeMedias()
            ->allowMediaSchemes([])
            ->withMaxInputLength(200_000);
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $out = $this->sanitizer->sanitize($html);
        // Gambar hanya dari penyaji media privat.
        $out = preg_replace_callback('#<img\b[^>]*>#i', function ($m) {
            return preg_match('#src="/admin/media/[A-Za-z0-9_/.\-]+"#', $m[0]) ? $m[0] : '';
        }, $out) ?? $out;

        return trim($out) === '<p></p>' ? '' : trim($out);
    }

    /** Teks polos dari Excel → paragraf HTML aman. */
    public static function fromPlain(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $paras = preg_split("/\R{2,}/", $text) ?: [$text];

        return implode('', array_map(fn ($p) => '<p>' . nl2br(e(trim($p)), false) . '</p>', $paras));
    }
}
