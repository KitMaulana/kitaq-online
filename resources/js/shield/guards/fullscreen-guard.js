/**
 * Guard layar penuh & mode aplikasi.
 *
 * - Android, HarmonyOS, iPad, desktop: wajib layar penuh (Fullscreen API) saat ujian.
 *   Keluar dari layar penuh = pelanggaran 'fullscreen_exit' + layar penutup dengan tombol
 *   "Kembali ke layar penuh" (browser mensyaratkan ketukan pengguna).
 * - iPhone: Safari di iPhone TIDAK mendukung layar penuh untuk halaman web.
 *   Penggantinya: KITAQ ONLINE dipasang ke Layar Utama (Bagikan → Tambah ke Layar Utama)
 *   sehingga berjalan tanpa bilah alamat (mode standalone). Jika config
 *   iosRequireStandalone = true, ujian hanya bisa dimulai dari mode ini.
 */
export function fullscreenGuard(ctx) {
  const p = ctx.platform;
  const doc = document;
  const need = ctx.config.requireFullscreen !== false && p.caps.fullscreen;

  const isFs = () => !!(doc.fullscreenElement || doc.webkitFullscreenElement);

  const onChange = () => {
    if (!ctx.isActive() || !need) return;
    if (!isFs()) { ctx.report('fullscreen_exit', {}); ctx.showBlocker('fullscreen'); }
    else ctx.hideBlocker('fullscreen');
  };

  return {
    /** Syarat sebelum mulai. */
    precheck() {
      if (p.os === 'ios' && ctx.config.iosRequireStandalone !== false && !p.standalone) return { ok: false, reason: 'ios_standalone' };
      return { ok: true };
    },
    /** Harus dipanggil di dalam handler klik/ketuk (gesture pengguna). */
    async enter() {
      if (!need || isFs()) return true;
      const el = doc.documentElement;
      try {
        if (el.requestFullscreen) await el.requestFullscreen({ navigationUI: 'hide' });
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        return true;
      } catch (e) {
        ctx.report('fullscreen_denied', { msg: String(e && e.message || e).slice(0, 80) });
        return false;
      }
    },
    start() {
      doc.addEventListener('fullscreenchange', onChange);
      doc.addEventListener('webkitfullscreenchange', onChange);
    },
    stop() {
      doc.removeEventListener('fullscreenchange', onChange);
      doc.removeEventListener('webkitfullscreenchange', onChange);
      if (isFs() && doc.exitFullscreen) doc.exitFullscreen().catch(() => {});
    },
  };
}
