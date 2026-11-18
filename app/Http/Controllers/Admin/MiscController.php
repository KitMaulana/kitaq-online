<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pencarian command palette, unduh template, penyaji media soal untuk staf, halaman Tentang. */
class MiscController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();
        $items = [];
        if (mb_strlen($q) >= 2) {
            if ($user->isAdminLike() && \Route::has('admin.students')) {
                Student::query()->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('nisn', 'like', "{$q}%"))
                    ->limit(6)->get(['id', 'name', 'nisn'])->each(function ($s) use (&$items) {
                        $items[] = ['label' => "{$s->name} · {$s->nisn}", 'group' => 'Siswa', 'url' => route('admin.students', ['q' => $s->nisn])];
                    });
            }
            if (\Route::has('admin.exams.edit')) {
                Exam::query()->visibleTo($user)->where('name', 'like', "%{$q}%")->limit(5)->get(['id', 'name'])->each(function ($e) use (&$items) {
                    $items[] = ['label' => $e->name, 'group' => 'Ujian', 'url' => route('admin.exams.edit', $e)];
                });
            }
        }

        return response()->json(['items' => $items]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $map = [
            'siswa' => 'template_siswa.xlsx',
            'pengguna' => 'template_pengguna.xlsx',
            'soal' => 'template_soal.xlsx',
            'gambar' => 'contoh_gambar_soal.zip',
        ];
        $file = $map[(string) $request->query('file')] ?? abort(404);

        return response()->download(base_path('templates/' . $file));
    }

    /** Gambar soal untuk staf (editor/pratinjau). Siswa memakai rute bertanda tangan. */
    public function media(string $path)
    {
        abort_if(str_contains($path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists('media/' . $path), 404);

        return response()->file($disk->path('media/' . $path), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function about()
    {
        return view('admin.about');
    }
}
