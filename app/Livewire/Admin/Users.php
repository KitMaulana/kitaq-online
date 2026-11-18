<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Concerns\WithImport;
use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Subject;
use App\Models\User;
use App\Support\StaffPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pengguna staf (Super Admin) + import template_pengguna.xlsx. */
#[Title('Pengguna')]
class Users extends Component
{
    use WithBulkActions, WithDataTable, WithImport, WithToast;

    protected array $sortable = ['name', 'username', 'last_login_at'];

    public bool $showForm = false;
    public bool $showImport = false;
    public bool $confirmDelete = false;
    public ?int $editingId = null;
    public ?int $deleteId = null;
    public ?string $newPassword = null;
    public array $form = [];

    public function mount(): void
    {
        $this->filters = ['role' => ''];
        $this->sortField = 'name';
        $this->sortDirection = 'asc';
        $this->blank();
    }

    private function blank(): void
    {
        $this->form = ['name' => '', 'username' => '', 'email' => '', 'role' => 'guru', 'password' => '', 'is_active' => true, 'subjects' => []];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->blank();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $u = User::query()->findOrFail($id);
        $this->blank();
        $this->editingId = $id;
        $this->form = [
            'name' => $u->name, 'username' => $u->username, 'email' => (string) $u->email, 'role' => $u->getRoleNames()->first() ?? 'guru',
            'password' => '', 'is_active' => $u->is_active, 'subjects' => $u->subjects()->pluck('subjects.id')->map(fn ($i) => (string) $i)->all(),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($this->editingId)],
            'form.email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.role' => ['required', Rule::in(array_keys(User::ROLES))],
            'form.password' => StaffPassword::rules(! $this->editingId),
            'form.subjects' => ['array'],
        ], [], ['form.name' => 'nama', 'form.username' => 'username', 'form.email' => 'email', 'form.password' => 'password', 'form.role' => 'role']);

        $u = $this->editingId ? User::query()->findOrFail($this->editingId) : new User();
        if ($u->exists && $u->id === auth()->id() && ! $this->form['is_active']) {
            $this->addError('form.is_active', 'Tidak dapat menonaktifkan akun sendiri.');

            return;
        }
        $u->fill(['name' => $this->form['name'], 'username' => $this->form['username'], 'email' => $this->form['email'] ?: null, 'is_active' => (bool) $this->form['is_active']]);
        if ($this->form['password']) {
            $u->password = $this->form['password'];
            $u->must_change_password = true;
        }
        $u->save();
        $u->syncRoles([$this->form['role']]);
        $u->subjects()->sync($this->form['role'] === 'guru' ? array_map('intval', $this->form['subjects']) : []);
        ActivityLog::record($this->editingId ? 'user_updated' : 'user_created', "{$u->name} ({$this->form['role']})", $u);
        $this->showForm = false;
        $this->toast('success', 'Pengguna tersimpan');
    }

    public function resetPassword(int $id): void
    {
        $u = User::query()->findOrFail($id);
        $this->newPassword = 'Kq' . Str::random(6) . random_int(10, 99) . '#';
        $u->forceFill(['password' => $this->newPassword, 'must_change_password' => true])->save();
        DB::table('sessions')->where('user_id', $u->id)->delete();
        ActivityLog::record('user_password_reset', "Reset password {$u->name}", $u);
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmDelete = true;
    }

    public function delete(): void
    {
        if ($this->deleteId === auth()->id()) {
            $this->toast('error', 'Tidak dapat menghapus akun sendiri');
        } elseif ($u = User::query()->find($this->deleteId)) {
            ActivityLog::record('user_deleted', $u->name, $u);
            $u->delete();
            $this->toast('success', 'Pengguna dihapus');
        }
        $this->confirmDelete = false;
    }

    /* ---------- Import (template_pengguna.xlsx) ---------- */
    protected function importSheet(): ?string
    {
        return 'pengguna';
    }

    protected function validateImportRow(array $row, int $line, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $username = trim((string) ($row['username'] ?? ''));
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        if (trim((string) ($row['nama'] ?? '')) === '') {
            $errors[] = 'Nama kosong';
        }
        if (! preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username tidak valid';
        } elseif (isset($seen[$username])) {
            $errors[] = 'Username ganda';
        }
        $seen[$username] = $line;
        if (! isset(User::ROLES[$role])) {
            $errors[] = 'Role harus super_admin/operator/guru/pengawas';
        }
        $pw = (string) ($row['password'] ?? '');
        if (strlen($pw) < 8) {
            $errors[] = 'Password minimal 8 karakter';
        }
        $codes = array_filter(array_map('trim', explode(',', (string) ($row['kode_mapel'] ?? ''))));
        $subjectIds = Subject::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
        foreach (array_diff($codes, array_keys($subjectIds)) as $missing) {
            $warnings[] = "Kode mapel {$missing} tidak ditemukan";
        }
        if (User::query()->where('username', $username)->exists()) {
            $warnings[] = 'Username sudah ada';
        }

        return ['data' => ['name' => trim((string) ($row['nama'] ?? '')), 'username' => $username, 'email' => ($row['email'] ?? '') ?: null, 'password' => $pw, 'role' => $role, 'subjects' => array_values($subjectIds)], 'errors' => $errors, 'warnings' => $warnings];
    }

    protected function persistImportRow(array $d, string $mode): string
    {
        $u = User::query()->where('username', $d['username'])->first();
        if ($u && $mode === 'skip') {
            return 'skipped';
        }
        $isNew = ! $u;
        $u = $u ?? new User(['username' => $d['username']]);
        $u->fill(['name' => $d['name'], 'email' => $d['email'], 'is_active' => true]);
        $u->password = $d['password'];
        $u->must_change_password = true;
        $u->save();
        $u->syncRoles([$d['role']]);
        if ($d['role'] === 'guru') {
            $u->subjects()->sync($d['subjects']);
        }

        return $isNew ? 'created' : 'updated';
    }

    protected function afterImport(): void
    {
        ActivityLog::record('user_import', "Import pengguna: {$this->result['created']} baru, {$this->result['updated']} diperbarui");
    }

    public function render()
    {
        $q = User::query()->with(['roles', 'subjects']);
        if ($this->filters['role'] ?? '') {
            $q->role($this->filters['role']);
        }

        return view('livewire.admin.users', [
            'users' => $this->applyTable($q, ['name', 'username', 'email'])->paginate($this->perPage),
            'subjects' => Subject::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
