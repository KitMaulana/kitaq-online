/**
 * Guard fokus: pindah tab/aplikasi, jendela kehilangan fokus.
 *
 * Kehilangan fokus (blur) terjadi saat siswa:
 *  - mengetuk aplikasi lain di layar terpisah (split screen) atau jendela mengambang,
 *  - membuka overlay AI (mis. asisten di atas browser), menarik panel notifikasi/kontrol,
 *  - pindah aplikasi / tab (biasanya juga memicu visibilitychange).
 * Blur sangat singkat (< blurGraceMs) diabaikan untuk mengurangi alarm palsu.
 * Satu kejadian bisa memicu blur + hidden sekaligus; server menggabungkannya
 * (merge window) sehingga dihitung SATU pelanggaran.
 */
export function focusGuard(ctx) {
  const grace = ctx.config.blurGraceMs ?? 1200;
  let blurTimer = null;
  let blurAt = 0;
  let hiddenAt = 0;
  let poll = null;
  let reportedPollLoss = false;

  const onVisibility = () => {
    if (!ctx.isActive()) return;
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
      ctx.report('tab_hidden', {});
    } else if (hiddenAt) {
      ctx.report('tab_visible', { away_ms: Date.now() - hiddenAt });
      hiddenAt = 0;
    }
  };

  const onBlur = () => {
    if (!ctx.isActive()) return;
    blurAt = Date.now();
    clearTimeout(blurTimer);
    blurTimer = setTimeout(() => {
      blurTimer = null;
      if (!document.hasFocus()) { reportedPollLoss = true; ctx.report('window_blur', { grace_ms: grace }); }
    }, grace);
  };

  const onFocus = () => {
    clearTimeout(blurTimer);
    blurTimer = null;
    reportedPollLoss = false;
    if (blurAt && Date.now() - blurAt >= grace) ctx.report('window_focus', { away_ms: Date.now() - blurAt });
    blurAt = 0;
  };

  const onPageHide = () => {
    if (!ctx.isActive()) return;
    ctx.report('page_leave', {});
    ctx.flushBeacon();
  };

  return {
    start() {
      document.addEventListener('visibilitychange', onVisibility);
      window.addEventListener('blur', onBlur);
      window.addEventListener('focus', onFocus);
      window.addEventListener('pagehide', onPageHide);
      // Cadangan: sebagian browser tidak selalu memicu event blur (mis. jendela mengambang).
      poll = setInterval(() => {
        if (!ctx.isActive() || document.visibilityState !== 'visible') return;
        if (!document.hasFocus() && !reportedPollLoss && !blurTimer) {
          reportedPollLoss = true;
          ctx.report('window_blur', { source: 'poll' });
        }
        if (document.hasFocus()) reportedPollLoss = false;
      }, 1000);
    },
    stop() {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('blur', onBlur);
      window.removeEventListener('focus', onFocus);
      window.removeEventListener('pagehide', onPageHide);
      clearInterval(poll); clearTimeout(blurTimer);
    },
  };
}
