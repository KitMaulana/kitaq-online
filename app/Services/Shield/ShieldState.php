<?php

namespace App\Services\Shield;

/**
 * Status anti-contek satu percobaan ujian (disimpan di kolom exam_attempts).
 * Objek nilai yang tidak berubah (immutable); gunakan with() untuk membuat salinan.
 */
final class ShieldState
{
    public const ACTIVE = 'active';
    public const WARNED = 'warned';
    public const LOCKED = 'locked';
    public const DONE = 'done';

    public function __construct(
        public readonly string $status = self::ACTIVE,
        public readonly int $warnings = 0,
        public readonly ?int $lastIncidentAt = null,
        public readonly ?string $lockReason = null,
    ) {
    }

    public function with(
        ?string $status = null,
        ?int $warnings = null,
        ?int $lastIncidentAt = null,
        ?string $lockReason = null,
        bool $clearLockReason = false,
    ): self {
        return new self(
            $status ?? $this->status,
            $warnings ?? $this->warnings,
            $lastIncidentAt ?? $this->lastIncidentAt,
            $clearLockReason ? null : ($lockReason ?? $this->lockReason),
        );
    }

    /** @return array{status:string,warnings:int,last_incident_at:?int,lock_reason:?string} */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'warnings' => $this->warnings,
            'last_incident_at' => $this->lastIncidentAt,
            'lock_reason' => $this->lockReason,
        ];
    }
}
