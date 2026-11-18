<?php

/*
| Helper global KITAQ ONLINE (dimuat lewat composer.json "autoload.files").
*/

if (! function_exists('initials')) {
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}

if (! function_exists('kitaq_version')) {
    function kitaq_version(): string
    {
        return (string) config('kitaq.version', '1.0.0');
    }
}

if (! function_exists('kitaq_footer')) {
    /** Footer wajib: "KITAQ ONLINE v{versi} · © {tahun} CiptaKita.id — Solusi Digitalmu, dari kita!" */
    function kitaq_footer(): string
    {
        return 'KITAQ ONLINE v' . kitaq_version() . ' · © ' . date('Y') . ' CiptaKita.id — Solusi Digitalmu, dari kita!';
    }
}

if (! function_exists('school')) {
    function school(string $key, mixed $default = null): mixed
    {
        return \App\Models\Setting::get($key, $default);
    }
}

if (! function_exists('site_url')) {
    /** Alamat situs untuk kartu login & layar proyektor. */
    function site_url(): string
    {
        $u = (string) (\App\Models\Setting::get('site_url') ?: config('app.url'));

        return rtrim($u, '/');
    }
}

if (! function_exists('site_host')) {
    function site_host(): string
    {
        return (string) (parse_url(site_url(), PHP_URL_HOST) ?: site_url());
    }
}

if (! function_exists('qr_svg')) {
    /** QR code SVG (bacon/bacon-qr-code, tanpa imagick). */
    function qr_svg(string $text, int $size = 160): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $svg = (new \BaconQrCode\Writer($renderer))->writeString($text);

        return preg_replace('/^<\?xml[^>]+>\s*/', '', $svg) ?? $svg;
    }
}

if (! function_exists('fmt_num')) {
    /** Format angka gaya Indonesia tanpa ekstensi intl. */
    function fmt_num(float|int|null $n, int $dec = 0): string
    {
        return number_format((float) $n, $dec, ',', '.');
    }
}

if (! function_exists('fmt_dt')) {
    function fmt_dt($dt, string $format = 'd M Y H:i'): string
    {
        if (! $dt) {
            return '-';
        }
        $c = $dt instanceof \Carbon\CarbonInterface ? $dt : \Illuminate\Support\Carbon::parse($dt);

        return $c->locale('id')->translatedFormat($format);
    }
}

if (! function_exists('device_label')) {
    /** "Android · Chrome · Aplikasi" untuk kartu monitoring. */
    function device_label(?array $info): string
    {
        if (! $info) {
            return '-';
        }
        $os = ['android' => 'Android', 'ios' => 'iPhone', 'ipados' => 'iPad', 'harmony' => 'HarmonyOS', 'harmony_next' => 'HarmonyOS NEXT', 'windows' => 'Windows', 'macos' => 'macOS', 'chromeos' => 'ChromeOS', 'linux' => 'Linux'][$info['os'] ?? ''] ?? ($info['os'] ?? '?');
        $br = \App\Support\ExamSettings::BROWSERS[$info['browser'] ?? ''] ?? ucfirst((string) ($info['browser'] ?? '?'));
        $mode = ! empty($info['standalone']) ? 'Aplikasi' : 'Browser';

        return "{$os} · {$br} · {$mode}";
    }
}
