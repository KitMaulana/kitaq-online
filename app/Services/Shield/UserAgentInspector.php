<?php

namespace App\Services\Shield;

/**
 * Baca User-Agent di sisi server (cermin resources/js/shield/platform.js).
 * Dipakai untuk: log perangkat, gerbang browser yang diizinkan saat login/mulai ujian,
 * dan deteksi "satu siswa dua perangkat" (os/browser berbeda pada heartbeat).
 * UA bisa dipalsukan → ini lapisan tambahan, bukan satu-satunya pengaman.
 */
final class UserAgentInspector
{
    /**
     * @return array{os:string,browser:string,device:string,webview:bool}
     */
    public static function inspect(string $ua): array
    {
        $os = match (true) {
            (bool) preg_match('/OpenHarmony|ArkWeb/i', $ua) => 'harmony_next',
            (bool) preg_match('/HarmonyOS/i', $ua) => 'harmony',
            (bool) preg_match('/Android/i', $ua) => 'android',
            (bool) preg_match('/iPhone|iPod/i', $ua) => 'ios',
            (bool) preg_match('/iPad/i', $ua) => 'ipados',
            (bool) preg_match('/Windows/i', $ua) => 'windows',
            (bool) preg_match('/CrOS/i', $ua) => 'chromeos',
            (bool) preg_match('/Macintosh/i', $ua) => 'macos', // iPadOS modern juga muncul sebagai Mac; klien mengoreksi via maxTouchPoints
            (bool) preg_match('/Linux/i', $ua) => 'linux',
            default => 'other',
        };

        $webview = (bool) preg_match('/; wv\)|FBAN|FBAV|Instagram|Line\/|WhatsApp|TikTok|Snapchat/i', $ua);

        $browser = match (true) {
            $webview => 'webview',
            (bool) preg_match('/EdgA|EdgiOS|Edg\//i', $ua) => 'edge',
            (bool) preg_match('/OPR\/|OPT\/|OPX\/|Opera/i', $ua) => 'opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'samsung',
            (bool) preg_match('/HuaweiBrowser|HBPC|HMSCore/i', $ua) => 'huawei',
            (bool) preg_match('/YaBrowser/i', $ua) => 'yandex',
            (bool) preg_match('/UCBrowser|UCWEB/i', $ua) => 'uc',
            (bool) preg_match('/MiuiBrowser|XiaoMi/i', $ua) => 'miui',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'firefox',
            (bool) preg_match('/DuckDuckGo/i', $ua) => 'duckduckgo',
            (bool) preg_match('/CriOS|Chrome\//i', $ua) => 'chrome',
            (bool) preg_match('/Version\/[\d.]+.*Safari\//i', $ua) => 'safari',
            default => 'other',
        };

        $mobile = in_array($os, ['android', 'harmony', 'harmony_next', 'ios'], true);
        $tablet = $os === 'ipados' || ($os === 'android' && ! preg_match('/Mobile/i', $ua));
        $device = $tablet ? 'tablet' : ($mobile ? 'phone' : 'desktop');

        return ['os' => $os, 'browser' => $browser, 'device' => $device, 'webview' => $webview];
    }

    /**
     * @param array{browser:string,webview:bool} $info
     * @param list<string> $allow
     */
    public static function isAllowed(array $info, array $allow): bool
    {
        return ! $info['webview'] && in_array($info['browser'], $allow, true);
    }
}
