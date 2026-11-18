/**
 * Guard terjemahan & integritas konten soal.
 *
 * Pencegahan (dipasang juga di Blade):
 *   <html lang="id" translate="no" class="notranslate">
 *   <meta name="google" content="notranslate">
 *
 * Deteksi (jika siswa tetap memaksa terjemahan atau ada yang mengubah teks soal):
 *  - Chrome/Google Translate: menambah kelas `translated-ltr|rtl` di <html>, menyisipkan <font>.
 *  - Edge/Microsoft Translator: atribut `_msttexthash` / `_msthash`.
 *  - Safari, browser lain, ekstensi AI yang menulis ulang teks: ditangkap oleh
 *    pemeriksaan sidik jari (hash) teks soal yang dibandingkan dengan aslinya.
 */
export function translateGuard(ctx) {
  let htmlObs = null;
  let rootObs = null;
  let iv = null;
  const baseline = new WeakMap();
  const protectedEls = new Set();
  let fired = false;

  function fnv1a(str) {
    let h = 0x811c9dc5;
    for (let i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 0x01000193); }
    return (h >>> 0).toString(16);
  }

  function fire(type, detail) {
    if (fired && type === 'translate_detected') return;
    if (type === 'translate_detected') fired = true;
    ctx.report(type, detail);
  }

  function checkHtml() {
    const el = document.documentElement;
    if (/\btranslated-(ltr|rtl)\b/.test(el.className)) return fire('translate_detected', { by: 'html_class', cls: el.className });
    const lang = (el.getAttribute('lang') || '').toLowerCase();
    if (lang && !lang.startsWith(ctx.config.lang || 'id')) return fire('translate_detected', { by: 'lang', lang });
    if (el.getAttribute('translate') !== 'no') el.setAttribute('translate', 'no');
  }

  function checkIntegrity() {
    protectedEls.forEach((node) => {
      if (!node.isConnected) { protectedEls.delete(node); return; }
      const h = fnv1a(node.textContent || '');
      if (baseline.get(node) !== h) {
        const translated = node.querySelector('font, [_msttexthash], [_msthash], .goog-text-highlight');
        fire(translated ? 'translate_detected' : 'content_tampered', { by: 'hash', id: node.dataset.kqId || '' });
        baseline.set(node, h); // hindari laporan berulang untuk perubahan yang sama
      }
    });
  }

  return {
    /** Daftarkan elemen teks soal/opsi setelah dirender. Panggil ulang setiap ganti soal. */
    protect(nodes) {
      protectedEls.clear();
      nodes.forEach((n) => { baseline.set(n, fnv1a(n.textContent || '')); protectedEls.add(n); });
    },
    start() {
      document.documentElement.setAttribute('translate', 'no');
      document.documentElement.classList.add('notranslate');
      htmlObs = new MutationObserver(checkHtml);
      htmlObs.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'lang', 'translate'] });
      const root = ctx.root || document.body;
      rootObs = new MutationObserver((muts) => {
        for (const m of muts) {
          for (const n of m.addedNodes) {
            if (n.nodeType === 1 && (n.tagName === 'FONT' || n.hasAttribute('_msttexthash') || /\bgoog-/.test(n.className || ''))) {
              fire('translate_detected', { by: 'node', tag: n.tagName });
            }
          }
        }
        checkIntegrity();
      });
      rootObs.observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['_msttexthash', '_msthash'] });
      iv = setInterval(() => { checkHtml(); checkIntegrity(); }, 3000);
      checkHtml();
    },
    stop() { if (htmlObs) htmlObs.disconnect(); if (rootObs) rootObs.disconnect(); clearInterval(iv); },
  };
}
