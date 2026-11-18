<?php

namespace App\Services\Shield;

/** Hasil evaluasi satu event oleh ViolationPolicy. */
final class ShieldDecision
{
    public function __construct(
        public readonly string $action,       // ignore | log | warn | lock
        public readonly ShieldState $state,   // status setelah event
        public readonly bool $newWarning,     // tampilkan layar peringatan ke siswa
        public readonly bool $locked,         // baru saja terkunci → kirim alarm ke pengawas
        public readonly bool $merged,         // digabung dengan kejadian sebelumnya (tidak menambah hitungan)
        public readonly string $reasonText,
    ) {
    }
}
