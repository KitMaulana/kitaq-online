<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Models\Setting;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Pengaturan sekolah (nama, logo, kop, kepala sekolah, alamat situs, opsi login). */
#[Title('Pengaturan Sekolah')]
class Settings extends Component
{
    use WithFileUploads, WithToast;

    public array $form = [];
    public $logo;

    public function mount(): void
    {
        foreach (array_keys(Setting::DEFAULTS) as $k) {
            $this->form[$k] = Setting::get($k);
        }
        $this->form['force_change_password'] = (bool) $this->form['force_change_password'];
    }

    public function save(): void
    {
        $this->validate([
            'form.school_name' => ['required', 'string', 'max:150'],
            'form.school_address' => ['nullable', 'string', 'max:255'],
            'form.school_city' => ['nullable', 'string', 'max:80'],
            'form.school_letterhead' => ['nullable', 'string', 'max:500'],
            'form.principal_name' => ['nullable', 'string', 'max:120'],
            'form.principal_nip' => ['nullable', 'string', 'max:30'],
            'form.site_url' => ['nullable', 'url', 'max:200'],
            'form.default_preset' => ['required', 'in:longgar,standar,ketat'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ], [], ['form.school_name' => 'nama sekolah', 'form.site_url' => 'alamat situs']);
        if ($this->logo) {
            $this->form['school_logo'] = $this->logo->store('school', 'local');
            $this->logo = null;
        }
        foreach ($this->form as $k => $v) {
            Setting::put($k, $k === 'force_change_password' ? (bool) $v : $v);
        }
        ActivityLog::record('settings_updated', 'Mengubah pengaturan sekolah');
        $this->toast('success', 'Pengaturan tersimpan');
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}
