<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\Questions\HtmlCleaner;
use App\Services\Questions\ImageOptimizer;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Editor soal dua panel (DESIGN_SYSTEM 5.4): kiri form, kanan pratinjau tampilan siswa dalam bingkai HP.
 * Pintasan: Ctrl+S simpan, Ctrl+Enter simpan & berikutnya.
 */
#[Title('Editor Soal')]
class QuestionEditor extends Component
{
    use WithFileUploads, WithToast;

    public QuestionBank $bank;
    public ?Question $question = null;

    public string $type = 'pg';
    public ?string $stimulus_id = null;
    public string $content = '';
    public ?string $image = null;          // path media tersimpan
    public $imageUpload;
    public $editorUpload;
    public $audioUpload;
    public ?string $audio = null;
    public ?int $audio_limit = 2;

    /** pg/pgk/bs: [{key, content, image}] · jodoh: kiri & kanan terpisah */
    public array $options = [];
    public array $left = [];
    public array $right = [];
    public array $optionUploads = [];

    public string $keyPg = '';
    public array $keyPgk = [];
    public array $keyBs = [];
    public array $keyJodoh = [];
    public string $keyIsian = '';

    public float $weight = 1;
    public ?string $difficulty = 'sedang';
    public ?string $cognitive_level = null;
    public string $explanation = '';
    public bool $numeric = false;
    public ?int $max_length = null;
    public ?string $package = null;

    public function mount(QuestionBank $bank, ?Question $question = null): void
    {
        abort_unless($bank->canBeManagedBy(auth()->user()), 403);
        $this->bank = $bank;
        if ($question && $question->exists) {
            abort_unless($question->question_bank_id === $bank->id, 404);
            $this->question = $question;
            $this->load($question);
        } else {
            $this->resetOptions();
        }
    }

    private function load(Question $q): void
    {
        $q->load('options');
        $this->type = $q->type;
        $this->stimulus_id = $q->stimulus_id ? (string) $q->stimulus_id : null;
        $this->content = (string) $q->content;
        $this->image = $q->image;
        $this->audio = $q->audio;
        $this->audio_limit = $q->audio_limit;
        $this->weight = (float) $q->weight;
        $this->difficulty = $q->difficulty;
        $this->cognitive_level = $q->cognitive_level;
        $this->explanation = (string) $q->explanation;
        $this->numeric = (bool) $q->numeric;
        $this->max_length = $q->max_length;
        $this->package = $q->package;
        $plain = fn ($html) => trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p><p>'], ["\n", "\n"], (string) $html)), ENT_QUOTES));
        $key = $q->answer_key;
        if (in_array($q->type, ['pg', 'pgk', 'bs'], true)) {
            $this->options = $q->options->map(fn ($o) => ['key' => $o->key, 'content' => $plain($o->content), 'image' => $o->image])->values()->all();
        }
        if ($q->type === 'jodoh') {
            $this->left = $q->options->where('side', 'left')->map(fn ($o) => ['key' => $o->key, 'content' => $plain($o->content)])->values()->all();
            $this->right = $q->options->where('side', 'right')->map(fn ($o) => ['key' => $o->key, 'content' => $plain($o->content), 'image' => $o->image])->values()->all();
            $this->keyJodoh = array_map('strval', (array) $key);
        }
        match ($q->type) {
            'pg' => $this->keyPg = (string) $key,
            'pgk' => $this->keyPgk = array_map('strval', (array) $key),
            'bs' => $this->keyBs = array_map(fn ($v) => $v ? 'B' : 'S', (array) $key),
            'isian' => $this->keyIsian = implode("\n", (array) $key),
            default => null,
        };
        if (! $this->options && in_array($q->type, ['pg', 'pgk', 'bs'], true)) {
            $this->resetOptions();
        }
    }

    public function updatedType(): void
    {
        $this->resetOptions();
    }

    private function resetOptions(): void
    {
        $this->options = match ($this->type) {
            'pg', 'pgk' => array_map(fn ($k) => ['key' => $k, 'content' => '', 'image' => null], ['A', 'B', 'C', 'D', 'E']),
            'bs' => array_map(fn ($k) => ['key' => (string) $k, 'content' => '', 'image' => null], [1, 2, 3]),
            default => [],
        };
        if ($this->type === 'jodoh' && ! $this->left) {
            $this->left = [['key' => '1', 'content' => ''], ['key' => '2', 'content' => ''], ['key' => '3', 'content' => '']];
            $this->right = [['key' => 'a', 'content' => '', 'image' => null], ['key' => 'b', 'content' => '', 'image' => null], ['key' => 'c', 'content' => '', 'image' => null]];
        }
        $this->keyPg = '';
        $this->keyPgk = [];
        $this->keyBs = [];
    }

