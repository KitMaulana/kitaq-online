/**
 * Sinkronisasi jawaban yang tahan sinyal putus-putus.
 * - Jawaban terakhir per soal disimpan di localStorage (satu soal = satu entri, yang terbaru menang).
 * - Dikirim berkelompok ke server; jika gagal, dicoba lagi dengan jeda bertambah.
 * - Status ditampilkan ke siswa: "Tersimpan", "Menyimpan…", "Offline — tersimpan di HP".
 * - Server tetap otoritatif: menolak jawaban bila waktu habis atau ujian terkunci (HTTP 423).
 * - Setiap jawaban membawa `rev` (nomor revisi) agar jawaban lama yang datang terlambat tidak
 *   menimpa jawaban baru.
 */
export class AnswerSync {
  constructor({ attemptId, endpoint, csrf, onStatus }) {
    this.key = `kitaq:ans:${attemptId}`;
    this.endpoint = endpoint;
    this.csrf = csrf;
    this.onStatus = onStatus || (() => {});
    this.busy = false;
    this.delay = 1500;
    this.timer = null;
    this.stopped = false;
    window.addEventListener('online', () => this.flush());
  }

  _all() { try { return JSON.parse(localStorage.getItem(this.key) || '{}'); } catch (e) { return this._mem || {}; } }
  _save(o) { this._mem = o; try { localStorage.setItem(this.key, JSON.stringify(o)); } catch (e) { /* abaikan */ } }

  set(questionId, answer, flagged = false) {
    const all = this._all();
    const prev = all[questionId];
    all[questionId] = { answer, flagged, rev: (prev ? prev.rev : 0) + 1, at: Date.now() };
    this._save(all);
    this.onStatus(navigator.onLine ? 'saving' : 'offline');
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.flush(), 600);
  }

  pendingCount() { return Object.keys(this._all()).length; }

  async flush() {
    if (this.busy || this.stopped) return;
    const all = this._all();
    const ids = Object.keys(all);
    if (!ids.length) { this.onStatus('saved'); return; }
    if (!navigator.onLine) { this.onStatus('offline'); return; }
    this.busy = true;
    const batch = ids.slice(0, 20).map((id) => ({ question_id: id, ...all[id] }));
    try {
      const res = await fetch(this.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
        body: JSON.stringify({ answers: batch }),
      });
      if (res.status === 423) { this.stopped = true; this.onStatus('locked'); return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const cur = this._all();
      (data.saved || []).forEach((s) => { if (cur[s.question_id] && cur[s.question_id].rev <= s.rev) delete cur[s.question_id]; });
      this._save(cur);
      this.delay = 1500;
      this.onStatus(Object.keys(cur).length ? 'saving' : 'saved');
      if (Object.keys(cur).length) setTimeout(() => this.flush(), 100);
    } catch (e) {
      this.onStatus(navigator.onLine ? 'retrying' : 'offline');
      setTimeout(() => this.flush(), this.delay);
      this.delay = Math.min(this.delay * 2, 20000);
    } finally {
      this.busy = false;
    }
  }
}
