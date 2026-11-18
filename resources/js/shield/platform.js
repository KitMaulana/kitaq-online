/**
 * KITAQ Shield — deteksi platform & kemampuan browser.
 * Dipakai untuk memilih aturan per platform (Android, iOS/iPadOS, HarmonyOS, desktop).
 * Catatan: User-Agent bisa dipalsukan. Hasil ini untuk menyesuaikan perilaku dan
 * pencatatan, BUKAN satu-satunya dasar keputusan keamanan (server tetap memeriksa).
 */

export function detectPlatform(nav = navigator, win = window) {
  const ua = nav.userAgent || '';
  const touch = (nav.maxTouchPoints || 0) > 1;

  let os = 'other';
  if (/OpenHarmony|ArkWeb/i.test(ua)) os = 'harmony_next';          // HarmonyOS NEXT / 5+ (ArkWeb, berbasis Chromium)
  else if (/HarmonyOS/i.test(ua)) os = 'harmony';                    // HarmonyOS 2–4 (kompatibel Android)
  else if (/Android/i.test(ua)) os = 'android';
  else if (/iPhone|iPod/i.test(ua)) os = 'ios';
  else if (/iPad/i.test(ua) || (/Macintosh/i.test(ua) && touch)) os = 'ipados'; // iPadOS menyamar sebagai Mac
  else if (/Windows/i.test(ua)) os = 'windows';
  else if (/CrOS/i.test(ua)) os = 'chromeos';
  else if (/Macintosh/i.test(ua)) os = 'macos';
  else if (/Linux/i.test(ua)) os = 'linux';

  let browser = 'other';
  if (/; wv\)|FBAN|FBAV|Instagram|Line\/|WhatsApp|TikTok|Snapchat/i.test(ua)) browser = 'webview';
  else if (/EdgA|EdgiOS|Edg\//i.test(ua)) browser = 'edge';
  else if (/OPR\/|OPT\/|OPX\/|Opera/i.test(ua)) browser = 'opera';
  else if (/SamsungBrowser/i.test(ua)) browser = 'samsung';
  else if (/HuaweiBrowser|HBPC|HMSCore/i.test(ua)) browser = 'huawei';
  else if (/YaBrowser/i.test(ua)) browser = 'yandex';
  else if (/UCBrowser|UCWEB/i.test(ua)) browser = 'uc';
  else if (/MiuiBrowser|XiaoMi/i.test(ua)) browser = 'miui';
  else if (/Firefox|FxiOS/i.test(ua)) browser = 'firefox';
  else if (/DuckDuckGo/i.test(ua)) browser = 'duckduckgo';
  else if (nav.brave && typeof nav.brave.isBrave === 'function') browser = 'brave';
  else if (/CriOS/i.test(ua)) browser = 'chrome';                    // Chrome iOS (mesin WebKit)
  else if (/Chrome\//i.test(ua)) browser = 'chrome';
  else if (/Safari\//i.test(ua) && /Version\//i.test(ua)) browser = 'safari';

  const mobile = ['android', 'harmony', 'harmony_next', 'ios'].includes(os);
  const tablet = os === 'ipados' || (os === 'android' && !/Mobile/i.test(ua));
  const device = tablet ? 'tablet' : mobile ? 'phone' : 'desktop';

  const doc = win.document;
  const standalone =
    nav.standalone === true ||
    (win.matchMedia && (win.matchMedia('(display-mode: standalone)').matches ||
                        win.matchMedia('(display-mode: fullscreen)').matches));

  return {
    os, browser, device,
    isMobileLike: device !== 'desktop',
    isApple: os === 'ios' || os === 'ipados',
    isHarmony: os === 'harmony' || os === 'harmony_next',
    standalone: !!standalone,
    caps: {
      fullscreen: !!(doc.fullscreenEnabled || doc.webkitFullscreenEnabled) && os !== 'ios', // iPhone tidak mendukung fullscreen elemen
      broadcastChannel: typeof win.BroadcastChannel === 'function',
      visualViewport: !!win.visualViewport,
      vibrate: typeof nav.vibrate === 'function',
      beacon: typeof nav.sendBeacon === 'function',
      screenExtended: 'isExtended' in (win.screen || {}),
    },
    ua,
  };
}

/** Cocokkan platform dengan daftar browser yang diizinkan (dari config server). */
export function isBrowserAllowed(platform, allowList = ['chrome', 'safari', 'samsung', 'huawei']) {
  if (platform.browser === 'webview') return false;
  return allowList.includes(platform.browser);
}
