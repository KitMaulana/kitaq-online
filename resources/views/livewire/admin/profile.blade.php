<div>
    <x-ui.page-header title="Profil & Password" :subtitle="$user->name . ' · ' . $user->roleLabel()" :crumbs="['Dashboard' => route('admin.dashboard'), 'Profil']" />

    @if ($user->must_change_password)
        <x-ui.alert tone="warning" title="Wajib ganti password">Akun ini masih memakai password bawaan. Ganti password sebelum memakai menu lain.</x-ui.alert>
    @endif

    <div class="grid g-2b">
        <x-ui.card title="Ganti password" subtitle="Minimal 10 karakter, kombinasi huruf dan angka, bukan password umum.">
            <form wire:submit="changePassword" class="space-y-4">
                <x-ui.input type="password" wire:model="current_password" label="Password saat ini" autocomplete="current-password" />
                <x-ui.input type="password" wire:model="password" label="Password baru" autocomplete="new-password" />
                <x-ui.input type="password" wire:model="password_confirmation" label="Ulangi password baru" autocomplete="new-password" />
                <x-ui.button type="submit" icon="save" loading="changePassword">Simpan password</x-ui.button>
            </form>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card title="Verifikasi dua langkah (2FA)" subtitle="Disarankan untuk Super Admin. Pakai aplikasi autentikator (TOTP).">
                @if ($user->hasTwoFactor())
                    <x-ui.badge tone="success" dot>2FA aktif sejak {{ fmt_dt($user->two_factor_confirmed_at) }}</x-ui.badge>
                    <div class="mt-4"><x-ui.button variant="soft-danger" wire:click="disableTwoFactor" loading="disableTwoFactor">Nonaktifkan 2FA</x-ui.button></div>
                @elseif ($pendingSecret)
                    <div class="ipbox mb-4">
                        <div class="qr">{!! $qr !!}</div>
                        <div class="text-sm"><p class="muted">Pindai QR atau masukkan kunci:</p><p class="code break-all font-bold">{{ $pendingSecret }}</p></div>
                    </div>
                    <form wire:submit="confirmTwoFactor" class="flex gap-2 items-end">
                        <div class="flex-1"><x-ui.input wire:model="code" label="Kode 6 digit" inputmode="numeric" maxlength="6" /></div>
                        <x-ui.button type="submit" loading="confirmTwoFactor">Aktifkan</x-ui.button>
                    </form>
                @else
                    <x-ui.button variant="secondary" icon="shield" wire:click="startTwoFactor">Siapkan 2FA</x-ui.button>
                @endif
            </x-ui.card>
            <x-ui.card title="Sesi">
                <p class="muted text-sm mb-3">Sesi admin berakhir otomatis setelah 2 jam tanpa aktivitas.</p>
                <x-ui.button variant="secondary" icon="logout" wire:click="logoutOthers">Keluar dari semua perangkat lain</x-ui.button>
            </x-ui.card>
        </div>
    </div>
</div>
