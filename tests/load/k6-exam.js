// Uji beban KITAQ ONLINE — lihat docs/UJI_BEBAN.md
// k6 run -e BASE=https://ujian.sekolah.sch.id -e VUS=300 -e DURATION=10m -e SCHEDULE=1 -e TOKEN=UJIBEB tests/load/k6-exam.js
// Butuh siswa dummy dari `php artisan kitaq:seed-load 300` (NISN 99000000001.., tanggal lahir 01012008) dan jadwal uji dengan token tetap.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://localhost:8000';
const SCHEDULE = __ENV.SCHEDULE || '1';
const TOKEN = __ENV.TOKEN || 'UJIBEB';
const tHeartbeat = new Trend('kitaq_heartbeat_ms');
const tAnswer = new Trend('kitaq_answer_ms');
const tQuestion = new Trend('kitaq_question_ms');

export const options = {
  scenarios: {
    siswa: { executor: 'ramping-vus', startVUs: 0, stages: [
      { duration: '2m', target: Number(__ENV.VUS || 100) },
      { duration: __ENV.DURATION || '5m', target: Number(__ENV.VUS || 100) },
      { duration: '1m', target: 0 },
    ] },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    kitaq_heartbeat_ms: ['p(95)<800'],
    kitaq_answer_ms: ['p(95)<800'],
    kitaq_question_ms: ['p(95)<1200'],
  },
};

function csrfFrom(html) {
  const m = html.match(/name="csrf-token" content="([^"]+)"/) || html.match(/name="_token" value="([^"]+)"/);
  return m ? m[1] : '';
}

export default function () {
  const jar = http.cookieJar();
  const nisn = String(99000000000 + __VU).slice(-10);
  let res = http.get(`${BASE}/`);
  let csrf = csrfFrom(res.body);
  res = http.post(`${BASE}/login`, { _token: csrf, nisn, password: '01012008' }, { redirects: 1 });
  check(res, { 'login ok': (r) => r.status === 200 });

  res = http.get(`${BASE}/ujian/${SCHEDULE}`);
  csrf = csrfFrom(res.body);
  res = http.post(`${BASE}/ujian/${SCHEDULE}/mulai`, { _token: csrf, token: TOKEN }, { redirects: 1 });
  const m = res.body && res.body.match(/"attemptId":(\d+)/);
  if (!m) { sleep(5); return; }
  const a = m[1];
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
  const instance = `k6-${__VU}`;
  let nonce = null;

  for (let no = 1; no <= 20; no++) {
    let r = http.get(`${BASE}/api/attempts/${a}/questions/${no}`, { headers });
    tQuestion.add(r.timings.duration);
    const q = r.json();
    sleep(8 + Math.random() * 6);

    r = http.post(`${BASE}/api/attempts/${a}/answers`, JSON.stringify({ answers: [{ question_id: q.id, answer: 'A', flagged: false, rev: 1 }] }), { headers });
    tAnswer.add(r.timings.duration);

    r = http.post(`${BASE}/api/attempts/${a}/heartbeat`, JSON.stringify({ instance_id: instance, nonce, visible: true, focused: true, online: true, vw: 390, vh: 780, sw: 390, sh: 844 }), { headers });
    tHeartbeat.add(r.timings.duration);
    try { nonce = r.json('nonce'); } catch (e) { nonce = null; }

    if (Math.random() < 0.05) {
      http.post(`${BASE}/api/attempts/${a}/events`, JSON.stringify({ instance_id: instance, client_now: Date.now(), events: [{ seq: no, type: 'context_menu', at: Date.now(), detail: {} }] }), { headers });
    }
  }
}
