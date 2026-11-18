<?php

namespace App\Services\Shield;

/**
 * Mesin keputusan pelanggaran (otoritatif di server).
 *
 * Klien (KITAQ Shield) hanya melapor jenis event. Kelas ini menentukan apakah event itu
 * diabaikan, dicatat, menjadi peringatan, atau langsung mengunci ujian — berdasarkan
 * profil keketatan ujian (longgar / standar / ketat) plus pengecualian per ujian.
 *
 * Aturan:
 *  - 'lock'  → ujian langsung terkunci.
 *  - 'warn'  → peringatan; peringatan ke-(maxWarnings + 1) mengunci ujian.
 *  - Beberapa event 'warn' dalam jendela gabung (default 3 detik) dihitung SATU kejadian,
 *    karena satu aksi siswa (mis. pindah aplikasi) memicu blur + hidden + keluar layar penuh.
 *    Jendela TIDAK diperpanjang oleh event berikutnya, sehingga bolak-balik berulang tetap terhitung.
 *  - 'log'   → hanya dicatat di log pelanggaran (terlihat di laporan pengawas).
 *  - 'ignore'→ tidak dicatat sebagai pelanggaran (event informasi).
 *  - Event jenis tak dikenal → 'log'.
 *
 * Kompatibel PHP 8.2 (tanpa fitur 8.3+).
 */
final class ViolationPolicy
{
    public const LOG = 'log';
    public const WARN = 'warn';
    public const LOCK = 'lock';
    public const IGNORE = 'ignore';

    /** Event informasi (bukan pelanggaran). */
    public const INFO_EVENTS = [
        'exam_started', 'tab_visible', 'window_focus', 'split_screen_end',
        'online', 'offline', 'page_reload',
    ];

    public const PRESETS = [
        'longgar' => [
            'tab_hidden' => self::WARN, 'page_leave' => self::WARN, 'window_blur' => self::LOG,
            'split_screen' => self::WARN, 'multi_tab' => self::WARN, 'second_instance' => self::WARN,
            'fullscreen_exit' => self::LOG, 'translate_detected' => self::WARN, 'content_tampered' => self::WARN,
            'ai_extension_detected' => self::WARN, 'dom_injection' => self::LOG, 'physical_keyboard' => self::LOG,
            'foreign_input_focus' => self::LOG, 'ime_input' => self::LOG, 'screen_extended' => self::LOG,
        ],
        'standar' => [
            'tab_hidden' => self::WARN, 'page_leave' => self::WARN, 'window_blur' => self::WARN,
            'split_screen' => self::WARN, 'multi_tab' => self::LOCK, 'second_instance' => self::LOCK,
            'fullscreen_exit' => self::WARN, 'translate_detected' => self::LOCK, 'content_tampered' => self::LOCK,
            'ai_extension_detected' => self::LOCK, 'dom_injection' => self::WARN, 'physical_keyboard' => self::WARN,
            'foreign_input_focus' => self::WARN, 'ime_input' => self::WARN, 'screen_extended' => self::WARN,
        ],
        'ketat' => [
            'tab_hidden' => self::WARN, 'page_leave' => self::WARN, 'window_blur' => self::WARN,
            'split_screen' => self::LOCK, 'multi_tab' => self::LOCK, 'second_instance' => self::LOCK,
            'fullscreen_exit' => self::WARN, 'translate_detected' => self::LOCK, 'content_tampered' => self::LOCK,
            'ai_extension_detected' => self::LOCK, 'dom_injection' => self::LOCK, 'physical_keyboard' => self::WARN,
            'foreign_input_focus' => self::LOCK, 'ime_input' => self::LOCK, 'screen_extended' => self::LOCK,
            'shortcut_blocked' => self::WARN,
        ],
    ];

    /** Teks alasan untuk siswa & pengawas. */
    public const REASONS = [
        'tab_hidden' => 'Keluar dari halaman ujian (pindah tab/aplikasi).',
        'page_leave' => 'Meninggalkan atau menutup halaman ujian.',
        'window_blur' => 'Layar ujian kehilangan fokus (aplikasi/jendela lain dibuka).',
        'split_screen' => 'Layar terpisah atau jendela mengambang terdeteksi.',
        'multi_tab' => 'Ujian dibuka di dua tab/jendela.',
        'second_instance' => 'Ujian dibuka di perangkat atau browser lain.',
        'fullscreen_exit' => 'Keluar dari layar penuh.',
        'translate_detected' => 'Fitur terjemahan browser terdeteksi.',
        'content_tampered' => 'Teks soal diubah oleh browser atau ekstensi.',
        'ai_extension_detected' => 'Ekstensi/asisten AI terdeteksi di browser.',
        'dom_injection' => 'Ada elemen asing (ekstensi) di halaman ujian.',
        'physical_keyboard' => 'Keyboard fisik/Bluetooth terdeteksi.',
        'foreign_input_focus' => 'Kolom teks asing mendapat fokus.',
        'ime_input' => 'Input dari keyboard bawaan HP terdeteksi.',
        'screen_extended' => 'Monitor tambahan terdeteksi.',
        'shortcut_blocked' => 'Pintasan keyboard terlarang ditekan.',
        'heartbeat_gap' => 'Perangkat berhenti mengirim sinyal ke server.',
        // Tambahan KITAQ ONLINE (server): lihat docs/DECISIONS.md.
        'heartbeat_invalid' => 'Sinyal dari perangkat tidak sah (aplikasi ujian diubah).',
        'offline_lock' => 'Perangkat terputus dari server terlalu lama.',
        'location_blocked' => 'Ujian dibuka dari luar jaringan sekolah.',
        'copy_attempt' => 'Mencoba menyalin teks.',
        'paste_attempt' => 'Mencoba menempel teks.',
        'context_menu' => 'Membuka menu klik kanan/tekan lama.',
        'pointer_left' => 'Kursor keluar dari jendela ujian.',
        'fullscreen_denied' => 'Layar penuh ditolak browser.',
    ];

