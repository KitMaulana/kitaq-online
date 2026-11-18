<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Kartu login siswa (PDF): nama, NISN, kelas, alamat situs + QR. Tanggal lahir TIDAK dicetak (KEAMANAN.md).
 */
class StudentCardController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = Student::query()->with('classroom')->where('is_active', true)->orderBy('classroom_id')->orderBy('name');
        $classroom = null;
        if ($request->filled('classroom')) {
            $classroom = Classroom::query()->findOrFail((int) $request->query('classroom'));
            $q->where('classroom_id', $classroom->id);
        }
        if ($request->filled('ids')) {
            $q->whereIn('id', array_map('intval', explode(',', (string) $request->query('ids'))));
        }
        $students = $q->limit(1000)->get();
        $url = site_url();
        $qr = 'data:image/svg+xml;base64,' . base64_encode(qr_svg($url, 120));
        ActivityLog::record('print_cards', 'Mencetak kartu login ' . $students->count() . ' siswa' . ($classroom ? " ({$classroom->name})" : ''));

        $pdf = Pdf::loadView('pdf.student-cards', [
            'students' => $students,
            'url' => $url,
            'qr' => $qr,
            'school' => school('school_name'),
        ])->setPaper('a4');

        return $pdf->stream('kartu-login' . ($classroom ? '-' . str($classroom->name)->slug() : '') . '.pdf');
    }
}
