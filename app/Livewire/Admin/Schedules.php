<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\IpUtils;

/** Jadwal ujian per kelas/rombel + sesi + jam buka/tutup, peserta khusus, pengawas, batasan IP. */
#[Title('Jadwal')]
class Schedules extends Component
{
    use WithDataTable, WithToast;

    protected array $sortable = ['opens_at', 'status'];

    #[Url(as: 'create')]
    public ?int $create = null;
    #[Url(as: 'exam')]
    public ?int $examParam = null;

    public bool $showForm = false;
    public bool $confirmClose = false;
    public ?int $editingId = null;
    public ?int $closeId = null;
    public array $form = [];
    public string $specialNisn = '';
    public int $extendMinutes = 10;

    public function mount(): void
    {
        $this->filters = ['when' => 'today', 'status' => ''];
        $this->sortField = 'opens_at';
        $this->sortDirection = 'asc';
        $this->blank();
        if ($this->create && $this->canManage()) {
            $this->openCreate();
            if ($this->examParam) {
                $this->form['exam_id'] = (string) $this->examParam;
            }
        }
    }

    private function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'guru']);
    }

    private function blank(): void
    {
        $this->form = [
            'exam_id' => '', 'classrooms' => [], 'date' => today()->format('Y-m-d'), 'opens' => '07:30', 'closes' => '09:30',
            'exam_session_id' => '', 'room_id' => '', 'token_required' => true, 'ip_allowlist' => '', 'supervisors' => [], 'special' => [],
        ];
        $this->specialNisn = '';
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->blank();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $s = ExamSchedule::query()->visibleTo(auth()->user())->with(['supervisors', 'specialStudents'])->findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'exam_id' => (string) $s->exam_id, 'classrooms' => $s->classroom_id ? [(string) $s->classroom_id] : [],
            'date' => $s->date->format('Y-m-d'), 'opens' => $s->opens_at->format('H:i'), 'closes' => $s->closes_at->format('H:i'),
            'exam_session_id' => (string) $s->exam_session_id, 'room_id' => (string) $s->room_id, 'token_required' => $s->token_required,
            'ip_allowlist' => implode("\n", (array) $s->ip_allowlist),
            'supervisors' => $s->supervisors->pluck('id')->map(fn ($i) => (string) $i)->all(),
            'special' => $s->specialStudents->map(fn ($st) => ['id' => $st->id, 'name' => $st->name, 'nisn' => $st->nisn, 'reason' => $st->pivot->reason])->all(),
        ];
        $this->showForm = true;
    }

    public function updatedFormExamSessionId($v): void
    {
        if ($v && ($sess = ExamSession::query()->find($v)) && $sess->starts_at) {
            $this->form['opens'] = substr((string) $sess->starts_at, 0, 5);
            $this->form['closes'] = substr((string) $sess->ends_at, 0, 5);
        }
    }

    public function addSpecial(): void
    {
        $st = Student::query()->where('nisn', \App\Services\Auth\StudentCredential::normalizeNisn($this->specialNisn) ?? '-')->first();
        if (! $st) {
            $this->addError('specialNisn', 'Siswa dengan NISN itu tidak ditemukan.');

            return;
        }
        if (! collect($this->form['special'])->contains('id', $st->id)) {
            $this->form['special'][] = ['id' => $st->id, 'name' => $st->name, 'nisn' => $st->nisn, 'reason' => 'susulan'];
        }
        $this->specialNisn = '';
    }

    public function removeSpecial(int $i): void
    {
        array_splice($this->form['special'], $i, 1);
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'form.exam_id' => ['required', 'exists:exams,id'],
            'form.classrooms' => [count($this->form['special']) ? 'array' : 'required', 'array'],
            'form.date' => ['required', 'date'],
            'form.opens' => ['required', 'date_format:H:i'],
            'form.closes' => ['required', 'date_format:H:i'],
            'form.exam_session_id' => ['nullable', 'exists:exam_sessions,id'],
            'form.room_id' => ['nullable', 'exists:rooms,id'],
        ], [], ['form.exam_id' => 'ujian', 'form.classrooms' => 'kelas', 'form.date' => 'tanggal', 'form.opens' => 'jam buka', 'form.closes' => 'jam tutup']);
        $exam = Exam::query()->visibleTo(auth()->user())->findOrFail($this->form['exam_id']);
        $opens = Carbon::parse($this->form['date'] . ' ' . $this->form['opens']);
        $closes = Carbon::parse($this->form['date'] . ' ' . $this->form['closes']);
        if ($closes->lte($opens)) {
            $this->addError('form.closes', 'Jam tutup harus setelah jam buka.');

            return;
        }
        $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $this->form['ip_allowlist']) ?: [])));
        foreach ($ips as $ip) {
            $base = explode('/', $ip)[0];
            if (! filter_var($base, FILTER_VALIDATE_IP)) {
                $this->addError('form.ip_allowlist', "IP/CIDR tidak valid: {$ip}");

                return;
            }
        }
        $attrs = [
            'exam_id' => $exam->id, 'date' => $this->form['date'], 'opens_at' => $opens, 'closes_at' => $closes,
            'exam_session_id' => $this->form['exam_session_id'] ?: null, 'room_id' => $this->form['room_id'] ?: null,
            'token_required' => (bool) $this->form['token_required'], 'ip_allowlist' => $ips ?: null,
        ];
        $classes = $this->form['classrooms'] ?: [null];
        $saved = [];
        if ($this->editingId) {
            $s = ExamSchedule::query()->findOrFail($this->editingId);
            $s->fill($attrs + ['classroom_id' => $classes[0] ?: null]);
            if ($s->status === 'closed' && $closes->isFuture()) {
                $s->status = 'scheduled';
            }
            $s->save();
            $saved[] = $s;
        } else {
            foreach ($classes as $cid) {
                $saved[] = ExamSchedule::query()->create($attrs + ['classroom_id' => $cid ?: null, 'status' => 'scheduled']);
            }
        }
        foreach ($saved as $s) {
            $s->supervisors()->sync(array_map('intval', $this->form['supervisors']));
            $s->specialStudents()->sync(collect($this->form['special'])->mapWithKeys(fn ($x) => [$x['id'] => ['reason' => $x['reason'] ?? null]])->all());
            ActivityLog::record($this->editingId ? 'schedule_updated' : 'schedule_created', $s->title() . ' ' . $opens->format('d/m H:i'), $s);
        }
        app(ScheduleService::class)->tick();
        $this->showForm = false;
        $this->toast('success', count($saved) . ' jadwal tersimpan', 'Dibuka otomatis pada jamnya (butuh cron).');
    }

    private function schedule(int $id): ExamSchedule
    {
        return ExamSchedule::query()->visibleTo(auth()->user())->findOrFail($id);
    }

    public function openNow(int $id): void
    {
        app(ScheduleService::class)->open($this->schedule($id), auth()->user());
        $this->toast('success', 'Jadwal dibuka');
    }

    public function pause(int $id): void
    {
        app(ScheduleService::class)->pause($this->schedule($id), auth()->user());
        $this->toast('warning', 'Semua peserta dijeda');
    }

    public function resume(int $id): void
    {
        app(ScheduleService::class)->resume($this->schedule($id), auth()->user());
        $this->toast('success', 'Ujian dilanjutkan', 'Waktu jeda dikembalikan ke peserta.');
    }

    public function extend(int $id): void
    {
        app(ScheduleService::class)->extend($this->schedule($id), $this->extendMinutes, auth()->user());
        $this->toast('success', "Diperpanjang {$this->extendMinutes} menit");
    }

    public function askClose(int $id): void
    {
        $this->closeId = $id;
        $this->confirmClose = true;
    }

    public function close(): void
    {
        $n = app(ScheduleService::class)->close($this->schedule($this->closeId), auth()->user());
        $this->confirmClose = false;
        $this->toast('success', 'Jadwal ditutup', "{$n} peserta dikumpulkan paksa.");
    }

    public function delete(int $id): void
    {
        $s = $this->schedule($id);
        if ($s->attempts()->whereNotNull('started_at')->exists()) {
            $this->toast('error', 'Tidak dapat dihapus', 'Sudah ada siswa yang mengerjakan.');

            return;
        }
        ActivityLog::record('schedule_deleted', $s->title(), $s);
        $s->delete();
        $this->toast('success', 'Jadwal dihapus');
    }

    public function render()
    {
        $user = auth()->user();
        $q = ExamSchedule::query()->visibleTo($user)->with(['exam.subject', 'classroom', 'session', 'room', 'supervisors'])
            ->withCount(['attempts as started_count' => fn ($a) => $a->whereNotNull('started_at')]);
        match ($this->filters['when'] ?? 'today') {
            'today' => $q->whereDate('date', today()),
            'upcoming' => $q->whereDate('date', '>', today()),
            'past' => $q->whereDate('date', '<', today()),
            default => null,
        };
        if ($this->filters['status'] ?? '') {
            $q->where('status', $this->filters['status']);
        }
        if (trim($this->search) !== '') {
            $q->whereHas('exam', fn ($e) => $e->where('name', 'like', '%' . $this->search . '%'));
        }
        $field = $this->isSortable($this->sortField) ? $this->sortField : 'opens_at';
        $schedules = $q->orderBy($field, $this->sortDirection === 'desc' ? 'desc' : 'asc')->paginate($this->perPage);

        return view('livewire.admin.schedules', [
            'schedules' => $schedules,
            'exams' => Exam::query()->visibleTo($user)->orderBy('name')->pluck('name', 'id'),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id'),
            'sessions' => ExamSession::query()->orderBy('starts_at')->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'rooms' => Room::query()->orderBy('name')->pluck('name', 'id'),
            'supervisorOptions' => User::query()->role(['pengawas', 'guru', 'operator'])->orderBy('name')->pluck('name', 'id'),
            'canManage' => $this->canManage(),
        ]);
    }
}
