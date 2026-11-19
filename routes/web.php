<?php

use App\Http\Controllers\Admin\AuthController as StaffAuth;
use App\Http\Controllers\Admin\MiscController;
use App\Http\Controllers\Admin\StudentCardController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Student\AuthController as StudentAuth;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\PasswordController;
use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KITAQ ONLINE — rute web (Spesifikasi Bagian 10)
|--------------------------------------------------------------------------
| Halaman ujian & API siswa memakai CSP ketat (security.headers:exam).
| Admin memakai security.headers:app (Livewire/Alpine butuh 'unsafe-eval').
*/

/* ---------- Publik ---------- */
Route::middleware('security.headers:app')->group(function () {
    Route::get('/pasang', [PublicController::class, 'install'])->name('student.install');
    Route::get('/cek-perangkat', [PublicController::class, 'deviceCheck'])->name('device-check');
    Route::get('/logo-sekolah', [PublicController::class, 'schoolLogo'])->name('school.logo');
    Route::get('/api/ping', [PublicController::class, 'ping'])->middleware('throttle:60,1')->name('ping');
});

/* ---------- Instalasi darurat tanpa SSH (KITAQ_INSTALL_TOKEN) ---------- */
Route::get('/install', \App\Http\Controllers\InstallController::class)->middleware('throttle:10,1')->name('install');

/* ---------- Siswa ---------- */
Route::middleware(['security.headers:app', 'browser.allowed'])->group(function () {
    Route::get('/', [StudentAuth::class, 'show'])->name('student.login');
    Route::post('/login', [StudentAuth::class, 'login'])->middleware('throttle:30,1')->name('student.login.submit');
});

Route::middleware(['security.headers:app', 'auth:student', 'student.session'])->group(function () {
    Route::post('/logout', [StudentAuth::class, 'logout'])->name('student.logout');
    Route::get('/siswa', DashboardController::class)->name('student.dashboard');
    Route::get('/siswa/password', [PasswordController::class, 'show'])->name('student.password');
    Route::post('/siswa/password', [PasswordController::class, 'update'])->name('student.password.update');

    Route::middleware(['browser.allowed', 'exam.location'])->group(function () {
        Route::get('/ujian/{schedule}', [ExamController::class, 'show'])->name('student.exam.show');
        Route::post('/ujian/{schedule}/mulai', [ExamController::class, 'start'])->middleware('throttle:20,1')->name('student.exam.start');
    });
    Route::get('/ujian/{schedule}/hasil', [ExamController::class, 'result'])->name('student.exam.result');
});

// Ruang ujian: CSP ketat tanpa 'unsafe-eval', tanpa Livewire.
Route::middleware(['security.headers:exam', 'auth:student', 'student.session', 'browser.allowed', 'exam.location'])
    ->get('/ujian/{schedule}/kerjakan', [ExamController::class, 'room'])->name('student.exam.room');

/* ---------- API ujian siswa (sesi + CSRF, JSON) ---------- */
Route::prefix('api')->middleware(['security.headers:exam', 'auth:student', 'student.session', 'throttle:exam-api', 'exam.location', 'api.timing'])->group(function () {
    Route::get('/attempts/{attempt}/questions/{no}', [AttemptController::class, 'question'])->whereNumber('no')->name('api.attempts.question');
    Route::get('/attempts/{attempt}/map', [AttemptController::class, 'map'])->name('api.attempts.map');
    Route::post('/attempts/{attempt}/answers', [AttemptController::class, 'answers'])->name('api.attempts.answers');
    Route::post('/attempts/{attempt}/events', [AttemptController::class, 'events'])->name('api.attempts.events');
    Route::post('/attempts/{attempt}/heartbeat', [AttemptController::class, 'heartbeat'])->name('api.attempts.heartbeat');
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('api.attempts.submit');
});
Route::middleware(['auth:student', 'student.session', 'signed'])
    ->get('/m/{attempt}/{path}', [AttemptController::class, 'media'])->where('path', '.*')->name('media.signed');

/* ---------- API monitoring pengawas (polling 4 dtk) ---------- */
Route::prefix('api/monitor')->middleware(['security.headers:app', 'auth:web', 'staff', 'throttle:monitor-api'])->group(function () {
    Route::get('/{schedule}', [MonitorController::class, 'show'])->whereNumber('schedule')->name('api.monitor.show');
    Route::post('/{schedule}/jadwal/{action}', [MonitorController::class, 'scheduleAction'])->name('api.monitor.schedule-action');
    Route::get('/attempts/{attempt}/detail', [MonitorController::class, 'detail'])->name('api.monitor.detail');
    Route::post('/attempts/{attempt}/{action}', [MonitorController::class, 'action'])->whereIn('action', ['unlock', 'reset-login', 'extend', 'force-submit', 'message'])->name('api.monitor.action');
});

/* ---------- Admin / staf ---------- */
Route::prefix('admin')->name('admin.')->middleware('security.headers:app')->group(function () {
    Route::get('/login', [StaffAuth::class, 'show'])->name('login');
    Route::post('/login', [StaffAuth::class, 'login'])->middleware('throttle:staff-login')->name('login.submit');

    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [StaffAuth::class, 'logout'])->name('logout');
        Route::get('/2fa', [StaffAuth::class, 'challenge'])->name('2fa.challenge');
        Route::post('/2fa', [StaffAuth::class, 'verify'])->middleware('throttle:10,1')->name('2fa.verify');
    });

    Route::middleware(['auth:web', 'staff'])->group(function () {
        Route::get('/', Admin\Dashboard::class)->name('dashboard');
        Route::get('/profil', Admin\Profile::class)->name('profile');
        Route::get('/tentang', [MiscController::class, 'about'])->name('about');
        Route::get('/cari', [MiscController::class, 'search'])->name('search');
        Route::get('/template', [MiscController::class, 'template'])->name('templates');
        Route::get('/media/{path}', [MiscController::class, 'media'])->where('path', '.*')->name('media');
        Route::get('/_ui', Admin\UiCatalog::class)->name('ui');

        // Data master & siswa (Super Admin, Operator)
        Route::middleware('role:super_admin|operator')->group(function () {
            Route::get('/siswa', Admin\Students::class)->name('students');
            Route::get('/siswa/import', Admin\StudentImport::class)->name('students.import');
            Route::get('/siswa/kartu', StudentCardController::class)->name('students.cards');
            Route::get('/kelas', Admin\Classrooms::class)->name('classrooms');
            Route::get('/mapel', Admin\Subjects::class)->name('subjects');
            Route::get('/ruang-sesi', Admin\Rooms::class)->name('rooms');
            Route::get('/tahun-ajaran', Admin\AcademicYears::class)->name('academic-years');
        });
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/pengguna', Admin\Users::class)->name('users');
        });

        require __DIR__ . '/admin.php';
    });
});
