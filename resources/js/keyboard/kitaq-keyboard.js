/**
 * Keyboard Virtual KITAQ + Kotak Jawaban KITAQ.
 *
 * Tujuan: saat soal terbuka, keyboard bawaan HP (Gboard, SwiftKey, Samsung Keyboard,
 * keyboard iOS, keyboard Huawei/Celia — termasuk fitur AI, tulis-ulang, saran, dikte,
 * dan papan klip) TIDAK PERNAH muncul.
 *
 * Caranya: halaman ujian tidak memakai <input>, <textarea>, atau contenteditable.
 * Kotak jawaban adalah <div role="textbox"> biasa yang isinya dikelola JavaScript,
 * dan huruf dimasukkan lewat tombol-tombol keyboard KITAQ di bawah layar. Karena tidak ada
 * field teks, sistem operasi tidak punya alasan membuka keyboard-nya. Cara ini sama
 * di Android, iOS/iPadOS, dan HarmonyOS karena hanya memakai HTML/CSS/JS standar.
 *
 * Tanpa koreksi otomatis, tanpa prediksi, tanpa tempel. Tekan lama ⌫ untuk hapus cepat.
 */

const SPECIAL = new Set(['@shift', '@bksp', '@space', '@enter', '@left', '@right', '@123', '@abc', '@sym', '@done']);

export const LAYOUTS = {
  abc: [
    ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
    ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
    ['@shift', 'z', 'x', 'c', 'v', 'b', 'n', 'm', '@bksp'],
    ['@123', ',', '@left', '@space', '@right', '.', '@enter'],
  ],
  num: [
    ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
    ['-', '/', ':', ';', '(', ')', '&', '@', '"', "'"],
    ['@sym', '.', ',', '?', '!', '%', '+', '=', '@bksp'],
    ['@abc', '@left', '@space', '@right', '@enter'],
  ],
  sym: [
    ['[', ']', '{', '}', '#', '^', '*', '<', '>', '~'],
    ['_', '\\', '|', '$', '°', '•', '…', '“', '”', '–'],
    ['@123', '√', 'π', '²', '³', '±', '×', '÷', '@bksp'],
    ['@abc', '≤', '≥', '≠', '@space', '½', '@enter'],
  ],
  token: [
    ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
    ['J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R'],
    ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
    ['2', '3', '4', '5', '6', '7', '8', '9'],
    ['@left', '@bksp', '@right'],
  ],
  pad: [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    [',', '0', '-'],
    ['@left', '@bksp', '@right'],
  ],
};

const LABEL = {
  '@shift': '⇧', '@bksp': '⌫', '@space': 'spasi', '@enter': '↵', '@left': '‹', '@right': '›',
  '@123': '?123', '@abc': 'ABC', '@sym': '=\\<', '@done': 'Selesai',
};
const ARIA = {
  '@shift': 'Huruf besar', '@bksp': 'Hapus', '@space': 'Spasi', '@enter': 'Baris baru', '@left': 'Kursor ke kiri',
  '@right': 'Kursor ke kanan', '@123': 'Angka dan tanda baca', '@abc': 'Huruf', '@sym': 'Simbol', '@done': 'Tutup keyboard',
};

/* ------------------------------------------------------------------ */
/* Kotak jawaban                                                       */
/* ------------------------------------------------------------------ */
export class KitaqTextbox {
  /**
   * @param {HTMLElement} el
   * @param {{value?:string,maxLength?:number,multiline?:boolean,mode?:'text'|'numeric'|'token',placeholder?:string,onChange?:(v:string)=>void,onActivate?:(tb:KitaqTextbox)=>void}} opts
   */
  constructor(el, opts = {}) {
    this.el = el;
    this.value = opts.value || '';
    this.maxLength = opts.maxLength || 2000;
    this.multiline = !!opts.multiline;
    this.mode = opts.mode || 'text';
    this.placeholder = opts.placeholder || 'Ketuk di sini untuk menjawab';
    this.onChange = opts.onChange || (() => {});
    this.onActivate = opts.onActivate || (() => {});
    this.caret = this.value.length;
    this.active = false;

    el.classList.add('kq-textbox');
    if (this.multiline) el.classList.add('multiline');
    if (this.mode === 'token') el.classList.add('token');
    el.setAttribute('role', 'textbox');
    el.setAttribute('aria-multiline', String(this.multiline));
    el.setAttribute('aria-readonly', 'false');
    el.setAttribute('tabindex', '-1');      // tidak bisa difokus lewat Tab → tidak memicu keyboard OS
    el.setAttribute('translate', 'no');
    el.removeAttribute('contenteditable');
    el.addEventListener('pointerdown', (e) => this._onPointer(e));
    this.render();
  }

