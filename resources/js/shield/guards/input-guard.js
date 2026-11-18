/**
 * Guard input: salin/tempel, seleksi teks, menu tekan-lama, pintasan keyboard,
 * keyboard fisik/Bluetooth, dan field teks asing.
 *
 * Mengapa penting di HP: menyeleksi teks memunculkan menu "Terjemahkan", "Telusuri",
 * "Tanya AI". Dengan seleksi dimatikan, menu itu tidak pernah muncul.
 *
 * Halaman ujian tidak punya <input>/<textarea>/contenteditable sama sekali (jawaban isian
 * diketik lewat keyboard virtual KITAQ), sehingga keyboard bawaan HP — termasuk keyboard
 * ber-AI — tidak pernah terbuka. Jika ada field teks muncul (mis. disuntik ekstensi)
 * lalu mendapat fokus, guard ini langsung melepas fokusnya dan mencatatnya.
 */
export function inputGuard(ctx) {
  const root = ctx.root || document.body;
  const allowDesktopTyping = ctx.config.physicalKeyboard?.desktop === 'allow';
  const mobileMode = ctx.config.physicalKeyboard?.mobile || 'block';
  let lastKbReport = 0;
  const handlers = [];

  function on(target, type, fn, opts) { target.addEventListener(type, fn, opts); handlers.push([target, type, fn, opts]); }
  const block = (type, evName) => on(document, evName, (e) => {
    if (!ctx.isActive()) return;
    e.preventDefault();
    ctx.report(type, {});
  }, true);

  function isEditable(el) {
    return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
  }

  return {
    start() {
      root.classList.add('kq-noselect');
      block('copy_attempt', 'copy');
      block('copy_attempt', 'cut');
      block('paste_attempt', 'paste');
      block('context_menu', 'contextmenu');
      on(document, 'selectstart', (e) => { if (ctx.isActive()) e.preventDefault(); }, true);
      on(document, 'dragstart', (e) => { if (ctx.isActive()) e.preventDefault(); }, true);
      on(document, 'drop', (e) => { if (ctx.isActive()) e.preventDefault(); }, true);

      on(document, 'focusin', (e) => {
        if (!ctx.isActive()) return;
        if (isEditable(e.target) && !e.target.closest('[data-kq-allow-input]')) {
          e.target.blur();
          ctx.report('foreign_input_focus', { tag: e.target.tagName });
        }
      }, true);

      on(document, 'beforeinput', (e) => {
        if (!ctx.isActive()) return;
        if (!e.target.closest || !e.target.closest('[data-kq-allow-input]')) { e.preventDefault(); ctx.report('ime_input', { type: e.inputType }); }
      }, true);

      on(document, 'keydown', (e) => {
        if (!ctx.isActive()) return;
        const combo = e.ctrlKey || e.metaKey || e.altKey;
        const k = (e.key || '').toLowerCase();
        // Pintasan berbahaya selalu diblokir (salin, cetak, simpan, cari, devtools, sumber).
        if (k === 'printscreen' || k === 'f12' || (combo && ['c', 'x', 'v', 'p', 's', 'u', 'f', 'a', 'i', 'j'].includes(k))) {
          e.preventDefault();
          ctx.report('shortcut_blocked', { key: k, combo });
          if (k === 'printscreen') ctx.flashBlank();
          return;
        }
        // Keyboard fisik/Bluetooth di HP/tablet → tidak boleh, karena bisa jadi jalan pintas.
        if (ctx.platform.isMobileLike && mobileMode === 'block') {
          e.preventDefault();
          if (Date.now() - lastKbReport > 10000) { lastKbReport = Date.now(); ctx.report('physical_keyboard', { key: k.length === 1 ? 'char' : k }); }
          return;
        }
        // Desktop: ketikan diteruskan ke kotak jawaban KITAQ bila diizinkan.
        if (!ctx.platform.isMobileLike && allowDesktopTyping && ctx.onDesktopKey) {
          if (ctx.onDesktopKey(e)) e.preventDefault();
        }
      }, true);

      on(document, 'keyup', (e) => { if (ctx.isActive() && (e.key || '').toLowerCase() === 'printscreen') ctx.flashBlank(); }, true);

      // Desktop: kursor keluar dari jendela (mis. ke monitor lain) → dicatat saja.
      if (!ctx.platform.isMobileLike) {
        on(document, 'mouseleave', () => ctx.isActive() && ctx.report('pointer_left', {}), false);
      }
    },
    stop() {
      handlers.forEach(([t, ty, fn, o]) => t.removeEventListener(ty, fn, o));
      handlers.length = 0;
      root.classList.remove('kq-noselect');
    },
  };
}
