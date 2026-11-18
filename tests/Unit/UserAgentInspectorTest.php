<?php

use App\Services\Shield\UserAgentInspector as UA;

it('mengenali platform dan browser umum', function (string $ua, string $os, string $browser, string $device) {
    $i = UA::inspect($ua);
    expect($i['os'])->toBe($os);
    expect($i['browser'])->toBe($browser);
    expect($i['device'])->toBe($device);
})->with([
    'Chrome Android' => ['Mozilla/5.0 (Linux; Android 14; SM-A155F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 'android', 'chrome', 'phone'],
    'Samsung Internet' => ['Mozilla/5.0 (Linux; Android 14; SM-A155F) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 'android', 'samsung', 'phone'],
    'Safari iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'ios', 'safari', 'phone'],
    'Chrome iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0.6723.90 Mobile/15E148 Safari/604.1', 'ios', 'chrome', 'phone'],
    'HarmonyOS NEXT' => ['Mozilla/5.0 (Phone; OpenHarmony 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1 Mobile HuaweiBrowser/5.0.4.300', 'harmony_next', 'huawei', 'phone'],
    'HarmonyOS 4' => ['Mozilla/5.0 (Linux; Android 12; HarmonyOS; NOH-AN00; HMSCore 6.13.0.302) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 HuaweiBrowser/14.0.6.300 Mobile Safari/537.36', 'harmony', 'huawei', 'phone'],
    'Edge Android' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 EdgA/130.0.2849.80', 'android', 'edge', 'phone'],
    'Opera Android' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 OPR/85.0.4341.75', 'android', 'opera', 'phone'],
    'Firefox Android' => ['Mozilla/5.0 (Android 14; Mobile; rv:132.0) Gecko/132.0 Firefox/132.0', 'android', 'firefox', 'phone'],
    'Tablet Android' => ['Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'android', 'chrome', 'tablet'],
    'Chrome Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'windows', 'chrome', 'desktop'],
]);

it('menandai webview aplikasi sebagai tidak diizinkan', function () {
    $i = UA::inspect('Mozilla/5.0 (Linux; Android 14; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/130.0.0.0 Mobile Safari/537.36 Instagram 350.0');
    expect($i['webview'])->toBeTrue();
    expect(UA::isAllowed($i, ['chrome', 'safari']))->toBeFalse();
});

it('memeriksa daftar browser yang diizinkan', function () {
    $edge = UA::inspect('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 EdgA/130.0');
    expect(UA::isAllowed($edge, ['chrome', 'safari', 'samsung', 'huawei']))->toBeFalse();
    $chrome = UA::inspect('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36');
    expect(UA::isAllowed($chrome, ['chrome', 'safari', 'samsung', 'huawei']))->toBeTrue();
});
