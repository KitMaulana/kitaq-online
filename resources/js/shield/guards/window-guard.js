/**
 * Guard jendela: layar terpisah (split screen), jendela mengambang (pop-up view),
 * Slide Over/Split View iPad, multi-monitor, jendela desktop tidak dimaksimalkan.
 *
 * Kuncinya: halaman ujian TIDAK pernah membuka keyboard bawaan HP (diganti keyboard
 * virtual KITAQ), sehingga tinggi viewport hanya berubah karena bilah alamat browser
 * (kecil) atau karena split screen/jendela mengambang (besar). Perubahan besar = curiga.
 *
 * Ambang default (bisa diubah di config):
 *  - lebar < 80% lebar layar  → split kiri-kanan / jendela mengambang
 *  - tinggi < 62% tinggi layar → split atas-bawah
 * Rotasi layar mereset patokan.
 */
export function windowGuard(ctx) {
  const minW = ctx.config.splitMinWidthRatio ?? 0.8;
  const minH = ctx.config.splitMinHeightRatio ?? 0.62;
  const p = ctx.platform;
  let timer = null;
  let inSplit = false;
  let lastOrientation = orientationKey();
  let extendedReported = false;

  function orientationKey() {
    return (screen.orientation && screen.orientation.type) || (window.innerWidth > window.innerHeight ? 'landscape' : 'portrait');
  }

  function screenSize() {
    // screen.width/height dalam CSS px; tukar bila orientasi tidak cocok (beberapa browser lama).
    let sw = screen.width, sh = screen.height;
    const landscape = window.innerWidth > window.innerHeight;
    if ((landscape && sw < sh) || (!landscape && sw > sh)) { const t = sw; sw = sh; sh = t; }
    return { sw, sh };
  }

  /** @returns {null|{reason:string, ratioW:number, ratioH:number}} */
  function measure() {
    const { sw, sh } = screenSize();
    const vv = window.visualViewport;
    // Abaikan saat pengguna mencubit-zoom (visualViewport mengecil, layout viewport tetap).
    if (vv && vv.scale > 1.05) return null;
    const ratioW = window.innerWidth / sw;
    const ratioH = window.innerHeight / sh;
    if (p.isMobileLike) {
      if (ratioW < minW) return { reason: 'width', ratioW, ratioH };
      if (ratioH < minH) return { reason: 'height', ratioW, ratioH };
      return null;
    }
    // Desktop/laptop: jendela harus hampir penuh (bukan dibagi dua dengan aplikasi lain).
    const aw = screen.availWidth || sw, ah = screen.availHeight || sh;
    if (window.outerWidth < aw * 0.9 || window.outerHeight < ah * 0.85) return { reason: 'desktop_window', ratioW, ratioH };
    return null;
  }

  function check(source) {
    if (!ctx.isActive()) return;
    const o = orientationKey();
    if (o !== lastOrientation) { lastOrientation = o; inSplit = false; }
    const m = measure();
    if (m && !inSplit) {
      inSplit = true;
      ctx.report('split_screen', { ...m, source, iw: window.innerWidth, ih: window.innerHeight });
      ctx.showBlocker('split');
    } else if (!m && inSplit) {
      inSplit = false;
      ctx.report('split_screen_end', { source });
      ctx.hideBlocker('split');
    }
    if (p.caps.screenExtended && screen.isExtended && !extendedReported) {
      extendedReported = true;
      ctx.report('screen_extended', {});
    }
  }

  const onResize = () => { clearTimeout(timer); timer = setTimeout(() => check('resize'), 450); };

  return {
    /** Dipanggil sebelum ujian dimulai: tolak mulai jika sedang split screen. */
    precheck() { const m = measure(); return m ? { ok: false, reason: m.reason } : { ok: true }; },
    start() {
      window.addEventListener('resize', onResize);
      if (window.visualViewport) window.visualViewport.addEventListener('resize', onResize);
      window.addEventListener('orientationchange', onResize);
      this._iv = setInterval(() => check('poll'), 2000);
      check('start');
    },
    stop() {
      window.removeEventListener('resize', onResize);
      if (window.visualViewport) window.visualViewport.removeEventListener('resize', onResize);
      window.removeEventListener('orientationchange', onResize);
      clearInterval(this._iv); clearTimeout(timer);
    },
  };
}
