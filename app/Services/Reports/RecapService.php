<?php

namespace App\Services\Reports;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\ShieldEvent;
use Illuminate\Support\Collection;

/** Rekap nilai, analisis butir (kesukaran, daya beda, pengecoh), rekap pelanggaran. */
class RecapService
{
    /** @return Collection<int,array> baris rekap urut nilai tertinggi */
    public function recap(ExamSchedule $schedule): Collection
    {
        $schedule->loadMissing('exam');
        $students = $schedule->participantsQuery()->with('classroom:id,name')->orderBy('name')->get();
        $attempts = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->get()->keyBy('student_id');
        $violations = ShieldEvent::query()->whereIn('exam_attempt_id', $attempts->pluck('id')->all() ?: [0])
            ->whereIn('action', ['warn', 'lock'])->where('merged', false)->selectRaw('exam_attempt_id, COUNT(*) as n')
            ->groupBy('exam_attempt_id')->pluck('n', 'exam_attempt_id');
        $kkm = (int) $schedule->exam->kkm;

        return $students->map(function ($st) use ($attempts, $violations, $kkm) {
            $a = $attempts[$st->id] ?? null;
            $d = $a?->score_detail ?? [];

            return [
                'student_id' => $st->id,
                'attempt_id' => $a?->id,
                'nisn' => $st->nisn,
                'nis' => $st->nis,
                'name' => $st->name,
                'class' => $st->classroom?->name,
                'status' => $a ? $a->status : 'not_started',
                'present' => $a && $a->started_at !== null,
                'started_at' => $a?->started_at,
                'submitted_at' => $a?->submitted_at,
                'correct' => $d['correct'] ?? null,
                'wrong' => $d['wrong'] ?? null,
                'blank' => $d['blank'] ?? null,
                'score' => $a && $a->isDone() ? (float) $a->score : null,
                'needs_grading' => (bool) ($a->needs_grading ?? false),
                'passed' => $a && $a->isDone() && ! $a->needs_grading ? $a->score >= $kkm : null,
                'violations' => (int) ($violations[$a->id ?? 0] ?? 0),
                'warnings' => (int) ($a->warning_count ?? 0),
            ];
        })->sortByDesc(fn ($r) => $r['score'] ?? -1)->values();
    }

    /** @return array{count:int,avg:?float,max:?float,min:?float,pass_pct:?float,histogram:array<string,int>} */
    public function summary(Collection $rows): array
    {
        $scores = $rows->pluck('score')->filter(fn ($s) => $s !== null);
        $hist = ['0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0];
        foreach ($scores as $s) {
            $k = match (true) { $s <= 20 => '0-20', $s <= 40 => '21-40', $s <= 60 => '41-60', $s <= 80 => '61-80', default => '81-100' };
            $hist[$k]++;
        }
        $graded = $rows->filter(fn ($r) => $r['passed'] !== null);

        return [
            'count' => $scores->count(),
            'avg' => $scores->count() ? round($scores->avg(), 2) : null,
            'max' => $scores->count() ? $scores->max() : null,
            'min' => $scores->count() ? $scores->min() : null,
            'pass_pct' => $graded->count() ? round($graded->where('passed', true)->count() / $graded->count() * 100, 1) : null,
            'histogram' => $hist,
        ];
    }

    /**
     * Analisis butir klasik: p (kesukaran) = proporsi benar; D (daya beda) = p_atas − p_bawah (27% kelompok);
     * pengecoh: proporsi pemilih tiap opsi (PG). @return list<array>
     */
    public function itemAnalysis(ExamSchedule $schedule): array
    {
        $attempts = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->whereIn('status', ExamAttempt::DONE_STATUSES)->get(['id', 'score']);
        $n = $attempts->count();
        if (! $n) {
            return [];
        }
        $sorted = $attempts->sortByDesc('score')->values();
        $g = max(1, (int) round($n * 0.27));
        $upper = $sorted->take($g)->pluck('id')->all();
        $lower = $sorted->reverse()->take($g)->pluck('id')->all();

        $answers = AttemptAnswer::query()->whereIn('exam_attempt_id', $attempts->pluck('id'))->get(['exam_attempt_id', 'question_id', 'answer', 'is_correct', 'score'])->groupBy('question_id');
        $questions = Question::query()->whereIn('id', $answers->keys())->with('options')->get()->keyBy('id');
        $out = [];
        $no = 0;
        foreach ($questions as $qid => $q) {
            $no++;
            $rows = $answers[$qid];
            $correct = $rows->where('is_correct', true);
            $p = $rows->count() ? $correct->count() / $n : 0;
            $pu = count($upper) ? $correct->whereIn('exam_attempt_id', $upper)->count() / count($upper) : 0;
            $pl = count($lower) ? $correct->whereIn('exam_attempt_id', $lower)->count() / count($lower) : 0;
            $distractors = [];
            if ($q->type === 'pg') {
                foreach ($q->options as $o) {
                    $chosen = $rows->filter(fn ($r) => $r->answer === $o->key)->count();
                    $distractors[$o->key] = ['pct' => round($chosen / $n * 100, 1), 'key' => $o->key === $q->answer_key];
                }
                $distractors['kosong'] = ['pct' => round(($n - $rows->whereNotNull('answer')->count()) / $n * 100, 1), 'key' => false];
            }
            $d = round($pu - $pl, 2);
            $out[] = [
                'id' => $qid,
                'no' => $no,
                'type' => $q->type,
                'text' => $q->plainText(90),
                'p' => round($p, 2),
                'difficulty_label' => $p >= 0.7 ? 'Mudah' : ($p >= 0.3 ? 'Sedang' : 'Sulit'),
                'd' => $d,
                'discrimination_label' => $d >= 0.4 ? 'Sangat baik' : ($d >= 0.3 ? 'Baik' : ($d >= 0.2 ? 'Cukup' : 'Perlu revisi')),
                'distractors' => $distractors,
                'essay' => $q->type === 'uraian',
            ];
        }

        return $out;
    }

    /** Rekap pelanggaran per jenis & per jam. */
    public function violations(?ExamSchedule $schedule, ?string $date = null): array
    {
        $q = ShieldEvent::query()->whereIn('action', ['log', 'warn', 'lock']);
        if ($schedule) {
            $q->whereIn('exam_attempt_id', ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->pluck('id'));
        } else {
            $q->whereDate('received_at', $date ?? today());
        }
        $events = $q->get(['id', 'exam_attempt_id', 'type', 'action', 'merged', 'received_at']);
        $byType = $events->groupBy('type')->map(fn ($g, $t) => ['type' => $t, 'label' => ShieldEvent::label($t), 'total' => $g->count(), 'warn' => $g->where('action', 'warn')->count(), 'lock' => $g->where('action', 'lock')->count()])->sortByDesc('total')->values()->all();
        $byHour = $events->groupBy(fn ($e) => $e->received_at->format('H'))->map->count()->sortKeys()->all();
        $byAttempt = $events->groupBy('exam_attempt_id')->map(fn ($g) => ['total' => $g->count(), 'warn' => $g->where('action', 'warn')->count(), 'lock' => $g->where('action', 'lock')->count()]);

        return ['byType' => $byType, 'byHour' => $byHour, 'byAttempt' => $byAttempt, 'total' => $events->count()];
    }
}
