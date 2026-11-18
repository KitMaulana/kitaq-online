<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rules\Password;

/**
 * Aturan password staf (KEAMANAN.md §4): minimal 10 karakter, huruf + angka,
 * dan bukan password umum (cek lokal, tanpa memanggil layanan luar).
 */
final class StaffPassword
{
    private const COMMON = [
        'password', 'password1', 'password123', '1234567890', '12345678910', 'qwertyuiop', 'admin12345', 'administrator',
        'sekolah123', 'sekolah2026', 'bismillah', 'bismillah123', 'indonesia', 'indonesia1', 'iloveyou12', 'superadmin',
        'kitaq#2026', 'kitaqonline', 'operator123', 'guru123456', 'pengawas123', 'passw0rd123', 'abcdef12345',
    ];

    /** @return array<int,mixed> */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:100',
            Password::min(10)->letters()->numbers(),
            function (string $attribute, mixed $value, Closure $fail) {
                if ($value !== null && in_array(mb_strtolower((string) $value), self::COMMON, true)) {
                    $fail('Password ini terlalu umum. Pilih password lain.');
                }
            },
        ];
    }
}
