<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Auth\StudentCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Ganti password siswa (bila sekolah mewajibkan saat login pertama). */
class PasswordController extends Controller
{
    public function show(): View
    {
        return view('student.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:60', 'confirmed'],
        ], [], ['password' => 'password baru', 'current_password' => 'password lama']);

        $student = $request->user('student');
        if (! Hash::check(StudentCredential::normalizePasswordInput($request->input('current_password')), $student->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password lama salah.']);
        }
        if ($request->input('password') === StudentCredential::passwordFromBirthDate($student->birth_date)) {
            throw ValidationException::withMessages(['password' => 'Password baru tidak boleh sama dengan tanggal lahir.']);
        }
        $student->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        ActivityLog::record('student_password_changed', 'Siswa mengganti password', $student, [], $student->id);

        return redirect()->route('student.dashboard')->with('success', 'Password berhasil diganti.');
    }
}
