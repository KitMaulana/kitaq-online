<?php

namespace App\Services\Auth;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Aturan kredensial siswa KITAQ ONLINE.
 *  - Username : NISN (10 digit).
 *  - Password : tanggal lahir siswa dalam format DDMMYYYY (contoh 20 Oktober 2008 → 20102008).
 *
 * Kelas ini juga membaca tanggal lahir dari berbagai format yang biasa muncul di Excel
 * sekolah/Dapodik saat import, dan merapikan input password siswa yang mengetik
 * "20-10-2008", "20/10/2008", atau "2008-10-20".
 *
 * Password tetap disimpan sebagai hash (bcrypt), tidak pernah teks asli.
 */
final class StudentCredential
{
    public const NISN_LENGTH = 10;

    private const MONTHS = [
        'januari' => 1, 'jan' => 1, 'january' => 1,
        'februari' => 2, 'feb' => 2, 'pebruari' => 2, 'february' => 2,
        'maret' => 3, 'mar' => 3, 'march' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'jun' => 6, 'june' => 6,
        'juli' => 7, 'jul' => 7, 'july' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'aug' => 8, 'august' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10, 'october' => 10,
        'november' => 11, 'nov' => 11, 'nopember' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12, 'december' => 12,
    ];

    /** NISN valid → string 10 digit; tidak valid → null. Excel sering membuang nol di depan, jadi dipulihkan. */
    public static function normalizeNisn(string|int|float|null $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (is_float($raw)) {
            $raw = number_format($raw, 0, '', '');
        }
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '' || strlen($digits) > self::NISN_LENGTH) {
            return null;
        }
        if (strlen($digits) < self::NISN_LENGTH - 2) {
            return null; // terlalu pendek, kemungkinan salah ketik
        }

        return str_pad($digits, self::NISN_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Baca tanggal lahir dari format umum. Mengembalikan null bila tidak valid.
     * Format: DateTime, serial Excel (mis. 39741), "2008-10-20", "20/10/2008", "20-10-2008",
     * "20.10.2008", "20102008", "20 Oktober 2008", "20 Okt 2008".
     */
    public static function parseBirthDate(mixed $raw, ?DateTimeImmutable $today = null): ?DateTimeImmutable
    {
        $today = $today ?? new DateTimeImmutable('today');

        if ($raw instanceof DateTimeInterface) {
            return self::guard(DateTimeImmutable::createFromInterface($raw)->setTime(0, 0), $today);
        }
        if ($raw === null) {
            return null;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        // Serial tanggal Excel (sistem 1900): 1 = 1900-01-01, basis efektif 1899-12-30.
        if (preg_match('/^\d{4,5}(\.0+)?$/', $s) && (int) $s >= 18000 && (int) $s <= 80000) {
            $d = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $s . ' days');

            return self::guard($d, $today);
        }

        // 8 digit tanpa pemisah: DDMMYYYY (diutamakan) atau YYYYMMDD
        if (preg_match('/^\d{8}$/', $s)) {
            $dd = (int) substr($s, 0, 2);
            $mm = (int) substr($s, 2, 2);
            $yy = (int) substr($s, 4, 4);
            if ($yy >= 1900 && checkdate($mm, $dd, $yy)) {
                return self::make($yy, $mm, $dd, $today);
            }

            return self::make((int) substr($s, 0, 4), (int) substr($s, 4, 2), (int) substr($s, 6, 2), $today);
        }

        // YYYY-MM-DD atau YYYY/MM/DD (opsional diikuti jam)
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $s, $m)) {
            return self::make((int) $m[1], (int) $m[2], (int) $m[3], $today);
        }

        // DD-MM-YYYY, DD/MM/YYYY, DD.MM.YYYY (hari/bulan boleh 1 digit)
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $s, $m)) {
            return self::make((int) $m[3], (int) $m[2], (int) $m[1], $today);
        }

        // "20 Oktober 2008" / "20 Okt 2008" / "20-Okt-2008"
        if (preg_match('/^(\d{1,2})[\s\-]+([A-Za-z]+)\.?[\s\-]+(\d{4})$/u', $s, $m)) {
            $month = self::MONTHS[strtolower($m[2])] ?? null;

            return $month ? self::make((int) $m[3], $month, (int) $m[1], $today) : null;
        }

        return null;
    }

    /** Tanggal lahir → password awal (DDMMYYYY). */
    public static function passwordFromBirthDate(DateTimeInterface $date): string
    {
        return $date->format('dmY');
    }

    /**
     * Rapikan input password dari form login agar siswa tidak gagal karena format.
     * "20-10-2008" / "20/10/2008" / "20 10 2008" / "2008-10-20" / "1-2-2009" → DDMMYYYY.
     * Password yang sudah diganti siswa (bukan tanggal) dikembalikan apa adanya.
     */
    public static function normalizePasswordInput(string $input): string
    {
        $s = trim($input);
        if (preg_match('/^(\d{4})[-\/.\s](\d{1,2})[-\/.\s](\d{1,2})$/', $s, $m)) {
            return sprintf('%02d%02d%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{1,2})[-\/.\s](\d{1,2})[-\/.\s](\d{4})$/', $s, $m)) {
            return sprintf('%02d%02d%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $s;
    }

    private static function make(int $y, int $m, int $d, DateTimeImmutable $today): ?DateTimeImmutable
    {
        if (! checkdate($m, $d, $y)) {
            return null;
        }

        return self::guard(new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d)), $today);
    }

    private static function guard(DateTimeImmutable $d, DateTimeImmutable $today): ?DateTimeImmutable
    {
        $year = (int) $d->format('Y');
        if ($year < 1950 || $d > $today) {
            return null;
        }

        return $d;
    }
}
