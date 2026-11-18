/** Tema terang/gelap: default mengikuti sistem, pilihan pengguna disimpan (DESIGN_SYSTEM §7). */
export function applyStoredTheme() {
  let t = null;
  try { t = localStorage.getItem('kq:theme'); } catch (e) { /* abaikan */ }
  if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.dataset.theme = t;
  return t;
}

export function registerTheme(Alpine) {
  Alpine.store('theme', {
    value: document.documentElement.dataset.theme || applyStoredTheme(),
    toggle() {
      this.value = this.value === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = this.value;
      try { localStorage.setItem('kq:theme', this.value); } catch (e) { /* abaikan */ }
      window.dispatchEvent(new CustomEvent('kq:theme', { detail: this.value }));
    },
  });
}