    public function addOption(): void
    {
        $n = count($this->options);
        if ($n >= 8) {
            return;
        }
        $this->options[] = ['key' => $this->type === 'bs' ? (string) ($n + 1) : chr(65 + $n), 'content' => '', 'image' => null];
    }

    public function removeOption(int $i): void
    {
        array_splice($this->options, $i, 1);
        foreach ($this->options as $k => &$o) {
            $o['key'] = $this->type === 'bs' ? (string) ($k + 1) : chr(65 + $k);
        }
        unset($o);
    }

    public function addPair(): void
    {
        $n = count($this->left);
        if ($n >= 8) {
            return;
        }
        $this->left[] = ['key' => (string) ($n + 1), 'content' => ''];
        $this->right[] = ['key' => chr(97 + count($this->right)), 'content' => '', 'image' => null];
    }

    public function updatedImageUpload(): void
    {
        $this->validate(['imageUpload' => ['image', 'max:4096']], [], ['imageUpload' => 'gambar soal']);
        $this->image = app(ImageOptimizer::class)->storeUpload($this->imageUpload);
        $this->imageUpload = null;
    }

    public function updatedOptionUploads($value, $key): void
    {
        $this->validate(["optionUploads.{$key}" => ['image', 'max:4096']], [], ["optionUploads.{$key}" => 'gambar opsi']);
        $path = app(ImageOptimizer::class)->storeUpload($this->optionUploads[$key]);
        [$list, $i] = str_contains((string) $key, 'r') ? ['right', (int) substr((string) $key, 1)] : ['options', (int) $key];
        $this->{$list}[$i]['image'] = $path;
        unset($this->optionUploads[$key]);
    }

    public function updatedAudioUpload(): void
    {
        $this->validate(['audioUpload' => ['file', 'max:10240', 'mimes:mp3,m4a,ogg,wav']], [], ['audioUpload' => 'audio']);
        $this->audio = $this->audioUpload->store('media/audio', 'local');
        $this->audio = preg_replace('#^media/#', '', $this->audio);
        $this->audioUpload = null;
    }

    public function storeEditorImage(): ?string
    {
        $this->validate(['editorUpload' => ['required', 'image', 'max:4096']]);
        $path = app(ImageOptimizer::class)->storeUpload($this->editorUpload);
        $this->editorUpload = null;

        return ImageOptimizer::adminUrl($path);
    }

    public function removeImage(string $where, ?int $i = null): void
    {
        if ($where === 'question') {
            $this->image = null;
        } elseif ($where === 'right') {
            $this->right[$i]['image'] = null;
        } else {
            $this->options[$i]['image'] = null;
        }
    }

    /** @return array{0:mixed,1:array} kunci asli + daftar galat */
    private function buildKey(): array
    {
        $errors = [];
        $key = null;
        switch ($this->type) {
            case 'pg':
                $key = $this->keyPg;
                if (! in_array($key, array_column($this->options, 'key'), true)) {
                    $errors['keyPg'] = 'Pilih kunci jawaban.';
                }
                break;
            case 'pgk':
                $key = array_values(array_intersect(array_column($this->options, 'key'), $this->keyPgk));
                if (! $key) {
                    $errors['keyPgk'] = 'Centang minimal satu kunci.';
                }
                break;
            case 'bs':
                $key = [];
                foreach ($this->options as $o) {
                    $v = $this->keyBs[$o['key']] ?? null;
                    if (! in_array($v, ['B', 'S'], true)) {
                        $errors['keyBs'] = 'Tentukan Benar/Salah untuk setiap pernyataan.';
                    }
                    $key[$o['key']] = $v === 'B';
                }
                break;
            case 'jodoh':
                $key = [];
                $rights = array_column($this->right, 'key');
                foreach ($this->left as $l) {
                    $v = $this->keyJodoh[$l['key']] ?? '';
                    if (! in_array($v, $rights, true)) {
                        $errors['keyJodoh'] = 'Tentukan pasangan untuk setiap pernyataan kiri.';
                    }
                    $key[$l['key']] = $v;
                }
                break;
            case 'isian':
                $key = array_values(array_filter(array_map('trim', preg_split('/\R|\|/', $this->keyIsian) ?: [])));
                if (! $key) {
                    $errors['keyIsian'] = 'Isi minimal satu jawaban benar.';
                }
                break;
        }

        return [$key, $errors];
    }

