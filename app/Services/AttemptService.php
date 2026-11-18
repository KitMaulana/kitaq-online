<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Siklus hidup percobaan ujian: mulai/lanjut (urutan soal & opsi acak per siswa), simpan jawaban
 * idempoten dengan nomor revisi, kumpulkan (manual/otomatis/paksa).
 *
 * option_order (TIDAK dikirim ke browser) memetakan kunci tampilan → kunci asli:
 *   pg/pgk : {"A":"C","B":"A",...}           bs : {"1":"3","2":"1",...}
 *   jodoh  : {"L":{"1":"2",...},"R":{"a":"c",...}}
 */
class AttemptService
{
    public function __construct(private ScoringService $scoring)
    {
    }

    public function findOrCreate(ExamSchedule $schedule, Student $student): ExamAttempt
    {
        return ExamAttempt::query()->firstOrCreate(
            ['exam_schedule_id' => $schedule->id, 'student_id' => $student->id],
            ['status' => 'not_started', 'shield_status' => 'active']
        );
    }

    /** Mulai (atau lanjutkan) percobaan. Dipanggil setelah token terverifikasi. */
    public function start(ExamSchedule $schedule, Student $student, ?string $ip = null, ?string $ua = null): ExamAttempt
    {
        return DB::transaction(function () use ($schedule, $student, $ip, $ua) {
            $attempt = $this->findOrCreate($schedule, $student);
            $attempt = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($attempt->started_at === null) {
                $exam = $schedule->exam;
                [$order, $options, $package] = $this->buildOrder($schedule, $student);
                $now = now();
                $extra = (int) $student->accommodation('extra_minutes', 0);
                $attempt->fill([
                    'status' => 'in_progress',
                    'shield_status' => 'active',
                    'package' => $package,
                    'question_order' => $order,
                    'option_order' => $options,
                    'started_at' => $now,
                    'extra_minutes' => $extra,
                    'ip_address' => $ip,
                    'user_agent' => $ua ? substr($ua, 0, 255) : null,
                ]);
                $attempt->deadline_at = $this->capDeadline($attempt, $now->copy()->addMinutes($exam->duration_minutes + $extra));
                $attempt->save();
                ActivityLog::record('attempt_started', 'Memulai ujian ' . $exam->name, $attempt, [], $student->id);
            } else {
                $attempt->forceFill(['ip_address' => $ip])->save();
            }

            return $attempt;
        });
    }

    /** deadline = started + durasi + tambahan, dibatasi closes_at jadwal. */
    public function capDeadline(ExamAttempt $attempt, Carbon $deadline): Carbon
    {
        $closes = $attempt->schedule->closes_at;

        return $closes && $deadline->gt($closes) ? $closes->copy() : $deadline;
    }

    /** @return array{0:list<int>,1:array<string,array>,2:?string} */
    public function buildOrder(ExamSchedule $schedule, Student $student): array
    {
        $exam = $schedule->exam;
        $s = $exam->resolvedSettings();
        $query = Question::query()->where('question_bank_id', $exam->question_bank_id)->orderBy('position')->orderBy('id');

        $package = null;
        $packages = array_values(array_filter((array) $s['packages']));
        if ($packages) {
            $package = $packages[$student->id % count($packages)];
            $query->where(fn ($q) => $q->where('package', $package)->orWhereNull('package'));
        }

        $questions = $query->with('options')->get(['id', 'type', 'stimulus_id', 'position']);
        $ids = $questions->pluck('id')->all();
        $count = $exam->question_count > 0 ? min($exam->question_count, count($ids)) : count($ids);

        if ($s['shuffle_questions']) {
            // Soal berstimulus yang sama tetap berdekatan (diacak per kelompok).
            $groups = $questions->groupBy(fn ($q) => $q->stimulus_id ? 's' . $q->stimulus_id : 'q' . $q->id)->map(fn ($g) => $g->pluck('id')->all())->values()->all();
            shuffle($groups);
            $ids = array_merge(...($groups ?: [[]]));
        }
        $ids = array_slice($ids, 0, $count);

        $options = [];
        $byId = $questions->keyBy('id');
        foreach ($ids as $id) {
            $q = $byId[$id];
            $map = $this->optionMap($q, (bool) $s['shuffle_options']);
            if ($map) {
                $options[(string) $id] = $map;
            }
        }

        return [array_map('intval', $ids), $options, $package];
    }

    /** @return array<string,mixed> */
    private function optionMap(Question $q, bool $shuffle): array
    {
        $opts = $q->options;
        if (in_array($q->type, ['pg', 'pgk', 'bs'], true)) {
            $keys = $opts->pluck('key')->all();
            if ($shuffle) {
                shuffle($keys);
            }
            $display = $q->type === 'bs' ? range(1, max(1, count($keys))) : array_slice(range('A', 'Z'), 0, count($keys));
            $map = [];
            foreach ($keys as $i => $orig) {
                $map[(string) $display[$i]] = (string) $orig;
            }

            return $map;
        }
        if ($q->type === 'jodoh') {
            $left = $opts->where('side', 'left')->pluck('key')->all();
            $right = $opts->where('side', 'right')->pluck('key')->all();
            if ($shuffle) {
                shuffle($left);
                shuffle($right);
            }
            $L = [];
            foreach ($left as $i => $orig) {
                $L[(string) ($i + 1)] = (string) $orig;
            }
            $R = [];
            foreach ($right as $i => $orig) {
                $R[chr(97 + $i)] = (string) $orig;
            }

            return ['L' => $L, 'R' => $R];
        }

        return [];
    }

