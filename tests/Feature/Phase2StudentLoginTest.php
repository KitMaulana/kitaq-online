<?php

use App\Livewire\Admin\StudentImport;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Support\Xlsx;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->student = student();
});

dataset('format_tanggal', ['20102008', '20-10-2008', '20/10/2008', '2008-10-20', '20.10.2008']);

it('login dengan NISN + tanggal lahir berbagai format', function (string $pw) {
    $this->post('/login', ['nisn' => '0081234567', 'password' => $pw])->assertRedirect('/siswa');
    $this->assertAuthenticatedAs($this->student, 'student');
})->with('format_tanggal');

it('memulihkan nol di depan NISN saat login', function () {
    $this->post('/login', ['nisn' => '81234567', 'password' => '20102008'])->assertRedirect('/siswa');
});

it('menolak tanggal lahir salah dengan sisa percobaan', function () {
    $this->post('/login', ['nisn' => '0081234567', 'password' => '01012000'])
        ->assertSessionHasErrors(['nisn' => 'NISN atau tanggal lahir salah. Sisa percobaan: 4.']);
    $this->assertGuest('student');
});

it('membatasi 5 percobaan per menit per NISN+IP', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['nisn' => '0081234567', 'password' => '01012000']);
    }
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008'])->assertSessionHasErrors('nisn');
    $this->assertGuest('student');
});

it('membekukan akun 15 menit setelah 10 gagal berturut dan mencatat log', function () {
    $this->student->forceFill(['failed_logins' => 9])->save();
    $this->post('/login', ['nisn' => '0081234567', 'password' => '01012000'])->assertSessionHasErrors('nisn');
    expect($this->student->fresh()->isFrozen())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'student_frozen')->exists())->toBeTrue();
    \Illuminate\Support\Facades\RateLimiter::clear('student-login:0081234567|127.0.0.1');
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008'])->assertSessionHasErrors('nisn');
    $this->assertGuest('student');
});

it('menolak akun nonaktif', function () {
    $this->student->update(['is_active' => false]);
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008'])->assertSessionHasErrors('nisn');
});

it('menampilkan halaman buka di browser untuk webview WhatsApp dan Edge', function (string $ua) {
    $this->withHeaders(['User-Agent' => $ua])->get('/')->assertStatus(403)->assertSee('Buka di browser yang diizinkan');
})->with([
    'Mozilla/5.0 (Linux; Android 13; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0 Mobile Safari/537.36 WhatsApp/2.24',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0',
]);

it('mengizinkan Safari iPhone dan Samsung Internet', function (string $ua) {
    $this->withHeaders(['User-Agent' => $ua])->get('/')->assertOk()->assertSee('NISN');
})->with([
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36',
]);

it('satu sesi aktif: sesi lama ditutup saat login baru di luar ujian', function () {
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008']);
    $this->student->forceFill(['current_session_id' => 'sesi-perangkat-lain'])->save();
    app('auth')->forgetGuards(); // request baru memuat ulang siswa dari database
    $this->get('/siswa')->assertRedirect('/');
    $this->assertGuest('student');
});

it('siswa hanya melihat jadwal kelasnya', function () {
    $mine = examSchedule();
    $other = examSchedule([], ['classroom_id' => classroom('XI IPS 9')->id]);
    $other->exam->update(['name' => 'Ujian Kelas Lain']);
    $this->post('/login', ['nisn' => '0081234567', 'password' => '20102008']);
    $this->get('/siswa')->assertOk()->assertSee('PTS Uji')->assertDontSee('Ujian Kelas Lain');
    $this->get("/ujian/{$other->id}")->assertNotFound();
});

it('menyamarkan tanggal lahir di halaman admin', function () {
    $this->actingAs(staff('operator'))->get('/admin/siswa')->assertOk()->assertSee('20-10-****')->assertDontSee('2008-10-20');
});

it('kartu login PDF tidak memuat tanggal lahir', function () {
    $res = $this->actingAs(staff('operator'))->get('/admin/siswa/kartu')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/pdf');
    $view = view('pdf.student-cards', ['students' => Student::with('classroom')->get(), 'url' => 'https://ujian.test', 'qr' => '', 'school' => 'SMA'])->render();
    expect($view)->toContain('0081234567')->not->toContain('20102008')->not->toContain('20-10-2008')->not->toContain('2008-10-20');
});

it('import siswa dari Excel memakai StudentCredential (nol depan, format tanggal, ganda, galat)', function () {
    $path = Xlsx::writeTemp([
        ['nisn' => '81234500', 'nama' => 'Budi', 'tanggal_lahir' => '01/02/2009', 'jenis_kelamin' => 'L', 'kelas' => 'X-1', 'nis' => '', 'tempat_lahir' => ''],
        ['nisn' => '0081234501', 'nama' => 'Citra', 'tanggal_lahir' => '20 Oktober 2008', 'jenis_kelamin' => 'P', 'kelas' => 'X-1', 'nis' => '9', 'tempat_lahir' => 'Serang'],
        ['nisn' => '0081234501', 'nama' => 'Ganda', 'tanggal_lahir' => '2008-10-20', 'jenis_kelamin' => 'P', 'kelas' => 'X-1', 'nis' => '', 'tempat_lahir' => ''],
        ['nisn' => '12', 'nama' => 'Salah', 'tanggal_lahir' => '31/02/2008', 'jenis_kelamin' => 'X', 'kelas' => 'X-1', 'nis' => '', 'tempat_lahir' => ''],
    ]);
    $file = UploadedFile::fake()->createWithContent('siswa.xlsx', file_get_contents($path));

    Livewire::actingAs(staff('operator'))->test(StudentImport::class)
        ->set('file', $file)->call('analyze')->assertSet('step', 2)
        ->call('runImport')->assertSet('step', 3)
        ->assertSet('result.created', 2)->assertSet('result.failed', 2);

    $budi = Student::query()->where('nisn', '0081234500')->firstOrFail();
    expect(\Illuminate\Support\Facades\Hash::check('01022009', $budi->password))->toBeTrue()
        ->and($budi->classroom->name)->toBe('X-1');
});
