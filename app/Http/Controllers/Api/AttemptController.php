<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Services\AttemptService;
use App\Services\QuestionPresenter;
use App\Services\Shield\ShieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * API ruang ujian (Spesifikasi Bagian 7). Semua rute: auth:student, student.session, security.headers:exam,
 * throttle, exam.location, dan pengecekan kepemilikan attempt. Respons JSON ringkas, Cache-Control: no-store.
 */
class AttemptController extends Controller
{
    public function __construct(
        private AttemptService $attempts,
        private QuestionPresenter $presenter,
        private ShieldService $shield,
    ) {
    }

    private function owned(Request $request, ExamAttempt $attempt): ExamAttempt
    {
        abort_unless($attempt->student_id === $request->user('student')?->id, 404);

        return $attempt;
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store, private']);
    }

    /** Waktu habis → kumpulkan otomatis lalu kembalikan true. */
    private function expire(ExamAttempt $attempt): bool
    {
        if (! $attempt->isDone() && $attempt->deadline_at && $attempt->deadline_at->isPast() && $attempt->schedule->status !== 'paused') {
            $this->attempts->submit($attempt, false);

            return true;
        }

        return $attempt->isDone();
    }

    public function question(Request $request, ExamAttempt $attempt, int $no): JsonResponse
    {
        $this->owned($request, $attempt);
        if ($this->expire($attempt)) {
            return $this->json(['message' => 'Waktu habis / sudah dikumpulkan.', 'state' => ['status' => 'done']], 423);
        }
        if ($attempt->isLocked()) {
            return $this->json(['message' => 'Ujian terkunci.', 'state' => $this->shield->state($attempt)], 423);
        }
        $q = $this->presenter->present($attempt, $no);
        abort_if($q === null, 404);

        return $this->json($q);
    }

    public function map(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->owned($request, $attempt);
        $order = array_map('intval', (array) $attempt->question_order);
        $ans = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get(['question_id', 'answer', 'is_doubtful'])->keyBy('question_id');
        $items = [];
        foreach ($order as $i => $qid) {
            $a = $ans[$qid] ?? null;
            $items[] = ['no' => $i + 1, 'answered' => $a !== null && $a->answer !== null && $a->answer !== [], 'flagged' => (bool) ($a->is_doubtful ?? false)];
        }

        return $this->json(['items' => $items]);
    }

    public function answers(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->owned($request, $attempt);
        $data = $request->validate([
            'answers' => ['required', 'array', 'max:50'],
            'answers.*.question_id' => ['required'],
            'answers.*.rev' => ['required', 'integer', 'min:0'],
            'answers.*.flagged' => ['nullable', 'boolean'],
        ]);
        if ($this->expire($attempt)) {
            return $this->json(['message' => 'Waktu habis / sudah dikumpulkan.', 'saved' => []], 423);
        }
        if ($attempt->isLocked()) {
            return $this->json(['message' => 'Ujian terkunci.', 'saved' => []], 423);
        }
        $raw = (array) $request->input('answers');

        return $this->json(['saved' => $this->attempts->saveAnswers($attempt, $raw)]);
    }

    public function events(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->owned($request, $attempt);
        // sendBeacon mengirim JSON sebagai Blob; Laravel tetap mem-parse bila Content-Type JSON.
        $request->validate([
            'instance_id' => ['required', 'string', 'max:64'],
            'client_now' => ['nullable', 'integer'],
            'events' => ['present', 'array', 'max:50'],
            'events.*.seq' => ['required', 'integer', 'min:1'],
            'events.*.type' => ['required', 'string', 'max:40'],
            'events.*.at' => ['nullable', 'integer'],
            'events.*.detail' => ['nullable', 'array'],
        ]);
        $res = $this->shield->processEvents(
            $attempt,
            (string) $request->input('instance_id'),
            $request->filled('client_now') ? (int) $request->input('client_now') : null,
            (array) $request->input('events', []),
            $request,
        );

        return $this->json($res);
    }

    public function heartbeat(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->owned($request, $attempt);
        $p = $request->validate([
            'instance_id' => ['required', 'string', 'max:64'],
            'nonce' => ['nullable', 'string', 'max:40'],
            'visible' => ['nullable', 'boolean'],
            'focused' => ['nullable', 'boolean'],
            'fullscreen' => ['nullable', 'boolean'],
            'online' => ['nullable', 'boolean'],
            'vw' => ['nullable', 'integer'], 'vh' => ['nullable', 'integer'], 'sw' => ['nullable', 'integer'], 'sh' => ['nullable', 'integer'],
            'blockers' => ['nullable', 'array'],
            'pending_events' => ['nullable', 'integer'],
        ]);

        return $this->json($this->shield->heartbeat($attempt, $p, $request));
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->owned($request, $attempt);
        $exam = $attempt->schedule->exam;
        $min = (int) $exam->setting('min_minutes_before_submit', 0) * 60;
        if (! $attempt->isDone() && $min > 0 && $attempt->started_at && $attempt->started_at->diffInSeconds(now()) < $min
            && $attempt->deadline_at && $attempt->deadline_at->isFuture()) {
            return $this->json(['message' => 'Belum boleh dikumpulkan. Minimal ' . ($min / 60) . ' menit setelah mulai.'], 422);
        }
        if ($attempt->isLocked()) {
            return $this->json(['message' => 'Ujian terkunci. Hubungi pengawas.'], 423);
        }
        $this->attempts->submit($attempt, false);

        return $this->json(['redirect' => route('student.exam.result', $attempt->exam_schedule_id)]);
    }

    /** Gambar soal bertanda tangan (30 menit) — hanya untuk pemilik attempt yang belum selesai. */
    public function media(Request $request, ExamAttempt $attempt, string $path)
    {
        $this->owned($request, $attempt);
        abort_if(str_contains($path, '..') || $attempt->isDone(), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists('media/' . $path), 404);

        return response()->file($disk->path('media/' . $path), ['Cache-Control' => 'private, max-age=1800']);
    }
}
