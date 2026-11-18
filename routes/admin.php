<?php

/*
| Rute admin fase 3–9 (dimuat dari routes/web.php di dalam grup prefix admin, name admin., auth:web + staff).
*/

use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

/* ---------- Fase 3: Bank soal (Super Admin, Operator, Guru) ---------- */
Route::middleware('role:super_admin|operator|guru')->group(function () {
    Route::get('/bank-soal', Admin\QuestionBanks::class)->name('banks');
    Route::get('/bank-soal/{bank}', Admin\BankDetail::class)->name('banks.show');
    Route::get('/bank-soal/{bank}/soal/{question?}', Admin\QuestionEditor::class)->name('banks.question');
});

/* ---------- Fase 4: Ujian, jadwal, token ---------- */
Route::middleware('role:super_admin|operator|guru')->group(function () {
    Route::get('/ujian', Admin\Exams::class)->name('exams');
    Route::get('/ujian/buat', Admin\ExamForm::class)->name('exams.create');
    Route::get('/ujian/{exam}/ubah', Admin\ExamForm::class)->name('exams.edit');
});
Route::get('/jadwal', Admin\Schedules::class)->name('schedules');
Route::get('/sesi-token', Admin\Sessions::class)->name('sessions');
Route::get('/token/{schedule}', [\App\Http\Controllers\Admin\TokenController::class, 'token'])->name('token');
Route::get('/proyektor/{schedule}', [\App\Http\Controllers\Admin\TokenController::class, 'projector'])->name('projector');

/* ---------- Fase 7: Monitoring live (polling) ---------- */
Route::get('/monitoring', [\App\Http\Controllers\Admin\MonitoringController::class, 'index'])->name('monitoring.index');
Route::get('/monitoring/{schedule}', [\App\Http\Controllers\Admin\MonitoringController::class, 'show'])->name('monitoring.show');
Route::get('/foto-siswa/{student}', [\App\Http\Controllers\Api\MonitorController::class, 'photo'])->name('monitoring.photo');

/* ---------- Fase 8: Rekap, laporan & analitik integritas ---------- */
Route::middleware('role:super_admin|operator|guru')->group(function () {
    Route::get('/rekap', Admin\Recap::class)->name('recap');
    Route::get('/analisis-butir', Admin\ItemAnalysis::class)->name('item-analysis');
    Route::get('/koreksi', Admin\Grading::class)->name('grading');
    Route::get('/integritas', Admin\Integrity::class)->name('integrity');
});
Route::get('/pelanggaran', Admin\Violations::class)->name('violations');
Route::get('/berita-acara', [\App\Http\Controllers\Admin\ReportController::class, 'minutesIndex'])->name('minutes');
Route::get('/berita-acara/{schedule}', [\App\Http\Controllers\Admin\ReportController::class, 'minutesShow'])->name('minutes.show');
Route::get('/berita-acara/{schedule}/pdf', [\App\Http\Controllers\Admin\ReportController::class, 'minutesPdf'])->name('minutes.pdf');
Route::get('/unduhan', [\App\Http\Controllers\Admin\ReportController::class, 'exports'])->name('exports');
Route::get('/unduhan/{export}', [\App\Http\Controllers\Admin\ReportController::class, 'download'])->name('exports.download');

/* ---------- Fase 9: Sistem ---------- */
Route::middleware('role:super_admin|operator')->group(function () {
    Route::get('/kesehatan', Admin\Health::class)->name('health');
    Route::get('/log-aktivitas', Admin\ActivityLogs::class)->name('activity');
});
Route::middleware('role:super_admin')->group(function () {
    Route::get('/pengaturan', Admin\Settings::class)->name('settings');
    Route::get('/backup', Admin\Backups::class)->name('backups');
});
