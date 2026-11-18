<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Token aktif (JSON) & Layar Proyektor: alamat situs + QR + token raksasa + hitung mundur. */
class TokenController extends Controller
{
    private function visible(Request $request, ExamSchedule $schedule): ExamSchedule
    {
        abort_unless(ExamSchedule::query()->visibleTo($request->user())->whereKey($schedule->id)->exists(), 403);

        return $schedule;
    }

    public function token(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $this->visible($request, $schedule);

        return response()->json([
            'token' => $schedule->currentToken(),
            'left' => $schedule->secondsUntilTokenRotation(),
            'total' => (int) config('kitaq.exam_token.rotation_sec', 900),
            'required' => (bool) $schedule->token_required,
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function projector(Request $request, ExamSchedule $schedule): View
    {
        $this->visible($request, $schedule);
        $schedule->load('exam', 'classroom');

        return view('admin.projector', [
            'schedule' => $schedule,
            'token' => ['token' => $schedule->currentToken(), 'left' => $schedule->secondsUntilTokenRotation(), 'total' => (int) config('kitaq.exam_token.rotation_sec', 900), 'url' => route('admin.token', $schedule)],
            'qr' => qr_svg(site_url(), 200),
        ]);
    }
}