    /** @var array<string,string> */
    private array $actions;

    /**
     * @param array<string,string> $actions  peta jenis event → aksi
     */
    public function __construct(array $actions, private int $maxWarnings = 2, private int $mergeWindowSec = 3)
    {
        foreach ($actions as $type => $action) {
            if (! in_array($action, [self::LOG, self::WARN, self::LOCK, self::IGNORE], true)) {
                throw new \InvalidArgumentException("Aksi tidak valid untuk {$type}: {$action}");
            }
        }
        $this->actions = $actions;
        $this->maxWarnings = max(0, $maxWarnings);
        $this->mergeWindowSec = max(0, $mergeWindowSec);
    }

    /**
     * @param array<string,string> $overrides  pengecualian per ujian (mis. ['split_screen' => 'lock'])
     */
    public static function fromPreset(string $preset, array $overrides = [], int $maxWarnings = 2, int $mergeWindowSec = 3): self
    {
        if (! isset(self::PRESETS[$preset])) {
            throw new \InvalidArgumentException("Profil keketatan tidak dikenal: {$preset}");
        }

        return new self(array_merge(self::PRESETS[$preset], $overrides), $maxWarnings, $mergeWindowSec);
    }

    public function maxWarnings(): int
    {
        return $this->maxWarnings;
    }

    public function actionFor(string $type): string
    {
        if (in_array($type, self::INFO_EVENTS, true)) {
            return self::IGNORE;
        }

        return $this->actions[$type] ?? self::LOG;
    }

    public static function reasonFor(string $type): string
    {
        return self::REASONS[$type] ?? 'Aktivitas tidak wajar terdeteksi.';
    }

    /**
     * @param int $occurredAt  detik Unix kejadian (waktu klien yang sudah dijepit server ke rentang wajar)
     */
    public function evaluate(ShieldState $state, string $type, int $occurredAt): ShieldDecision
    {
        $action = $this->actionFor($type);

        if ($state->status === ShieldState::LOCKED || $state->status === ShieldState::DONE) {
            return new ShieldDecision($action === self::IGNORE ? self::IGNORE : self::LOG, $state, false, false, false, self::reasonFor($type));
        }

        if ($action === self::IGNORE || $action === self::LOG) {
            return new ShieldDecision($action, $state, false, false, false, self::reasonFor($type));
        }

        if ($action === self::LOCK) {
            $new = $state->with(status: ShieldState::LOCKED, lastIncidentAt: $occurredAt, lockReason: $type);

            return new ShieldDecision(self::LOCK, $new, false, true, false, self::reasonFor($type));
        }

        // WARN
        if ($state->lastIncidentAt !== null && abs($occurredAt - $state->lastIncidentAt) < $this->mergeWindowSec) {
            return new ShieldDecision(self::LOG, $state, false, false, true, self::reasonFor($type));
        }

        $warnings = $state->warnings + 1;
        if ($warnings > $this->maxWarnings) {
            $new = $state->with(status: ShieldState::LOCKED, warnings: $warnings, lastIncidentAt: $occurredAt, lockReason: $type);

            return new ShieldDecision(self::LOCK, $new, false, true, false, self::reasonFor($type));
        }

        $new = $state->with(status: ShieldState::WARNED, warnings: $warnings, lastIncidentAt: $occurredAt);

        return new ShieldDecision(self::WARN, $new, true, false, false, self::reasonFor($type));
    }

    /** Buka kunci oleh pengawas/admin. */
    public function unlock(ShieldState $state, bool $resetWarnings = true): ShieldState
    {
        $warnings = $resetWarnings ? 0 : min($state->warnings, $this->maxWarnings);

        return $state->with(
            status: $warnings > 0 ? ShieldState::WARNED : ShieldState::ACTIVE,
            warnings: $warnings,
            lockReason: null,
            clearLockReason: true,
        );
    }
}