  _onPointer(e) {
    e.preventDefault();
    const span = e.target.closest('[data-i]');
    if (span) {
      const r = span.getBoundingClientRect();
      const i = +span.dataset.i;
      this.caret = e.clientX > r.left + r.width / 2 ? i + 1 : i;
    } else {
      this.caret = this.value.length;
    }
    this.activate();
  }

  activate() { this.active = true; this.el.classList.add('active'); this.render(); this.onActivate(this); }
  deactivate() { this.active = false; this.el.classList.remove('active'); this.render(); }

  insert(str) {
    if (str === '\n' && !this.multiline) return;
    if (this.mode === 'numeric' && !/^[0-9,.\-]$/.test(str)) return;
    if (this.mode === 'token' && !/^[A-HJ-NP-Z2-9]$/.test(str)) return;
    if (this.value.length + str.length > this.maxLength) return;
    this.value = this.value.slice(0, this.caret) + str + this.value.slice(this.caret);
    this.caret += str.length;
    this._changed();
  }
  backspace() {
    if (this.caret === 0) return;
    // hapus satu karakter (termasuk pasangan surrogate emoji)
    const before = Array.from(this.value.slice(0, this.caret));
    before.pop();
    const b = before.join('');
    this.value = b + this.value.slice(this.caret);
    this.caret = b.length;
    this._changed();
  }
  move(delta) { this.caret = Math.max(0, Math.min(this.value.length, this.caret + delta)); this.render(); }
  setValue(v) { this.value = String(v || '').slice(0, this.maxLength); this.caret = this.value.length; this.render(); }

  _changed() { this.render(); this.onChange(this.value); }

  render() {
    const frag = document.createDocumentFragment();
    if (!this.value && !this.active) {
      const ph = document.createElement('span');
      ph.className = 'kq-ph';
      ph.textContent = this.placeholder;
      frag.appendChild(ph);
    }
    const chars = this.value;
    for (let i = 0; i <= chars.length; i++) {
      if (i === this.caret && this.active) {
        const c = document.createElement('span');
        c.className = 'kq-caret';
        c.setAttribute('aria-hidden', 'true');
        frag.appendChild(c);
      }
      if (i < chars.length) {
        const s = document.createElement('span');
        s.dataset.i = String(i);
        s.textContent = chars[i];
        frag.appendChild(s);
      }
    }
    this.el.replaceChildren(frag);
    this.el.setAttribute('aria-label', this.value ? `Jawaban: ${this.value}` : this.placeholder);
    const counter = this.el.parentElement && this.el.parentElement.querySelector('[data-kq-count]');
    if (counter) counter.textContent = `${this.value.length}/${this.maxLength}`;
  }
}

/* ------------------------------------------------------------------ */
/* Keyboard                                                            */
/* ------------------------------------------------------------------ */
export class KitaqKeyboard {
  /** @param {HTMLElement} mount  wadah keyboard (posisi tetap di bawah) */
  constructor(mount, opts = {}) {
    this.mount = mount;
    this.haptics = opts.haptics !== false && typeof navigator.vibrate === 'function';
    this.onHide = opts.onHide || (() => {});
    this.target = null;
    this.layout = 'abc';
    this.shift = 'off'; // off | once | caps
    this.lastShiftTap = 0;
    this.repeat = null;
    mount.classList.add('kq-kb');
    mount.setAttribute('role', 'group');
    mount.setAttribute('aria-label', 'Keyboard KITAQ');
    mount.setAttribute('data-kq', '');
    mount.setAttribute('translate', 'no');
    mount.addEventListener('pointerdown', (e) => this._down(e));
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((t) => mount.addEventListener(t, () => this._stopRepeat()));
    mount.addEventListener('contextmenu', (e) => e.preventDefault());
    this.hide();
  }

  attach(textbox) {
    if (this.target && this.target !== textbox) this.target.deactivate();
    this.target = textbox;
    this.layout = textbox.mode === 'numeric' ? 'pad' : textbox.mode === 'token' ? 'token' : 'abc';
    this.shift = 'off';
    this.render();
    this.show();
  }

