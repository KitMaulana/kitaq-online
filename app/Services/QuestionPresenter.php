<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\URL;

/**
 * Menyusun soal untuk browser siswa — TANPA kunci jawaban (answer_key) dan tanpa pembahasan.
 * Opsi memakai kunci TAMPILAN acak per siswa; gambar lewat URL bertanda tangan 30 menit.
 */
class QuestionPresenter
{
    public function __construct(private AttemptService $attempts)
    {
    }

    /** @return array<string,mixed>|null */
    public function present(ExamAttempt $attempt, int $no): ?array
    {
        $order = array_map('intval', (array) $attempt->question_order);
        if ($no < 1 || $no > count($order)) {
            return null;
        }
        $qid = $order[$no - 1];
        /** @var Question|null $q */
        $q = Question::query()->with(['options', 'stimulus'])->find($qid);
        if (! $q) {
            return null;
        }
        $map = ((array) $attempt->option_order)[(string) $qid] ?? [];
        $ans = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->where('question_id', $qid)->first(['answer', 'is_doubtful', 'rev']);

        $out = [
            'id' => $q->id,
            'no' => $no,
            'type' => $q->type,
            'text' => $this->plain($q->content),
            'html' => $this->html($attempt, $q->content),
            'stimulus' => $q->stimulus ? $this->plain($q->stimulus->content) : null,
            'stimulus_html' => $q->stimulus ? $this->html($attempt, $q->stimulus->content) : null,
            'stimulus_title' => $q->stimulus?->title,
            'image' => $q->image ? $this->mediaUrl($attempt, $q->image) : null,
            'stimulus_image' => $q->stimulus?->image ? $this->mediaUrl($attempt, $q->stimulus->image) : null,
            'audio' => $q->audio ? $this->mediaUrl($attempt, $q->audio) : null,
            'audio_limit' => $q->audio ? (int) $q->audio_limit : null,
            'numeric' => (bool) $q->numeric,
            'max_length' => $q->maxLength(),
            'options' => [],
            'answer' => $ans ? $this->attempts->toDisplay($q, $ans->answer, $map) : null,
            'flagged' => (bool) ($ans->is_doubtful ?? false),
            'rev' => (int) ($ans->rev ?? 0),
        ];

        $byKey = $q->options->keyBy('key');
        if (in_array($q->type, ['pg', 'pgk', 'bs'], true)) {
            foreach ($map as $display => $orig) {
                $o = $byKey[$orig] ?? null;
                if (! $o) {
                    continue;
                }
                $out['options'][] = $this->option($attempt, (string) $display, $o->content, $o->image);
            }
        } elseif ($q->type === 'jodoh') {
            $out['left'] = [];
            $out['right'] = [];
            $left = $q->options->where('side', 'left')->keyBy('key');
            $right = $q->options->where('side', 'right')->keyBy('key');
            foreach ($map['L'] ?? [] as $display => $orig) {
                if ($o = $left[$orig] ?? null) {
                    $out['left'][] = $this->option($attempt, (string) $display, $o->content, $o->image);
                }
            }
            foreach ($map['R'] ?? [] as $display => $orig) {
                if ($o = $right[$orig] ?? null) {
                    $out['right'][] = $this->option($attempt, (string) $display, $o->content, $o->image);
                }
            }
        }

        return $out;
    }

    private function option(ExamAttempt $attempt, string $key, ?string $content, ?string $image): array
    {
        return [
            'key' => $key,
            'label' => strtoupper($key),
            'text' => $this->plain((string) $content),
            'html' => $this->html($attempt, (string) $content),
            'image' => $image ? $this->mediaUrl($attempt, $image) : null,
        ];
    }

    public function plain(?string $html): string
    {
        $s = (string) $html;
        $s = preg_replace('/<\s*(br|\/p|\/li|\/h\d|\/div)\s*\/?>/i', "\n", $s) ?? $s;
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $s) ?? $s);
    }

    /** HTML tersanitasi (saat disimpan) + src gambar ditulis ulang ke URL bertanda tangan. */
    public function html(ExamAttempt $attempt, ?string $html): string
    {
        return (string) preg_replace_callback('#src="/admin/media/([^"]+)"#', function ($m) use ($attempt) {
            return 'src="' . e($this->mediaUrl($attempt, 'media/' . html_entity_decode($m[1]))) . '"';
        }, (string) $html);
    }

    public function mediaUrl(ExamAttempt $attempt, string $path): string
    {
        $path = preg_replace('#^media/#', '', ltrim($path, '/')) ?? $path;

        return URL::temporarySignedRoute('media.signed', now()->addMinutes(30), ['attempt' => $attempt->id, 'path' => $path]);
    }
}