    /**
     * Simpan jawaban berkelompok. Revisi lama (rev <= tersimpan) diabaikan namun tetap di-ack.
     * @param list<array{question_id:int|string,answer:mixed,flagged?:bool,rev:int}> $answers
     * @return list<array{question_id:int,rev:int}>
     */
    public function saveAnswers(ExamAttempt $attempt, array $answers): array
    {
        $order = array_map('intval', (array) $attempt->question_order);
        $maps = (array) $attempt->option_order;
        $types = Question::query()->whereIn('id', $order)->get(['id', 'type', 'max_length'])->keyBy('id');
        $existing = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)
            ->whereIn('question_id', array_map(fn ($a) => (int) ($a['question_id'] ?? 0), $answers))->get()->keyBy('question_id');
        $saved = [];
        $elapsed = $attempt->started_at ? (int) $attempt->started_at->diffInSeconds(now()) : null;

        foreach ($answers as $a) {
            $qid = (int) ($a['question_id'] ?? 0);
            $rev = max(0, (int) ($a['rev'] ?? 0));
            if (! in_array($qid, $order, true) || ! isset($types[$qid])) {
                continue;
            }
            $row = $existing[$qid] ?? null;
            if ($row && $row->rev >= $rev) {
                $saved[] = ['question_id' => $qid, 'rev' => $row->rev];
                continue;
            }
            $value = $this->toOriginal($types[$qid], $a['answer'] ?? null, $maps[(string) $qid] ?? []);
            $empty = $value === null || $value === '' || $value === [];
            $data = [
                'answer' => $empty ? null : $value,
                'rev' => $rev,
                'is_doubtful' => (bool) ($a['flagged'] ?? false),
                'answered_at' => now(),
            ];
            if ($row) {
                if ($row->first_answered_sec === null && ! $empty) {
                    $data['first_answered_sec'] = $elapsed;
                }
                $row->forceFill($data)->save();
            } else {
                $row = AttemptAnswer::query()->create($data + ['exam_attempt_id' => $attempt->id, 'question_id' => $qid, 'first_answered_sec' => $empty ? null : $elapsed]);
                $existing[$qid] = $row;
            }
            $saved[] = ['question_id' => $qid, 'rev' => $rev];
        }

        return $saved;
    }

    /** Ubah jawaban berkunci tampilan menjadi kunci asli + validasi bentuk. */
    public function toOriginal(Question $q, mixed $answer, array $map): mixed
    {
        switch ($q->type) {
            case 'pg':
                return is_string($answer) && isset($map[$answer]) ? $map[$answer] : null;
            case 'pgk':
                $out = [];
                foreach ((array) $answer as $k) {
                    if (is_string($k) && isset($map[$k])) {
                        $out[] = $map[$k];
                    }
                }
                sort($out);

                return array_values(array_unique($out));
            case 'bs':
                $out = [];
                foreach ((array) $answer as $k => $v) {
                    if (isset($map[(string) $k]) && is_bool($v)) {
                        $out[$map[(string) $k]] = $v;
                    }
                }

                return $out ?: null;
            case 'jodoh':
                $out = [];
                foreach ((array) $answer as $l => $r) {
                    if (isset($map['L'][(string) $l], $map['R'][(string) $r])) {
                        $out[$map['L'][(string) $l]] = $map['R'][(string) $r];
                    }
                }

                return $out ?: null;
            case 'isian':
            case 'uraian':
                if (! is_string($answer)) {
                    return null;
                }

                return mb_substr($answer, 0, $q->max_length ?: ($q->type === 'uraian' ? 3000 : 120));
        }

        return null;
    }

    /** Kunci asli → kunci tampilan (untuk mengirim jawaban tersimpan ke klien). */
    public function toDisplay(Question $q, mixed $answer, array $map): mixed
    {
        if ($answer === null) {
            return null;
        }
        switch ($q->type) {
            case 'pg':
                $f = array_search($answer, $map, true);

                return $f === false ? null : (string) $f;
            case 'pgk':
                $flip = array_flip($map);

                return array_values(array_filter(array_map(fn ($k) => isset($flip[$k]) ? (string) $flip[$k] : null, (array) $answer)));
            case 'bs':
                $flip = array_flip($map);
                $out = [];
                foreach ((array) $answer as $k => $v) {
                    if (isset($flip[(string) $k])) {
                        $out[(string) $flip[(string) $k]] = (bool) $v;
                    }
                }

                return (object) $out;
            case 'jodoh':
                $fl = array_flip($map['L'] ?? []);
                $fr = array_flip($map['R'] ?? []);
                $out = [];
                foreach ((array) $answer as $l => $r) {
                    if (isset($fl[(string) $l], $fr[(string) $r])) {
                        $out[(string) $fl[(string) $l]] = (string) $fr[(string) $r];
                    }
                }

                return (object) $out;
            default:
                return is_string($answer) ? $answer : null;
        }
    }

    /** Kumpulkan: manual siswa, otomatis (waktu habis), atau paksa oleh pengawas. */
    public function submit(ExamAttempt $attempt, bool $forced = false, ?User $by = null): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $forced, $by) {
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($locked->isDone()) {
                return $locked;
            }
            if ($locked->started_at === null) {
                $locked->started_at = now();
                $locked->question_order = $locked->question_order ?? [];
            }
            $locked->status = $forced ? 'force_submitted' : 'submitted';
            $locked->shield_status = 'done';
            $locked->submitted_at = now();
            $locked->active_instance_id = $locked->active_instance_id; // tetap untuk audit
            $locked->save();
            $this->scoring->score($locked);
            if ($forced) {
                ActivityLog::record('force_submit', "Kumpulkan paksa ujian {$locked->student->name}", $locked, ['by' => $by?->id]);
            }
            $attempt->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $locked;
        });
    }
}
