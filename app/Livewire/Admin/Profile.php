<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithToast;
use App\Models\ActivityLog;
use App\Support\StaffPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Title('Profil & Password')]
class Profile extends Component
{
    use WithToast;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public ?string $pendingSecret = null;
    public string $code = '';

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => array_merge(StaffPassword::rules(), ['confirmed', 'different:current_password']),
        ]);
        $user = Auth::user();
        $user->forceFill(['password' => $this->password, 'must_change_password' => false])->save();
        ActivityLog::record('password_changed', 'Mengganti password sendiri', $user);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->toast('success', 'Password diganti', 'Gunakan password baru saat masuk berikutnya.');
    }

    /** Keluar dari semua perangkat lain (sesi database). */
    public function logoutOthers(): void
    {
        $id = session()->getId();
        DB::table('sessions')->where('user_id', Auth::id())->where('id', '!=', $id)->delete();
        $this->toast('success', 'Sesi lain ditutup');
    }

    public function startTwoFactor(): void
    {
        $this->pendingSecret = (new Google2FA())->generateSecretKey(32);
        $this->code = '';
    }

    public function confirmTwoFactor(): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);
        if (! $this->pendingSecret || ! (new Google2FA())->verifyKey($this->pendingSecret, $this->code, 1)) {
            $this->addError('code', 'Kode tidak cocok. Periksa jam HP Anda lalu coba lagi.');

            return;
        }
        $user = Auth::user();
        $user->forceFill(['two_factor_secret' => $this->pendingSecret, 'two_factor_confirmed_at' => now()])->save();
        session()->put('two_factor_passed', true);
        ActivityLog::record('2fa_enabled', 'Mengaktifkan 2FA', $user);
        $this->reset(['pendingSecret', 'code']);
        $this->toast('success', '2FA aktif');
    }

    public function disableTwoFactor(): void
    {
        $user = Auth::user();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        ActivityLog::record('2fa_disabled', 'Menonaktifkan 2FA', $user);
        $this->toast('warning', '2FA dinonaktifkan');
    }

    public function render()
    {
        $user = Auth::user();
        $qr = null;
        if ($this->pendingSecret) {
            $uri = (new Google2FA())->getQRCodeUrl('KITAQ ONLINE', $user->username, $this->pendingSecret);
            $qr = qr_svg($uri, 180);
        }

        return view('livewire.admin.profile', ['user' => $user, 'qr' => $qr]);
    }
}
