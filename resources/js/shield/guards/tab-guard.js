/**
 * Guard multi-tab / dua jendela ujian di browser yang sama.
 * - BroadcastChannel: tab baru menyapa; tab lama menjawab → tab baru diblokir.
 * - Cadangan localStorage (browser lama): kunci dengan detak waktu.
 * Dua jendela di BROWSER/PERANGKAT BERBEDA ditangkap di server lewat instance_id
 * pada heartbeat (lihat docs/ANTI_CONTEK.md, "Satu sesi aktif").
 */
export function tabGuard(ctx) {
  const name = 'kitaq-exam';
  const lockKey = 'kitaq:tablock';
  const me = ctx.instanceId;
  let ch = null;
  let iv = null;
  let duplicate = false;

  function markDuplicate(other) {
    if (duplicate) return;
    duplicate = true;
    ctx.report('multi_tab', { other });
    ctx.showBlocker('multitab');
  }

  return {
    start() {
      if (ctx.platform.caps.broadcastChannel) {
        ch = new BroadcastChannel(name);
        ch.onmessage = (e) => {
          const m = e.data || {};
          if (m.from === me) return;
          if (m.type === 'hello') ch.postMessage({ type: 'exists', from: me, to: m.from });
          if (m.type === 'exists' && m.to === me) markDuplicate(m.from);
        };
        ch.postMessage({ type: 'hello', from: me });
      }
      // Cadangan localStorage
      const now = Date.now();
      try {
        const cur = JSON.parse(localStorage.getItem(lockKey) || 'null');
        if (cur && cur.id !== me && now - cur.t < 6000) markDuplicate(cur.id);
      } catch (e) { /* abaikan */ }
      iv = setInterval(() => {
        if (duplicate) return;
        try { localStorage.setItem(lockKey, JSON.stringify({ id: me, t: Date.now() })); } catch (e) { /* abaikan */ }
      }, 2000);
    },
    stop() {
      clearInterval(iv);
      if (ch) ch.close();
      try { const cur = JSON.parse(localStorage.getItem(lockKey) || 'null'); if (cur && cur.id === me) localStorage.removeItem(lockKey); } catch (e) { /* abaikan */ }
    },
    isDuplicate: () => duplicate,
  };
}