    public function save(bool $next = false): void
    {
        $cleaner = app(HtmlCleaner::class);
        $this->content = $cleaner->clean($this->content);
        $this->explanation = $cleaner->clean($this->explanation);
        $this->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(Question::TYPES))],
            'content' => [$this->image ? 'nullable' : 'required', 'string'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'difficulty' => ['nullable', 'in:mudah,sedang,sulit'],
            'cognitive_level' => ['nullable', 'string', 'max:10'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'package' => ['nullable', 'in:A,B,C,D,E'],
            'stimulus_id' => ['nullable', 'exists:stimuli,id'],
            'audio_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ], [], ['content' => 'teks soal', 'weight' => 'bobot']);

        if (in_array($this->type, ['pg', 'pgk', 'bs'], true)) {
            $filled = array_filter($this->options, fn ($o) => trim($o['content']) !== '' || $o['image']);
            if (count($filled) < 2 || count($filled) !== count($this->options)) {
                $this->addError('options', 'Isi semua opsi/pernyataan (minimal 2). Hapus opsi yang tidak dipakai.');

                return;
            }
        }
        if ($this->type === 'jodoh' && (count(array_filter($this->left, fn ($l) => trim($l['content']) !== '')) < 2 || count(array_filter($this->right, fn ($r) => trim($r['content']) !== '' || $r['image'])) < 2)) {
            $this->addError('options', 'Isi minimal 2 pernyataan kiri dan 2 pilihan kanan.');

            return;
        }
        [$key, $keyErrors] = $this->buildKey();
        foreach ($keyErrors as $f => $msg) {
            $this->addError($f, $msg);
        }
        if ($keyErrors) {
            return;
        }

        $q = $this->question ?? new Question(['question_bank_id' => $this->bank->id, 'position' => (int) $this->bank->questions()->max('position') + 1]);
        DB::transaction(function () use ($q, $key) {
            $q->fill([
                'type' => $this->type,
                'stimulus_id' => $this->stimulus_id ?: null,
                'content' => $this->content,
                'image' => $this->image,
                'answer_key' => $key,
                'weight' => $this->weight,
                'difficulty' => $this->difficulty ?: null,
                'cognitive_level' => $this->cognitive_level ?: null,
                'explanation' => $this->explanation ?: null,
                'numeric' => $this->type === 'isian' && $this->numeric,
                'max_length' => in_array($this->type, ['isian', 'uraian'], true) ? ($this->max_length ?: null) : null,
                'audio' => $this->audio,
                'audio_limit' => $this->audio ? $this->audio_limit : null,
                'package' => $this->package ?: null,
            ])->save();
            $q->options()->delete();
            $pos = 0;
            if (in_array($this->type, ['pg', 'pgk', 'bs'], true)) {
                foreach ($this->options as $o) {
                    $q->options()->create(['key' => $o['key'], 'content' => HtmlCleaner::fromPlain($o['content']), 'image' => $o['image'], 'position' => $pos++]);
                }
            } elseif ($this->type === 'jodoh') {
                foreach ($this->left as $l) {
                    $q->options()->create(['key' => $l['key'], 'side' => 'left', 'content' => HtmlCleaner::fromPlain($l['content']), 'position' => $pos++]);
                }
                foreach ($this->right as $r) {
                    $q->options()->create(['key' => $r['key'], 'side' => 'right', 'content' => HtmlCleaner::fromPlain($r['content']), 'image' => $r['image'] ?? null, 'position' => $pos++]);
                }
            }
        });
        ActivityLog::record($this->question ? 'question_updated' : 'question_created', "Soal #{$q->id} ({$q->typeLabel()})", $q);
        $this->toast('success', 'Soal tersimpan');

        if ($next) {
            $this->redirectRoute('admin.banks.question', ['bank' => $this->bank]);

            return;
        }
        if (! $this->question) {
            $this->redirectRoute('admin.banks.question', ['bank' => $this->bank, 'question' => $q]);
        }
        $this->question = $q->fresh();
    }

    public function render()
    {
        $ids = $this->bank->questions()->pluck('id')->all();
        $preview = [
            'type' => $this->type,
            'content' => $this->content,
            'image' => ImageOptimizer::adminUrl($this->image),
            'stimulus' => $this->stimulus_id ? optional($this->bank->stimuli()->find($this->stimulus_id))->content : null,
            'options' => $this->type === 'jodoh'
                ? array_merge(
                    array_map(fn ($l) => ['key' => $l['key'], 'content' => e($l['content']), 'side' => 'left', 'image' => null], $this->left),
                    array_map(fn ($r) => ['key' => $r['key'], 'content' => e($r['content']), 'side' => 'right', 'image' => ImageOptimizer::adminUrl($r['image'] ?? null)], $this->right))
                : array_map(fn ($o) => ['key' => $o['key'], 'content' => e($o['content']), 'image' => ImageOptimizer::adminUrl($o['image']), 'side' => null], $this->options),
            'key' => $this->buildKey()[0],
        ];

        return view('livewire.admin.question-editor', [
            'ids' => $ids,
            'currentNo' => $this->question ? array_search($this->question->id, $ids, true) + 1 : count($ids) + 1,
            'stimuli' => $this->bank->stimuli()->pluck('title', 'id')->map(fn ($t, $id) => $t ?: "Stimulus #{$id}"),
            'preview' => $preview,
        ]);
    }
}
