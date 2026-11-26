/* Hand Filter Studio — kotak dari dua tangan + efek kamera, portal Doctor Strange, blur peace.
 * Seluruh pemrosesan berjalan di browser (MediaPipe Hand Landmarker, 2 tangan).
 * Satu canvas menggambar kamera (sudah dicerminkan) + efek, sehingga foto & rekaman = apa yang terlihat.
 */
import { HandTracker, describeError } from './hand-tracking.js';

const root = document.getElementById('hand-filter');
if (root) {
  const $ = (s) => root.querySelector(s);
  const $$ = (s) => Array.from(root.querySelectorAll(s));
  const video = $('[data-hf-video]'), canvas = $('[data-hf-canvas]'), board = $('[data-hf-board]');
  const ctx = canvas.getContext('2d');
  const el = {
    toggle: $('[data-hf-toggle]'), toggleLabel: $('[data-hf-toggle-label]'), status: $('[data-hf-status]'), statusText: $('[data-hf-status-text]'),
    hands: $('[data-hf-hands]'), rec: $('[data-hf-rec]'), recTime: $('[data-hf-rec-time]'), label: $('[data-hf-label]'), empty: $('[data-hf-empty]'),
    hint: $('[data-hf-hint]'), flash: $('[data-hf-flash]'), guide: $('[data-hf-guide]'), snap: $('[data-hf-snap]'), record: $('[data-hf-record]'), recordLabel: $('[data-hf-record-label]')
  };

  const FILTERS = ['glitch', 'crt', 'thermal', 'rainbow', 'mosaic', 'dream'];
  const NAMES = { glitch: 'GLITCH', crt: 'CRT', thermal: 'THERMAL', rainbow: 'RAINBOW', mosaic: 'MOSAIC', dream: 'DREAM' };
  const CONN = [[0, 1], [1, 2], [2, 3], [3, 4], [0, 5], [5, 6], [6, 7], [7, 8], [5, 9], [9, 10], [10, 11], [11, 12], [9, 13], [13, 14], [14, 15], [15, 16], [13, 17], [17, 18], [18, 19], [19, 20], [0, 17]];
  const GUIDE_KEY = 'wedi-filter-guide-v1';

  /* ---------------- buffers ---------------- */
  const mk = (w, h) => { const c = document.createElement('canvas'); c.width = w || 2; c.height = h || 2; return c; };
  let W = 960, H = 540;
  const mirror = mk(), mctx = mirror.getContext('2d');
  const bufA = mk(), actx = bufA.getContext('2d'), bufB = mk(), bctx = bufB.getContext('2d');
  const tc = mk(320, 320), tctx = tc.getContext('2d', { willReadFrequently: true });
  const blur1 = mk(), b1 = blur1.getContext('2d'), blur2 = mk(), b2 = blur2.getContext('2d');

  /* ---------------- state ---------------- */
  let tracker = null, running = false, busy = false, raf = 0, tick = 0;
  let filterIdx = 0, boxColor = '#00f0ff', showSkeleton = true, labelTimer = 0, lostSince = 0, state = 'off';
  const model = { hands: [], blurTarget: 0, blurAmt: 0, portals: [], fold: false, lastCycle: 0 };
  const box = { active: false, corners: null, lost: 0, since: 0 };
  let sparks = [];
  const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* noop */ } } };

  const d = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  const rand = (a, b) => a + Math.random() * (b - a);

  /* ---------------- UI helpers ---------------- */
  function setState(name, text) {
    state = name; root.dataset.hsState = name; el.status.dataset.state = name; el.statusText.textContent = text;
  }
  function label(text) {
    el.label.textContent = text; el.label.classList.add('active'); clearTimeout(labelTimer);
    labelTimer = setTimeout(() => el.label.classList.remove('active'), 1300);
  }
  function setFilter(i, announce) {
    filterIdx = (i + FILTERS.length) % FILTERS.length;
    $$('[data-hf-filter]').forEach((b) => { const on = b.dataset.hfFilter === FILTERS[filterIdx]; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    if (announce) label('FILTER: ' + NAMES[FILTERS[filterIdx]]);
  }
  $$('[data-hf-filter]').forEach((b) => b.addEventListener('click', () => setFilter(FILTERS.indexOf(b.dataset.hfFilter), true)));
  $$('[data-hf-color]').forEach((b) => b.addEventListener('click', () => {
    boxColor = b.dataset.hfColor; root.style.setProperty('--hf-accent', boxColor);
    $$('[data-hf-color]').forEach((x) => x.classList.toggle('active', x === b));
  }));
  $('[data-hf-skeleton]').addEventListener('change', (e) => { showSkeleton = e.target.checked; });
  $('[data-hf-reset]').addEventListener('click', () => { box.active = false; box.corners = null; label('KOTAK DIRESET'); });
  root.style.setProperty('--hf-accent', boxColor);

  /* ---------------- gestures (rotation-invariant) ---------------- */
  function fingers(h) {
    const ext = (t, p) => d(h[t], h[0]) > d(h[p], h[0]) * 1.12;
    const thumb = d(h[4], h[17]) > d(h[3], h[17]) * 1.02 && d(h[4], h[5]) > d(h[3], h[5]);
    return [thumb, ext(8, 6), ext(12, 10), ext(16, 14), ext(20, 18)];
  }
  const palm = (h) => d(h[0], h[9]);
  const isPeace = (f) => f[1] && f[2] && !f[3] && !f[4];
  const isOpen = (f) => f.every(Boolean);
  const threeFolded = (f) => !f[2] && !f[3] && !f[4];

  /* ---------------- hand results ---------------- */
  let prev = [];
  function onHands(s) {
    const now = performance.now();
    const raw = (s.hands || []).slice(0, 2);
    let hands = raw.map((lm) => lm.map((p) => ({ x: (1 - p.x) * W, y: p.y * H }))).sort((a, b) => a[0].x - b[0].x);
    if (prev.length === hands.length) hands = hands.map((h, i) => h.map((p, k) => ({ x: prev[i][k].x + (p.x - prev[i][k].x) * 0.6, y: prev[i][k].y + (p.y - prev[i][k].y) * 0.6 })));
    prev = hands; model.hands = hands;

    const n = hands.length;
    el.hands.hidden = n === 0;
    if (n) el.hands.textContent = n === 2 ? '🙌 2 tangan' : '✋ 1 tangan';
    if (n) { lostSince = 0; el.hint.hidden = true; if (state !== 'tracking') setState('tracking', 'Tangan terdeteksi'); }
    else {
      if (state === 'tracking') { setState('lost', 'Tangan hilang'); lostSince = now; }
      if (lostSince && now - lostSince > 1300) el.hint.hidden = false;
    }

    const f = hands.map(fingers);
    // satu tangan peace => blur sinematik
    model.blurTarget = n === 1 && isPeace(f[0]) ? 1 : 0;
    if (model.blurTarget && model.blurAmt < 0.1) label('CINEMATIC BLUR ✌️');

    if (n === 2) {
      box.lost = 0;
      const pm = (palm(hands[0]) + palm(hands[1])) / 2;
      const thr = Math.max(24, pm * 0.42);
      if (!box.active && d(hands[0][4], hands[1][4]) < thr && d(hands[0][8], hands[1][8]) < thr) {
        box.active = true; box.since = now; model.fold = false; label('BOX ACTIVATED ⚡');
      }
      if (box.active) {
        box.corners = [hands[0][4], hands[0][8], hands[1][8], hands[1][4]].map((p) => ({ x: p.x, y: p.y }));
        const folded = f.some(threeFolded);
        if (now - box.since > 900) { // abaikan saat baru membentuk kotak
          if (folded) model.fold = true;
          else if (model.fold) { model.fold = false; if (now - model.lastCycle > 500) { model.lastCycle = now; setFilter(filterIdx + 1, true); } }
        }
      }
    } else if (box.active && ++box.lost > 10) { box.active = false; box.corners = null; model.fold = false; }

    // portal Doctor Strange di setiap telapak terbuka (saat kotak tidak aktif)
    model.portals = box.active ? [] : hands.map((h, i) => ({ c: h[9], r: Math.max(55, palm(h) * 0.95), open: isOpen(f[i]) })).filter((p) => p.open);
    if (model.portals.length) label('DOCTOR STRANGE PORTAL x' + model.portals.length + ' 🔮');
  }

  /* ---------------- drawing ---------------- */
  function quad(c, q) { c.beginPath(); c.moveTo(q[0].x, q[0].y); for (let i = 1; i < 4; i++) c.lineTo(q[i].x, q[i].y); c.closePath(); }
  function bbox(q) {
    const xs = q.map((p) => p.x), ys = q.map((p) => p.y);
    const x = Math.max(0, Math.floor(Math.min.apply(null, xs))), y = Math.max(0, Math.floor(Math.min.apply(null, ys)));
    const x2 = Math.min(W, Math.ceil(Math.max.apply(null, xs))), y2 = Math.min(H, Math.ceil(Math.max.apply(null, ys)));
    return { x, y, w: x2 - x, h: y2 - y };
  }
  const region = (c, bb) => c.drawImage(mirror, bb.x, bb.y, bb.w, bb.h, bb.x, bb.y, bb.w, bb.h);

  const LUT = (() => {
    const stops = [[0, [0, 0, 20]], [0.22, [60, 10, 120]], [0.45, [200, 30, 60]], [0.65, [255, 120, 0]], [0.85, [255, 220, 40]], [1, [255, 255, 230]]];
    const out = new Uint8ClampedArray(256 * 3);
    for (let i = 0; i < 256; i++) {
      const t = i / 255; let k = 0; while (k < stops.length - 2 && t > stops[k + 1][0]) k++;
      const a = stops[k], b = stops[k + 1], u = (t - a[0]) / (b[0] - a[0]);
      for (let c = 0; c < 3; c++) out[i * 3 + c] = a[1][c] + (b[1][c] - a[1][c]) * u;
    }
    return out;
  })();

  const FX = {
    glitch(bb) {
      const shift = 6 + Math.sin(tick * 0.3) * 4, jit = Math.random() < 0.15 ? rand(-5, 5) : 0;
      region(ctx, bb);
      [[actx, bufA, '#ff0055'], [bctx, bufB, '#00f0ff']].forEach(([c, , col]) => {
        c.globalCompositeOperation = 'source-over'; c.clearRect(bb.x, bb.y, bb.w, bb.h); region(c, bb);
        c.globalCompositeOperation = 'multiply'; c.fillStyle = col; c.fillRect(bb.x, bb.y, bb.w, bb.h); c.globalCompositeOperation = 'source-over';
      });
      ctx.globalCompositeOperation = 'screen';
      ctx.drawImage(bufA, bb.x, bb.y, bb.w, bb.h, bb.x - shift + jit, bb.y, bb.w, bb.h);
      ctx.drawImage(bufB, bb.x, bb.y, bb.w, bb.h, bb.x + shift + jit, bb.y, bb.w, bb.h);
      ctx.globalCompositeOperation = 'source-over';
      for (let k = 0; k < 3; k++) if (Math.random() < 0.35) { const sy = bb.y + Math.random() * bb.h, sh = 3 + Math.random() * 14; ctx.drawImage(mirror, bb.x, sy, bb.w, sh, bb.x + rand(-25, 25), sy, bb.w, sh); }
    },
    crt(bb) {
      region(ctx, bb);
      ctx.fillStyle = 'rgba(0,240,255,.18)'; ctx.fillRect(bb.x, bb.y, bb.w, bb.h);
      ctx.fillStyle = 'rgba(0,0,0,.38)';
      for (let y = bb.y - (bb.y % 3); y < bb.y + bb.h; y += 3) ctx.fillRect(bb.x, y, bb.w, 1);
      const by = bb.y + ((tick * 3) % (bb.h + 80)) - 40, g = ctx.createLinearGradient(0, by, 0, by + 40);
      g.addColorStop(0, 'rgba(255,255,255,0)'); g.addColorStop(0.5, 'rgba(255,255,255,.10)'); g.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.fillStyle = g; ctx.fillRect(bb.x, by, bb.w, 40);
      const v = ctx.createRadialGradient(bb.x + bb.w / 2, bb.y + bb.h / 2, Math.min(bb.w, bb.h) * 0.2, bb.x + bb.w / 2, bb.y + bb.h / 2, Math.max(bb.w, bb.h) * 0.72);
      v.addColorStop(0, 'rgba(0,0,0,0)'); v.addColorStop(1, 'rgba(0,0,0,.5)'); ctx.fillStyle = v; ctx.fillRect(bb.x, bb.y, bb.w, bb.h);
      if (Math.random() < 0.12) { ctx.fillStyle = 'rgba(0,255,255,.05)'; ctx.fillRect(bb.x, bb.y, bb.w, bb.h); }
    },
    thermal(bb) {
      const sw = Math.min(320, Math.max(8, Math.round(bb.w / 6))), sh = Math.min(320, Math.max(8, Math.round(bb.h / 6)));
      tctx.clearRect(0, 0, 320, 320); tctx.drawImage(mirror, bb.x, bb.y, bb.w, bb.h, 0, 0, sw, sh);
      const img = tctx.getImageData(0, 0, sw, sh), px = img.data;
      for (let i = 0; i < px.length; i += 4) { const l = (px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114) | 0; px[i] = LUT[l * 3]; px[i + 1] = LUT[l * 3 + 1]; px[i + 2] = LUT[l * 3 + 2]; }
      tctx.putImageData(img, 0, 0);
      ctx.imageSmoothingEnabled = true; ctx.drawImage(tc, 0, 0, sw, sh, bb.x, bb.y, bb.w, bb.h);
    },
    rainbow(bb) {
      region(ctx, bb);
      const hue = (tick * 4) % 360, g = ctx.createLinearGradient(bb.x, bb.y, bb.x + bb.w, bb.y + bb.h);
      for (let i = 0; i < 4; i++) g.addColorStop(i / 3, 'hsl(' + ((hue + i * 90) % 360) + ',100%,55%)');
      ctx.globalCompositeOperation = 'color'; ctx.globalAlpha = 0.9; ctx.fillStyle = g; ctx.fillRect(bb.x, bb.y, bb.w, bb.h);
      ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    },
    mosaic(bb) {
      const block = Math.max(8, Math.round(Math.min(bb.w, bb.h) / 14)), sw = Math.min(320, Math.ceil(bb.w / block)), sh = Math.min(320, Math.ceil(bb.h / block));
      tctx.clearRect(0, 0, 320, 320); tctx.drawImage(mirror, bb.x, bb.y, bb.w, bb.h, 0, 0, sw, sh);
      ctx.imageSmoothingEnabled = false; ctx.drawImage(tc, 0, 0, sw, sh, bb.x, bb.y, sw * block, sh * block); ctx.imageSmoothingEnabled = true;
    },
    dream(bb) {
      region(ctx, bb);
      const sw = Math.max(4, Math.round(bb.w / 14)), sh = Math.max(4, Math.round(bb.h / 14));
      tctx.clearRect(0, 0, 320, 320); tctx.drawImage(mirror, bb.x, bb.y, bb.w, bb.h, 0, 0, sw, sh);
      ctx.imageSmoothingEnabled = true; ctx.globalCompositeOperation = 'screen'; ctx.globalAlpha = 0.65; ctx.drawImage(tc, 0, 0, sw, sh, bb.x, bb.y, bb.w, bb.h);
      ctx.globalAlpha = 0.35; ctx.globalCompositeOperation = 'soft-light';
      const g = ctx.createLinearGradient(bb.x, bb.y, bb.x + bb.w, bb.y + bb.h); g.addColorStop(0, '#ff7ad9'); g.addColorStop(1, '#7aa8ff');
      ctx.fillStyle = g; ctx.fillRect(bb.x, bb.y, bb.w, bb.h); ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    }
  };

  function drawEffect(q) {
    const bb = bbox(q);
    if (bb.w < 10 || bb.h < 10) { outline(q); return; }
    ctx.save(); quad(ctx, q); ctx.clip();
    FX[FILTERS[filterIdx]](bb);
    ctx.restore();
    outline(q);
    for (let i = 0; i < 2; i++) { // percikan di tepi kotak
      const a = (Math.random() * 4) | 0, b = (a + 1) % 4, t = Math.random();
      const x = q[a].x + (q[b].x - q[a].x) * t, y = q[a].y + (q[b].y - q[a].y) * t;
      sparks.push({ x, y, vx: rand(-1.5, 1.5), vy: rand(-2.2, 0.4), life: 22, max: 22, c: boxColor, r: 2 });
    }
  }

  function outline(q) {
    ctx.save(); ctx.lineJoin = 'round';
    ctx.shadowColor = boxColor; ctx.shadowBlur = 18; ctx.strokeStyle = boxColor; ctx.lineWidth = 3; quad(ctx, q); ctx.stroke();
    ctx.shadowBlur = 0; ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.lineWidth = 1.2; ctx.setLineDash([10, 8]); ctx.lineDashOffset = -tick * 0.8; quad(ctx, q); ctx.stroke(); ctx.setLineDash([]);
    q.forEach((p) => { ctx.fillStyle = boxColor; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(p.x, p.y, 7, 0, Math.PI * 2); ctx.fill(); ctx.stroke(); });
    const top = q.reduce((a, b) => (b.y < a.y ? b : a)), text = NAMES[FILTERS[filterIdx]];
    ctx.font = '800 13px "Segoe UI", sans-serif'; const tw = ctx.measureText(text).width + 18, tx = Math.max(4, Math.min(W - tw - 4, top.x - tw / 2)), ty = Math.max(4, top.y - 34);
    ctx.fillStyle = boxColor; ctx.beginPath(); ctx.roundRect ? ctx.roundRect(tx, ty, tw, 22, 11) : ctx.rect(tx, ty, tw, 22); ctx.fill();
    ctx.fillStyle = '#071018'; ctx.textBaseline = 'middle'; ctx.fillText(text, tx + 9, ty + 11.5);
    ctx.restore();
  }

  function portal(p) {
    const t = tick * 0.04, r = p.r * (1 + Math.sin(tick * 0.1) * 0.03);
    ctx.save(); ctx.translate(p.c.x, p.c.y);
    const glow = ctx.createRadialGradient(0, 0, r * 0.2, 0, 0, r * 1.1); glow.addColorStop(0, 'rgba(255,140,0,.22)'); glow.addColorStop(1, 'rgba(255,69,0,0)');
    ctx.fillStyle = glow; ctx.beginPath(); ctx.arc(0, 0, r * 1.1, 0, Math.PI * 2); ctx.fill();
    ctx.shadowColor = '#ff4500'; ctx.shadowBlur = 25;
    ctx.save(); ctx.rotate(t); ctx.strokeStyle = '#ff7700'; ctx.lineWidth = 4; ctx.setLineDash([12, 8, 4, 8]); ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
    ctx.save(); ctx.rotate(-t * 1.5); ctx.strokeStyle = '#ffcc00'; ctx.lineWidth = 3; ctx.setLineDash([20, 10, 5, 10]); ctx.beginPath(); ctx.arc(0, 0, r * 0.75, 0, Math.PI * 2); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,180,0,.6)'; ctx.lineWidth = 2; ctx.setLineDash([]); ctx.strokeRect(-r * 0.5, -r * 0.5, r, r); ctx.restore();
    ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(0, 0, r * 0.4, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();
    for (let i = 0; i < 3; i++) {
      const a = Math.random() * Math.PI * 2, dist = r * rand(0.8, 1.1);
      sparks.push({ x: p.c.x + Math.cos(a) * dist, y: p.c.y + Math.sin(a) * dist, vx: Math.cos(a) * rand(2, 5), vy: Math.sin(a) * rand(2, 5) - 1, life: 20, max: 20, c: 'orange', r: 2 });
    }
  }

  function drawSparks() {
    for (let i = sparks.length - 1; i >= 0; i--) {
      const s = sparks[i], a = s.life / s.max;
      ctx.fillStyle = s.c === 'orange' ? 'rgba(255,' + Math.floor(150 * a) + ',0,' + a + ')' : s.c;
      ctx.globalAlpha = s.c === 'orange' ? 1 : a;
      ctx.beginPath(); ctx.arc(s.x, s.y, s.r + a * 2, 0, Math.PI * 2); ctx.fill();
      s.x += s.vx; s.y += s.vy; if (--s.life <= 0) sparks.splice(i, 1);
    }
    ctx.globalAlpha = 1;
  }

  function skeleton(h) {
    ctx.save(); ctx.lineCap = 'round';
    ctx.strokeStyle = boxColor; ctx.globalAlpha = 0.35; ctx.lineWidth = 7; ctx.shadowColor = boxColor; ctx.shadowBlur = 16;
    CONN.forEach(([a, b]) => { ctx.beginPath(); ctx.moveTo(h[a].x, h[a].y); ctx.lineTo(h[b].x, h[b].y); ctx.stroke(); });
    ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.lineWidth = 2.5;
    CONN.forEach(([a, b]) => { ctx.beginPath(); ctx.moveTo(h[a].x, h[a].y); ctx.lineTo(h[b].x, h[b].y); ctx.stroke(); });
    h.forEach((p, i) => { ctx.fillStyle = boxColor; ctx.beginPath(); ctx.arc(p.x, p.y, i % 4 === 0 && i ? 5.5 : 4, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(p.x, p.y, 2, 0, Math.PI * 2); ctx.fill(); });
    ctx.restore();
  }

  function blurBackground(amt) {
    b1.drawImage(mirror, 0, 0, blur1.width, blur1.height);
    b2.drawImage(blur1, 0, 0, blur2.width, blur2.height);
    ctx.save(); ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high'; ctx.globalAlpha = Math.min(1, amt);
    ctx.drawImage(blur2, 0, 0, blur2.width, blur2.height, 0, 0, W, H);
    ctx.fillStyle = 'rgba(0,0,0,.16)'; ctx.fillRect(0, 0, W, H); ctx.restore();
  }

  function render() {
    if (!running) return;
    raf = requestAnimationFrame(render); tick++;
    mctx.setTransform(-1, 0, 0, 1, W, 0); mctx.drawImage(video, 0, 0, W, H); mctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalCompositeOperation = 'source-over'; ctx.globalAlpha = 1; ctx.clearRect(0, 0, W, H);
    ctx.drawImage(mirror, 0, 0);
    model.blurAmt += (model.blurTarget - model.blurAmt) * 0.2;
    if (model.blurAmt > 0.03) blurBackground(model.blurAmt);
    if (box.active && box.corners) drawEffect(box.corners); else model.portals.forEach(portal);
    drawSparks();
    if (showSkeleton) model.hands.forEach(skeleton);
  }

  /* ---------------- camera ---------------- */
  function setupCanvas() {
    const vw = video.videoWidth || 1280, vh = video.videoHeight || 720, k = Math.min(1, 960 / vw);
    W = Math.round(vw * k); H = Math.round(vh * k);
    [canvas, mirror, bufA, bufB].forEach((c) => { c.width = W; c.height = H; });
    blur1.width = Math.max(8, Math.round(W / 5)); blur1.height = Math.max(8, Math.round(H / 5));
    blur2.width = Math.max(6, Math.round(W / 16)); blur2.height = Math.max(6, Math.round(H / 16));
    board.style.aspectRatio = W + ' / ' + H;
  }

  async function start() {
    busy = true; el.toggle.disabled = true;
    setState('loading', 'Menyiapkan kamera & model tangan…');
    if (!store.get(GUIDE_KEY)) el.guide.hidden = false;
    try {
      if (!tracker) tracker = new HandTracker({ video, onFrame: onHands, maxFps: 40, smoothing: 0.6, numHands: 2 });
      await tracker.start();
      setupCanvas(); prev = []; model.hands = []; model.portals = []; sparks = []; box.active = false; box.corners = null;
      running = true; el.empty.hidden = true; el.snap.disabled = false; el.record.disabled = !(canvas.captureStream && window.MediaRecorder);
      el.toggleLabel.textContent = 'Matikan kamera'; el.toggle.classList.add('is-on');
      setState('waiting', 'Tunjukkan kedua tanganmu 🙌');
      raf = requestAnimationFrame(render);
    } catch (e) { setState('error', describeError(e)); }
    busy = false; el.toggle.disabled = false;
  }

  function stop() {
    running = false; cancelAnimationFrame(raf); stopRec();
    if (tracker) tracker.stop();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    model.hands = []; model.portals = []; box.active = false; box.corners = null; sparks = [];
    el.empty.hidden = false; el.hint.hidden = true; el.hands.hidden = true; el.snap.disabled = true; el.record.disabled = true; el.label.classList.remove('active');
    el.toggleLabel.textContent = 'Aktifkan kamera'; el.toggle.classList.remove('is-on');
    if (state !== 'error') setState('off', 'Kamera mati');
  }
  el.toggle.addEventListener('click', () => { if (busy) return; if (running) stop(); else start(); });
  window.addEventListener('pagehide', stop);

  /* ---------------- foto & rekaman ---------------- */
  const stamp = () => { const n = new Date(), p = (v) => String(v).padStart(2, '0'); return n.getFullYear() + p(n.getMonth() + 1) + p(n.getDate()) + '-' + p(n.getHours()) + p(n.getMinutes()) + p(n.getSeconds()); };
  function save(blob, name) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); }
  function snap() {
    if (!running) return;
    el.flash.classList.remove('go'); void el.flash.offsetWidth; el.flash.classList.add('go');
    canvas.toBlob((b) => { if (b) { save(b, 'wedi-filter-' + stamp() + '.png'); label('FOTO TERSIMPAN 📸'); } }, 'image/png');
  }
  el.snap.addEventListener('click', snap);

  let rec = null, recChunks = [], recTimer = 0, recStart = 0;
  function startRec() {
    if (!running || rec || !canvas.captureStream || !window.MediaRecorder) return;
    const types = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'], mime = types.find((t) => MediaRecorder.isTypeSupported(t));
    try { rec = new MediaRecorder(canvas.captureStream(30), mime ? { mimeType: mime, videoBitsPerSecond: 5000000 } : {}); } catch (e) { rec = null; return; }
    recChunks = []; rec.ondataavailable = (e) => { if (e.data && e.data.size) recChunks.push(e.data); };
    rec.onstop = () => { if (recChunks.length) save(new Blob(recChunks, { type: 'video/webm' }), 'wedi-filter-' + stamp() + '.webm'); recChunks = []; };
    rec.start(250); recStart = Date.now(); el.rec.hidden = false; el.recordLabel.textContent = 'Stop';
    recTimer = setInterval(() => { const s = Math.floor((Date.now() - recStart) / 1000); el.recTime.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); }, 500);
    label('MEREKAM 🔴');
  }
  function stopRec() {
    if (!rec) return;
    clearInterval(recTimer); try { if (rec.state !== 'inactive') rec.stop(); } catch (e) { /* noop */ }
    rec = null; el.rec.hidden = true; el.recTime.textContent = '00:00'; el.recordLabel.textContent = 'Rekam';
  }
  el.record.addEventListener('click', () => (rec ? stopRec() : startRec()));
  if (!(window.MediaRecorder && HTMLCanvasElement.prototype.captureStream)) el.record.hidden = true;

  /* ---------------- panduan & keyboard ---------------- */
  $('[data-hf-guide-ok]').addEventListener('click', () => { store.set(GUIDE_KEY, '1'); el.guide.hidden = true; });
  $('[data-hf-help]').addEventListener('click', () => { el.guide.hidden = false; });
  document.addEventListener('keydown', (e) => {
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    if (/^[1-6]$/.test(e.key)) setFilter(parseInt(e.key, 10) - 1, true);
    else if (e.code === 'Space' && running) { e.preventDefault(); snap(); }
    else if (e.key === 'Escape' && !el.guide.hidden) el.guide.hidden = true;
  });
}
