<?php

namespace App\Models;

use App\Services\Shield\ViolationPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShieldEvent extends Model
{
    public $timestamps = false;

    /** Label jenis event untuk pengawas (Bahasa Indonesia). */
    public const LABELS = [
        'tab_hidden' => 'Pindah tab/aplikasi',
        'page_leave' => 'Meninggalkan halaman',
        'window_blur' => 'Kehilangan fokus',
        'split_screen' => 'Split screen',
        'screen_extended' => 'Monitor tambahan',
        'multi_tab' => 'Dua tab',
        'second_instance' => 'Dua perangkat',
        'translate_detected' => 'Terjemahan',
        'content_tampered' => 'Teks soal diubah',
        'ai_extension_detected' => 'Ekstensi AI',
        'dom_injection' => 'Elemen asing',
        'physical_keyboard' => 'Keyboard fisik',
        'foreign_input_focus' => 'Kolom teks asing',
        'ime_input' => 'Keyboard HP',
        'fullscreen_exit' => 'Keluar layar penuh',
        'fullscreen_denied' => 'Layar penuh ditolak',
        'copy_attempt' => 'Coba salin',
        'paste_attempt' => 'Coba tempel',
        'context_menu' => 'Klik kanan',
        'shortcut_blocked' => 'Pintasan terlarang',
        'pointer_left' => 'Kursor keluar jendela',
        'heartbeat_gap' => 'Heartbeat hilang',
        'heartbeat_invalid' => 'Heartbeat tidak sah',
        'exam_started' => 'Mulai ujian',
        'online' => 'Online lagi',
        'offline' => 'Offline',
        'offline_lock' => 'Offline terlalu lama',
        'location_blocked' => 'Di luar lokasi',
    ];

    protected $fillable = ['exam_attempt_id', 'seq', 'instance_id', 'type', 'action', 'merged', 'detail', 'client_at', 'received_at'];

    protected function casts(): array
    {
        return ['detail' => 'array', 'merged' => 'boolean', 'client_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }

    public function reason(): string
    {
        return ViolationPolicy::reasonFor($this->type);
    }
}
