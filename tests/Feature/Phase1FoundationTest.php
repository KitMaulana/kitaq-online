<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

it('memakai PHP ^8.2 dan Laravel 12 tanpa Filament', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($composer['require']['php'])->toBe('^8.2')
        ->and($composer['require']['laravel/framework'])->toStartWith('^12')
        ->and($composer['require'])->not->toHaveKey('filament/filament');
});

it('memakai driver database untuk session, cache, dan queue di .env.example', function () {
    $env = file_get_contents(base_path('.env.example'));
    expect($env)->toContain('SESSION_DRIVER=database')->toContain('CACHE_STORE=database')->toContain('QUEUE_CONNECTION=database');
});

it('menyemai Super Admin bawaan yang wajib ganti password', function () {
    $this->seed(SuperAdminSeeder::class);
    $u = User::query()->where('username', 'superadmin')->firstOrFail();
    expect(Hash::check('KitaQ#2026', $u->password))->toBeTrue()
        ->and($u->must_change_password)->toBeTrue()
        ->and($u->hasRole('super_admin'))->toBeTrue();
});

it('mendaftarkan empat role staf', function () {
    expect(\Spatie\Permission\Models\Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(['guru', 'operator', 'pengawas', 'super_admin']);
});

it('mengarahkan tamu ke login staf dan memasang header keamanan', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $res = $this->get('/admin/login')->assertOk()->assertSee('Masuk Staf');
    expect($res->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($res->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('login staf dengan username, lalu Super Admin bawaan dipaksa ganti password', function () {
    $this->seed(SuperAdminSeeder::class);
    $this->post('/admin/login', ['login' => 'superadmin', 'password' => 'KitaQ#2026'])->assertRedirect('/admin');
    $this->get('/admin')->assertRedirect('/admin/profil');
    $this->get('/admin/profil')->assertOk()->assertSee('Wajib ganti password');
});

it('menolak password staf yang salah', function () {
    staff('operator', ['username' => 'op1']);
    $this->post('/admin/login', ['login' => 'op1', 'password' => 'salah'])->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

it('menampilkan dashboard custom dengan footer CiptaKita.id dan menu sesuai role', function () {
    $admin = staff('super_admin');
    $this->actingAs($admin)->withSession(['two_factor_passed' => true])
        ->get('/admin')->assertOk()
        ->assertSee('KITAQ ONLINE v' . config('kitaq.version'))
        ->assertSee('CiptaKita.id — Solusi Digitalmu, dari kita!')
        ->assertSee('Siswa')->assertSee('Pengguna');

    $pengawas = staff('pengawas');
    $this->actingAs($pengawas)->get('/admin')->assertOk()->assertDontSee('href="' . route('admin.users') . '"', false);
    $this->actingAs($pengawas)->get('/admin/siswa')->assertForbidden();
});

it('mengakhiri sesi admin setelah 2 jam tanpa aktivitas', function () {
    $admin = staff();
    $this->actingAs($admin)->withSession(['staff_last_activity' => time() - 3 * 3600])
        ->get('/admin')->assertRedirect('/admin/login');
});

it('menyediakan guard student berbasis NISN dan SecurityHeaders terdaftar', function () {
    expect(config('auth.guards.student.provider'))->toBe('students')
        ->and(config('auth.providers.students.model'))->toBe(\App\Models\Student::class)
        ->and(app('router')->getMiddleware())->toHaveKey('security.headers');
});
