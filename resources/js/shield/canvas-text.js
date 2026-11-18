/**
 * Mode Soal Kanvas (opsional per ujian, "Ketat+").
 * Teks soal/opsi digambar ke <canvas>, bukan teks DOM. Akibatnya:
 *  - fitur Terjemahkan browser, ekstensi "baca halaman"/AI, dan seleksi teks tidak bisa
 *    membaca soal karena tidak ada teks di halaman;
 *  - teks tetap terbaca jelas oleh siswa (dirender tajam sesuai devicePixelRatio).
 * Batasan: pembaca layar (aksesibilitas) tidak bisa membacanya → sediakan pengecualian
 * per siswa (akomodasi) di pengaturan peserta. Tangkapan layar/OCR tetap mungkin.
 * Rumus matematika: render KaTeX → SVG → gambar, lalu gambar di kanvas (lihat spesifikasi).
 */
export function drawText(canvas, text, opts = {}) {
  const css = getComputedStyle(canvas);
  const font = opts.font || `${css.fontWeight || 400} ${opts.size || 17}px Inter, system-ui, sans-serif`;
  const lineH = opts.lineHeight || Math.round((opts.size || 17) * 1.6);
  const color = opts.color || css.color || '#0f172a';
  const width = canvas.parentElement.clientWidth;
  const dpr = Math.min(window.devicePixelRatio || 1, 3);
  const ctx = canvas.getContext('2d');
  ctx.font = font;

  const lines = [];
  String(text).split('\n').forEach((para) => {
    const words = para.split(/\s+/);
    let line = '';
    words.forEach((w) => {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > width && line) { lines.push(line); line = w; } else line = test;
    });
    lines.push(line);
  });

  canvas.width = Math.ceil(width * dpr);
  canvas.height = Math.ceil(lines.length * lineH * dpr);
  canvas.style.width = width + 'px';
  canvas.style.height = lines.length * lineH + 'px';
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.font = font;
  ctx.fillStyle = color;
  ctx.textBaseline = 'top';
  lines.forEach((l, i) => ctx.fillText(l, 0, i * lineH + (lineH - (opts.size || 17)) / 2));
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', opts.ariaLabel || 'Teks soal');
  return lines.length;
}

/** Gambar ulang saat lebar berubah (rotasi layar). */
export function autoRedraw(canvas, text, opts) {
  drawText(canvas, text, opts);
  if (!window.ResizeObserver) return () => {};
  let w = canvas.parentElement.clientWidth;
  const ro = new ResizeObserver(() => {
    const nw = canvas.parentElement.clientWidth;
    if (Math.abs(nw - w) > 2) { w = nw; drawText(canvas, text, opts); }
  });
  ro.observe(canvas.parentElement);
  return () => ro.disconnect();
}