  show() { this.mount.hidden = false; document.documentElement.classList.add('kq-kb-open'); }
  hide() {
    this.mount.hidden = true;
    document.documentElement.classList.remove('kq-kb-open');
    if (this.target) this.target.deactivate();
    this.onHide();
  }

  render() {
    const rows = LAYOUTS[this.layout];
    const wrap = document.createDocumentFragment();
    const bar = document.createElement('div');
    bar.className = 'kq-kb-bar';
    bar.innerHTML = '<span class="kq-kb-brand">Keyboard KITAQ</span>';
    const done = this._key('@done');
    bar.appendChild(done);
    wrap.appendChild(bar);
    rows.forEach((row) => {
      const r = document.createElement('div');
      r.className = 'kq-kb-row' + (this.layout === 'pad' || this.layout === 'token' ? ' kq-pad' : '');
      row.forEach((k) => r.appendChild(this._key(k)));
      wrap.appendChild(r);
    });
    this.mount.replaceChildren(wrap);
    this.mount.dataset.layout = this.layout;
  }

  _key(k) {
    const b = document.createElement('button');
    b.type = 'button';
    b.tabIndex = -1;
    b.dataset.k = k;
    const upper = this.shift !== 'off';
    if (SPECIAL.has(k)) {
      b.className = 'kq-key fn k-' + k.slice(1);
      b.textContent = LABEL[k];
      b.setAttribute('aria-label', ARIA[k]);
      if (k === '@shift') { b.classList.toggle('on', this.shift !== 'off'); b.classList.toggle('caps', this.shift === 'caps'); }
      if (k === '@enter' && this.target && !this.target.multiline) b.disabled = true;
    } else {
      b.className = 'kq-key';
      b.textContent = upper && this.layout === 'abc' ? k.toUpperCase() : k;
    }
    return b;
  }

  _down(e) {
    const btn = e.target.closest('.kq-key');
    e.preventDefault(); // cegah fokus/zoom/seleksi
    if (!btn || btn.disabled || !this.target) return;
    const k = btn.dataset.k;
    if (this.haptics) navigator.vibrate(8);
    btn.classList.add('pressed');
    setTimeout(() => btn.classList.remove('pressed'), 120);
    this.press(k);
    if (k === '@bksp' || k === '@left' || k === '@right') {
      this._stopRepeat();
      this.repeat = setTimeout(() => { this.repeat = setInterval(() => this.press(k), 60); }, 420);
    }
  }

  _stopRepeat() { clearTimeout(this.repeat); clearInterval(this.repeat); this.repeat = null; }

  press(k) {
    const t = this.target;
    switch (k) {
      case '@bksp': t.backspace(); break;
      case '@space': t.insert(' '); break;
      case '@enter': t.insert('\n'); break;
      case '@left': t.move(-1); break;
      case '@right': t.move(1); break;
      case '@123': this.layout = 'num'; this.render(); break;
      case '@sym': this.layout = 'sym'; this.render(); break;
      case '@abc': this.layout = 'abc'; this.render(); break;
      case '@done': this.hide(); break;
      case '@shift': {
        const now = Date.now();
        if (now - this.lastShiftTap < 350) this.shift = 'caps';
        else this.shift = this.shift === 'off' ? 'once' : 'off';
        this.lastShiftTap = now;
        this.render();
        break;
      }
      default: {
        const ch = this.shift !== 'off' && this.layout === 'abc' ? k.toUpperCase() : k;
        t.insert(ch);
        if (this.shift === 'once') { this.shift = 'off'; this.render(); }
      }
    }
  }

  /**
   * Laptop/PC: teruskan ketikan keyboard fisik ke kotak jawaban (jika pengaturan ujian mengizinkan).
   * @returns {boolean} true bila tombol dipakai.
   */
  handleDesktopKey(e) {
    if (!this.target || !this.target.active) return false;
    if (e.ctrlKey || e.metaKey || e.altKey) return false;
    const map = { Backspace: '@bksp', Enter: '@enter', ArrowLeft: '@left', ArrowRight: '@right', Escape: '@done' };
    if (map[e.key]) { this.press(map[e.key]); return true; }
    if (e.key && e.key.length === 1) { this.target.insert(e.key); return true; }
    return false;
  }
}
