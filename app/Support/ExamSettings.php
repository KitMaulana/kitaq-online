<?php

namespace App\Support;

use App\Services\Shield\ViolationPolicy;

/**
 * Pengaturan ujian (kolom JSON `exams.settings`, Spesifikasi 5.6).
 * Nilai default diambil dari config/kitaq.php lalu ditimpa per ujian.
 */
final class ExamSettings
{
    public const SHOW_SCORE = ['immediately' => 'Langsung', 'after_close' => 'Setelah jadwal ditutup', 'never' => 'Tidak ditampilkan'];
    public const PRESETS = ['longgar' => 'Longgar', 'standar' => 'Standar', 'ketat' => 'Ketat'];
    public const BROWSERS = ['chrome' => 'Chrome', 'safari' => 'Safari', 'samsung' => 'Samsung Internet', 'huawei' => 'Huawei Browser', 'firefox' => 'Firefox', 'edge' => 'Edge', 'opera' => 'Opera', 'brave' => 'Brave', 'miui' => 'Mi Browser', 'uc' => 'UC Browser'];
    public const ACTIONS = ['ignore' => 'Abaikan', 'log' => 'Catat', 'warn' => 'Peringatan', 'lock' => 'Kunci'];

    /** Event yang bisa diberi pengecualian per ujian. */
    public const EVENT_LABELS = [
        'tab_hidden' => 'Pindah tab/aplikasi',
        'page_leave' => 'Meninggalkan halaman',
        'window_blur' => 'Kehilangan fokus',
        'split_screen' => 'Split screen / jendela mengambang',
        'multi_tab' => 'Dua tab',
        'second_instance' => 'Dua perangkat/browser',
        'fullscreen_exit' => 'Keluar layar penuh',
        'translate_detected' => 'Terjemahan browser',
        'content_tampered' => 'Teks soal diubah',
        'ai_extension_detected' => 'Ekstensi AI',
        'dom_injection' => 'Elemen asing (ekstensi)',
        'physical_keyboard' => 'Keyboard fisik',
        'foreign_input_focus' => 'Kolom teks asing',
        'ime_input' => 'Keyboard bawaan HP',
        'screen_extended' => 'Monitor tambahan',
        'shortcut_blocked' => 'Pintasan terlarang',
    ];

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $s = config('kitaq.shield');

        return [
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'token_required' => (bool) config('kitaq.exam_token.enabled_default', true),
            'show_score' => 'after_close',
            'min_minutes_before_submit' => 0,
            // Profil default: pengaturan sekolah (menu Pengaturan) bila diisi, selain itu KITAQ_SHIELD_PRESET.
            'preset' => \App\Models\Setting::allCached()['default_preset'] ?? ($s['preset'] ?? 'standar'),
            'overrides' => [],
            'max_warnings' => (int) ($s['max_warnings'] ?? 2),
            'browsers' => $s['browsers'] ?? ['chrome', 'safari', 'samsung', 'huawei'],
            'ios_require_standalone' => (bool) ($s['ios_require_standalone'] ?? true),
            'require_fullscreen' => (bool) ($s['require_fullscreen'] ?? true),
            'physical_keyboard_mobile' => $s['physical_keyboard']['mobile'] ?? 'block',
            'physical_keyboard_desktop' => $s['physical_keyboard']['desktop'] ?? 'allow',
            'canvas_mode' => (bool) ($s['canvas_mode'] ?? false),
            'offline_mode' => ((int) ($s['offline_lock_after_sec'] ?? 0)) > 0 ? 'lock' : 'log',
            'offline_lock_after_sec' => max(60, (int) ($s['offline_lock_after_sec'] ?? 0) ?: 120),
            'ip_restrict' => false,
            'timer_when_locked' => 'run',
            'packages' => [],
            'show_explanation' => false,
        ];
    }

    /** @param array<string,mixed>|null $stored @return array<string,mixed> */
    public static function resolve(?array $stored): array
    {
        $out = array_merge(self::defaults(), array_intersect_key($stored ?? [], self::defaults()));
        if (! isset(ViolationPolicy::PRESETS[$out['preset']])) {
            $out['preset'] = 'standar';
        }
        $out['overrides'] = array_filter(
            (array) $out['overrides'],
            fn ($a, $t) => is_string($t) && in_array($a, ['ignore', 'log', 'warn', 'lock'], true),
            ARRAY_FILTER_USE_BOTH
        );
        $out['max_warnings'] = max(0, min(10, (int) $out['max_warnings']));
        $out['browsers'] = array_values(array_intersect((array) $out['browsers'], array_keys(self::BROWSERS)));

        return $out;
    }

    /** Ringkasan aturan profil untuk panel "Profil keketatan". @return array<string,string> */
    public static function presetSummary(string $preset, array $overrides = []): array
    {
        $map = array_merge(ViolationPolicy::PRESETS[$preset] ?? [], $overrides);
        $out = [];
        foreach (self::EVENT_LABELS as $type => $label) {
            $out[$label] = self::ACTIONS[$map[$type] ?? 'log'];
        }

        return $out;
    }
}
