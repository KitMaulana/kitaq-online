/**
 * Guard ekstensi / add-on AI dan elemen yang disuntikkan ke halaman.
 *
 * Browser HP yang mendukung ekstensi: Safari iOS, Firefox Android, Samsung Internet (add-on),
 * sebagian browser turunan Chromium. Ekstensi AI (pembaca halaman, "tanya AI", grammar,
 * terjemah) biasanya menyuntikkan elemen/iframe/style ke DOM. Guard ini mencari:
 *  1. Tanda tangan ekstensi AI yang dikenal (daftar dari config server)  → 'ai_extension_detected'
 *  2. Elemen asing di <html>/<body>, custom element bukan milik KITAQ, iframe,
 *     style/script tanpa penanda KITAQ                                     → 'dom_injection'
 *  3. Sumber daya bertipe chrome-extension:// / moz-extension:// / safari-web-extension://
 *
 * Semua elemen milik KITAQ wajib diberi atribut `data-kq` (atau berada di dalam root ujian).
 * Halaman ujian sengaja TIDAK memakai Livewire/skrip pihak ketiga agar daftar putih bersih.
 */
const EXT_URL = /^(chrome|moz|safari-web|ms-browser|edge)-extension:\/\//i;

export function extensionGuard(ctx) {
  const signatures = ctx.config.aiSignatures || [];
  const ownTags = /^(kq-|template$)/i;
  const reported = new Set();
  let obs = null;
  let iv = null;

  function report(type, key, detail) {
    if (reported.has(key)) return;
    reported.add(key);
    ctx.report(type, detail);
  }

  function isOwn(el) {
    return el.hasAttribute && (el.hasAttribute('data-kq') || (ctx.root && ctx.root.contains(el) && !el.matches('iframe')));
  }

  function inspect(el) {
    if (!el || el.nodeType !== 1) return;
    for (const sel of signatures) {
      try { if (el.matches(sel) || el.querySelector(sel)) return report('ai_extension_detected', 'sig:' + sel, { selector: sel }); }
      catch (e) { /* selector tidak valid → lewati */ }
    }
    const tag = el.tagName.toLowerCase();
    const src = el.getAttribute('src') || el.getAttribute('href') || '';
    if (EXT_URL.test(src)) return report('ai_extension_detected', 'url:' + src.slice(0, 60), { url: src.slice(0, 120) });
    if (isOwn(el)) return;
    if (tag === 'iframe') return report('dom_injection', 'iframe', { tag });
    if (tag.includes('-') && !ownTags.test(tag)) return report('dom_injection', 'ce:' + tag, { tag });
    if ((tag === 'style' || tag === 'link' || tag === 'script') && !el.hasAttribute('nonce') && !el.hasAttribute('data-kq')) {
      return report('dom_injection', 'res:' + tag, { tag });
    }
    if (el.parentNode === document.body || el.parentNode === document.documentElement) {
      if (!['script', 'style', 'link', 'noscript'].includes(tag)) report('dom_injection', 'node:' + tag, { tag, id: el.id || '', cls: String(el.className || '').slice(0, 60) });
    }
    if (el.shadowRoot && !isOwn(el)) report('dom_injection', 'shadow:' + tag, { tag });
  }

  function scanAll() {
    document.querySelectorAll('body > *, html > *:not(head):not(body), head > *, iframe, *[src], *[href]').forEach(inspect);
    for (const sel of signatures) {
      try { if (document.querySelector(sel)) report('ai_extension_detected', 'sig:' + sel, { selector: sel }); } catch (e) { /* lewati */ }
    }
    try {
      performance.getEntriesByType('resource').forEach((r) => { if (EXT_URL.test(r.name)) report('ai_extension_detected', 'res:' + r.name.slice(0, 60), { url: r.name.slice(0, 120) }); });
    } catch (e) { /* lewati */ }
  }

  return {
    start() {
      // Tandai semua yang sudah ada saat halaman dimuat dari server sebagai milik KITAQ,
      // KECUALI yang cocok dengan tanda tangan AI (ekstensi bisa menyuntik sebelum skrip kita jalan).
      document.querySelectorAll('head > *, body > *').forEach((el) => {
        if (!signatures.some((s) => { try { return el.matches(s); } catch (e) { return false; } })) el.setAttribute('data-kq', '');
      });
      scanAll();
      obs = new MutationObserver((muts) => {
        if (!ctx.isActive()) return;
        for (const m of muts) m.addedNodes.forEach(inspect);
      });
      obs.observe(document.documentElement, { childList: true, subtree: true });
      iv = setInterval(() => ctx.isActive() && scanAll(), 5000);
    },
    stop() { if (obs) obs.disconnect(); clearInterval(iv); },
  };
}
