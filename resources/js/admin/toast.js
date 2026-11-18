/**
 * Toast kanan-atas (aria-live), hilang 4 detik. Sumber:
 *  - Livewire: $this->dispatch('toast', type: 'success', title: '...', text: '...')  (trait WithToast)
 *  - session flash 'success' | 'error' | 'warning' | 'status' (dirender layout ke data-flash)
 *  - JS: window.kqToast('success', 'Judul', 'teks')
 */
const TONES = { success: 'var(--success)', error: 'var(--accent)', danger: 'var(--accent)', warning: 'var(--warning)', info: 'var(--info)' };
const ICONS = { success: 'checkc', error: 'warn', danger: 'warn', warning: 'warn', info: 'bell' };

export function registerToast(Alpine) {
  Alpine.store('toasts', {
    items: [],
    push(type, title, text = '', timeout = 4000) {
      const id = Date.now() + Math.random();
      this.items.push({ id, type, title, text, color: TONES[type] || TONES.info, icon: ICONS[type] || 'bell' });
      if (timeout) setTimeout(() => this.remove(id), timeout);
    },
    remove(id) { this.items = this.items.filter((t) => t.id !== id); },
  });

  window.kqToast = (type, title, text) => Alpine.store('toasts').push(type, title, text);

  window.addEventListener('toast', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    if (d) Alpine.store('toasts').push(d.type || 'info', d.title || '', d.text || '');
  });

  document.addEventListener('alpine:initialized', () => {
    document.querySelectorAll('[data-flash]').forEach((el) => {
      Alpine.store('toasts').push(el.dataset.type || 'info', el.dataset.flash, el.dataset.text || '', 6000);
    });
  });
}
