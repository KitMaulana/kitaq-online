<?php

namespace App\Services;

/**
 * Token ujian berganti otomatis (mirip TOTP) — lapisan kedua di atas NISN + tanggal lahir.
 *
 * Mengapa perlu: tanggal lahir mudah ditebak teman sekelas. Token hanya ditampilkan
 * di layar pengawas/proyektor ruang ujian dan berganti tiap N menit, sehingga orang
 * di luar ruang (joki dari rumah) tidak bisa memulai ujian walau tahu NISN & tanggal lahir.
 * Token diminta SEKALI saat memulai/melanjutkan ujian, bukan setiap soal.
 *
 * Token = HMAC-SHA256(secret, sessionKey|jendelaWaktu) → 6 karakter dari alfabet tanpa
 * huruf/angka mirip (tanpa I, O, 0, 1). Token jendela sebelumnya masih diterima (grace)
 * agar siswa yang mengetik tepat saat token berganti tidak gagal.
 *
 * Tidak perlu menyimpan token di database. Kompatibel PHP 8.2.
 */
final class ExamTokenService
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private string $secret,
        private int $rotationSec = 900,
        private int $length = 6,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('Secret token tidak boleh kosong.');
        }
        $this->rotationSec = max(60, $rotationSec);
        $this->length = max(4, min(10, $length));
    }

    public function window(?int $now = null): int
    {
        return intdiv($now ?? time(), $this->rotationSec);
    }

    public function forWindow(string $sessionKey, int $window): string
    {
        $hash = hash_hmac('sha256', $sessionKey . '|' . $window, $this->secret, true);
        $out = '';
        for ($i = 0; $i < $this->length; $i++) {
            $out .= self::ALPHABET[ord($hash[$i]) & 31];
        }

        return $out;
    }

    public function current(string $sessionKey, ?int $now = null): string
    {
        return $this->forWindow($sessionKey, $this->window($now));
    }

    public function secondsUntilRotation(?int $now = null): int
    {
        $now = $now ?? time();

        return $this->rotationSec - ($now % $this->rotationSec);
    }

    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $input) ?? '');
    }

    public function verify(string $input, string $sessionKey, ?int $now = null, int $graceWindows = 1): bool
    {
        $token = self::normalize($input);
        if (strlen($token) !== $this->length) {
            return false;
        }
        $w = $this->window($now);
        $ok = false;
        for ($i = 0; $i <= max(0, $graceWindows); $i++) {
            // Bandingkan semua jendela dengan hash_equals (waktu konstan), jangan berhenti di tengah.
            $ok = hash_equals($this->forWindow($sessionKey, $w - $i), $token) || $ok;
        }

        return $ok;
    }
}
