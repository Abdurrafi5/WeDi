/* WeDi Hand FX — lapisan visual "hand filter" di atas board (canvas 2D).
 * Skeleton bercahaya, jejak jari, ripple pinch, kursor dengan progres dwell.
 * Koordinat dipetakan sama persis dengan pointer game (x dicerminkan), jadi
 * apa yang terlihat = apa yang mengontrol.
 */
const FINGERS = [[0, 1, 2, 3, 4], [0, 5, 6, 7, 8], [0, 9, 10, 11, 12], [0, 13, 14, 15, 16], [0, 17, 18, 19, 20]];
const PALM = [0, 1, 5, 9, 13, 17];
const TIPS = [4, 8, 12, 16, 20];
const TRAIL_MS = 480;

export const STYLES = {
  neon: { label: 'Neon', mode: 'neon', fingers: ['#ffd166', '#ff4d8d', '#3df5c8', '#4dc3ff', '#b48cff'], accent: '#ff4d8d', glow: 16, width: 4 },
  aurora: { label: 'Aurora', mode: 'aurora', fingers: ['#7cf', '#9f8', '#fa8', '#f8c', '#8cf'], accent: '#7ee8ff', glow: 0, width: 4 },
  sakura: { label: 'Sakura', mode: 'sakura', fingers: ['#ffc2d9', '#ff8fb8', '#ffd0e1', '#ff9ec4', '#ffdbe8'], accent: '#ff7fb0', glow: 10, width: 3.4 },
  minimal: { label: 'Minimal', mode: 'minimal', fingers: ['#fff', '#fff', '#fff', '#fff', '#fff'], accent: '#ffffff', glow: 0, width: 2.2 }
};

export class HandFX {
  constructor(canvas, opts = {}) {
    this.c = canvas;
    this.ctx = canvas.getContext('2d');
    this.opts = Object.assign({ style: 'neon', trail: true, cursor: true, skeleton: 1, hideTrailOnPinch: false, debug: false }, opts);
    this.w = 1; this.h = 1; this.dpr = 1;
    this.target = null; this.cur = null; this.alpha = 0; this.state = null;
    this.trail = []; this.parts = []; this.ripples = [];
    this.dwell = 0; this.running = false; this._pinchPrev = false; this._raf = 0; this._last = 0;
    this._loop = this._loop.bind(this);
    this._ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(() => this.resize()) : null;
    if (this._ro) this._ro.observe(canvas);
    this.resize();
  }

  set(o) { Object.assign(this.opts, o); }
  get st() { return STYLES[this.opts.style] || STYLES.neon; }

  resize() {
    const r = this.c.getBoundingClientRect();
    this.dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.w = Math.max(1, r.width); this.h = Math.max(1, r.height);
    this.c.width = Math.round(this.w * this.dpr); this.c.height = Math.round(this.h * this.dpr);
  }

  /** Dipanggil setiap hasil deteksi baru. */
  feed(s) {
    this.state = s;
    if (s.detected) {
      this.target = s.landmarks.map((p) => ({ x: (1 - p.x) * this.w, y: p.y * this.h }));
      if (!this.cur) this.cur = this.target.map((p) => ({ x: p.x, y: p.y }));
      if (s.pinch && !this._pinchPrev) this.ripple((this.target[4].x + this.target[8].x) / 2, (this.target[4].y + this.target[8].y) / 2);
      this._pinchPrev = s.pinch;
    } else this._pinchPrev = false;
  }

  ripple(x, y, color) { this.ripples.push({ x, y, t0: performance.now(), color: color || this.st.accent }); }

  start() {
    if (this.running) return;
    this.running = true; this._last = performance.now();
    this.resize();
    this._raf = requestAnimationFrame(this._loop);
  }

  stop() {
    this.running = false; cancelAnimationFrame(this._raf);
    this.target = null; this.cur = null; this.alpha = 0; this.trail = []; this.parts = []; this.ripples = []; this.dwell = 0; this.state = null;
    this.ctx.setTransform(1, 0, 0, 1, 0, 0); this.ctx.clearRect(0, 0, this.c.width, this.c.height);
  }

  /* ---------------- loop ---------------- */
  _loop(now) {
    if (!this.running) return;
    this._raf = requestAnimationFrame(this._loop);
    const dt = Math.min(0.05, (now - this._last) / 1000); this._last = now;
    const detected = !!(this.state && this.state.detected);

    if (this.cur && this.target) { const k = Math.min(1, dt * 24); for (let i = 0; i < 21; i++) { this.cur[i].x += (this.target[i].x - this.cur[i].x) * k; this.cur[i].y += (this.target[i].y - this.cur[i].y) * k; } }
    this.alpha += ((detected ? 1 : 0) - this.alpha) * Math.min(1, dt * (detected ? 12 : 7));
    if (!detected && this.alpha < 0.02) this.cur = null;

    const ctx = this.ctx;
    ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
    ctx.clearRect(0, 0, this.w, this.h);
    const st = this.st;

    if (this.cur && this.alpha > 0.01) {
      this._updateTrail(now);
      if (this.opts.trail && !(this.opts.hideTrailOnPinch && this.state && this.state.pinch)) this._drawTrail(now, st);
      ctx.save();
      ctx.globalAlpha = this.alpha * this.opts.skeleton;
      this._drawSkeleton(this.cur, st, now);
      ctx.restore();
      ctx.save(); ctx.globalAlpha = this.alpha;
      if (this.state && this.state.pinch) this._drawPinch(this.cur, st, now);
      if (st.mode === 'sakura') this._emitPetals(this.cur, dt);
      if (this.opts.cursor) this._drawCursor(this.cur, st, now);
      ctx.restore();
    }
    this._stepParticles(dt, st);
    this._drawRipples(now);
    if (this.opts.debug && this.cur) this._drawDebug(now);
  }

  /* ---------------- trail ---------------- */
  _updateTrail(now) {
    const p = this.cur[8], last = this.trail[this.trail.length - 1];
    if (!last || Math.hypot(p.x - last.x, p.y - last.y) > 1.5) {
      this.trail.push({ x: p.x, y: p.y, t: now });
      if (last && this.opts.trail) {
        const d = Math.hypot(p.x - last.x, p.y - last.y);
        if (d > 7 && Math.random() < 0.7) this._spark(p.x, p.y, this.st);
      }
    }
    while (this.trail.length && now - this.trail[0].t > TRAIL_MS) this.trail.shift();
  }

  _trailColor(a, st, now) {
    switch (st.mode) {
      case 'neon': return `hsla(${330 - (1 - a) * 150},100%,66%,${(1 - a) * 0.95})`;
      case 'aurora': return `hsla(${(now / 12 + (1 - a) * 120) % 360},95%,70%,${(1 - a) * 0.8})`;
      case 'sakura': return `rgba(255,140,185,${(1 - a) * 0.85})`;
      default: return `rgba(255,255,255,${(1 - a) * 0.6})`;
    }
  }

  _drawTrail(now, st) {
    const ctx = this.ctx, t = this.trail;
    if (t.length < 2) return;
    ctx.save();
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    if (st.mode === 'neon' || st.mode === 'aurora') ctx.globalCompositeOperation = 'lighter';
    for (let i = 1; i < t.length; i++) {
      const a = (now - t[i].t) / TRAIL_MS;
      ctx.beginPath(); ctx.moveTo(t[i - 1].x, t[i - 1].y); ctx.lineTo(t[i].x, t[i].y);
      ctx.strokeStyle = this._trailColor(a, st, now);
      ctx.lineWidth = 2 + (1 - a) * 10;
      if (st.mode === 'neon') { ctx.shadowColor = st.accent; ctx.shadowBlur = 12; }
      ctx.stroke();
    }
    ctx.restore();
  }

  /* ---------------- skeleton ---------------- */
  _poly(pts, idx) { const ctx = this.ctx; ctx.beginPath(); idx.forEach((i, k) => (k ? ctx.lineTo(pts[i].x, pts[i].y) : ctx.moveTo(pts[i].x, pts[i].y))); }

  _drawSkeleton(pts, st, now) {
    const ctx = this.ctx;
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';

    // telapak (isi lembut)
    this._poly(pts, PALM); ctx.closePath();
    const g = ctx.createLinearGradient(pts[0].x, pts[0].y, pts[9].x, pts[9].y);
    if (st.mode === 'sakura') { g.addColorStop(0, 'rgba(255,170,205,.22)'); g.addColorStop(1, 'rgba(255,220,235,.12)'); }
    else if (st.mode === 'minimal') { g.addColorStop(0, 'rgba(255,255,255,.10)'); g.addColorStop(1, 'rgba(255,255,255,.04)'); }
    else { g.addColorStop(0, 'rgba(0,229,255,.20)'); g.addColorStop(1, 'rgba(255,61,242,.20)'); }
    ctx.fillStyle = g; ctx.fill();

    if (st.mode === 'aurora') ctx.globalCompositeOperation = 'lighter';

    FINGERS.forEach((idx, fi) => {
      if (st.mode === 'aurora') {
        const hue = (now / 14 + fi * 55) % 360;
        this._poly(pts, idx); ctx.strokeStyle = `hsla(${hue},95%,65%,.16)`; ctx.lineWidth = 15; ctx.stroke();
        this._poly(pts, idx); ctx.strokeStyle = `hsla(${hue},95%,70%,.36)`; ctx.lineWidth = 7; ctx.stroke();
        this._poly(pts, idx); ctx.strokeStyle = 'rgba(255,255,255,.85)'; ctx.lineWidth = 2; ctx.stroke();
      } else if (st.mode === 'minimal') {
        ctx.shadowColor = 'rgba(0,0,0,.4)'; ctx.shadowBlur = 4;
        this._poly(pts, idx); ctx.strokeStyle = 'rgba(255,255,255,.92)'; ctx.lineWidth = st.width; ctx.stroke();
        ctx.shadowBlur = 0;
      } else {
        const col = st.fingers[fi];
        ctx.shadowColor = col; ctx.shadowBlur = st.glow;
        this._poly(pts, idx); ctx.strokeStyle = col; ctx.lineWidth = st.width; ctx.stroke();
        ctx.shadowBlur = 0;
        this._poly(pts, idx); ctx.strokeStyle = 'rgba(255,255,255,.88)'; ctx.lineWidth = 1.3; ctx.stroke();
      }
    });
    ctx.shadowBlur = 0;
    if (st.mode === 'aurora') ctx.globalCompositeOperation = 'source-over';

    // sendi & ujung jari
    for (let i = 0; i < 21; i++) {
      const tip = TIPS.indexOf(i);
      const p = pts[i];
      if (tip >= 0) {
        const col = st.fingers[tip];
        const r = i === 8 ? 8 : 6.5;
        const halo = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, r * 2.6);
        halo.addColorStop(0, st.mode === 'minimal' ? 'rgba(255,255,255,.55)' : col); halo.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.globalAlpha *= 0.55; ctx.fillStyle = halo; ctx.beginPath(); ctx.arc(p.x, p.y, r * 2.6, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha /= 0.55;
        ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(p.x, p.y, r * 0.62, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI * 2); ctx.stroke();
      } else {
        ctx.fillStyle = 'rgba(255,255,255,.92)'; ctx.beginPath(); ctx.arc(p.x, p.y, 2.6, 0, Math.PI * 2); ctx.fill();
      }
    }
  }

  _drawPinch(pts, st, now) {
    const ctx = this.ctx, a = pts[4], b = pts[8];
    const mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
    ctx.save();
    ctx.globalCompositeOperation = 'lighter';
    ctx.strokeStyle = st.accent; ctx.lineWidth = 3; ctx.shadowColor = st.accent; ctx.shadowBlur = 14;
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    const pulse = 11 + Math.sin(now / 70) * 3;
    ctx.beginPath(); ctx.arc(mx, my, pulse, 0, Math.PI * 2); ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.lineWidth = 2; ctx.stroke();
    ctx.restore();
  }

  _drawCursor(pts, st, now) {
    const ctx = this.ctx, p = pts[8], pinch = this.state && this.state.pinch;
    const r = pinch ? 12 : 17;
    ctx.save();
    ctx.shadowColor = st.accent; ctx.shadowBlur = st.mode === 'minimal' ? 0 : 12;
    ctx.lineWidth = 3; ctx.strokeStyle = st.accent; ctx.fillStyle = pinch ? 'rgba(255,255,255,.35)' : 'rgba(255,255,255,.14)';
    ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
    ctx.shadowBlur = 0; ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(p.x, p.y, 3, 0, Math.PI * 2); ctx.fill();
    if (this.dwell > 0) { // progres dwell-click
      ctx.lineWidth = 5; ctx.lineCap = 'round'; ctx.strokeStyle = '#fff'; ctx.shadowColor = st.accent; ctx.shadowBlur = 8;
      ctx.beginPath(); ctx.arc(p.x, p.y, 25, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * this.dwell); ctx.stroke();
    }
    ctx.restore();
  }

  /* ---------------- particles ---------------- */
  _spark(x, y, st) {
    const hue = st.mode === 'neon' ? 300 + Math.random() * 80 : st.mode === 'aurora' ? Math.random() * 360 : st.mode === 'sakura' ? 335 + Math.random() * 20 : 0;
    this.parts.push({ type: 'spark', x, y, vx: (Math.random() - 0.5) * 70, vy: (Math.random() - 0.5) * 70 - 10, life: 0.55 + Math.random() * 0.3, max: 0.8, size: 1.5 + Math.random() * 2.6, color: st.mode === 'minimal' ? 'rgba(255,255,255,A)' : `hsla(${hue},100%,72%,A)` });
  }

  _emitPetals(pts, dt) {
    if (Math.random() > dt * 9) return;
    const p = pts[TIPS[(Math.random() * 5) | 0]];
    this.parts.push({ type: 'petal', x: p.x, y: p.y, vx: (Math.random() - 0.5) * 30, vy: 20 + Math.random() * 30, rot: Math.random() * 6, vr: (Math.random() - 0.5) * 4, life: 1.6, max: 1.6, size: 4 + Math.random() * 4 });
  }

  _stepParticles(dt, st) {
    const ctx = this.ctx;
    this.parts = this.parts.filter((p) => (p.life -= dt) > 0);
    this.parts.forEach((p) => {
      const a = Math.max(0, p.life / p.max);
      p.x += p.vx * dt; p.y += p.vy * dt;
      if (p.type === 'spark') {
        p.vy += 40 * dt;
        ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = p.color.replace('A', a.toFixed(2));
        ctx.beginPath(); ctx.arc(p.x, p.y, p.size * a + 0.4, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      } else {
        p.rot += p.vr * dt; p.vx += Math.sin(p.life * 4) * 18 * dt;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.globalAlpha = a * 0.9; ctx.fillStyle = '#ffb3cf';
        ctx.beginPath(); ctx.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      }
    });
  }

  _drawRipples(now) {
    const ctx = this.ctx;
    this.ripples = this.ripples.filter((r) => now - r.t0 < 560);
    this.ripples.forEach((r) => {
      const k = (now - r.t0) / 560;
      ctx.save(); ctx.globalAlpha = (1 - k) * 0.9; ctx.strokeStyle = r.color; ctx.lineWidth = 4 * (1 - k) + 1; ctx.shadowColor = r.color; ctx.shadowBlur = 10;
      ctx.beginPath(); ctx.arc(r.x, r.y, 8 + k * 58, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
    });
  }

  _drawDebug(now) {
    const ctx = this.ctx, pts = this.cur;
    let minX = 1e9, minY = 1e9, maxX = -1e9, maxY = -1e9;
    ctx.save(); ctx.font = '600 9px sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = '#fff'; ctx.strokeStyle = 'rgba(0,0,0,.6)'; ctx.lineWidth = 2.5;
    pts.forEach((p, i) => { minX = Math.min(minX, p.x); minY = Math.min(minY, p.y); maxX = Math.max(maxX, p.x); maxY = Math.max(maxY, p.y); ctx.strokeText(i, p.x, p.y - 9); ctx.fillText(i, p.x, p.y - 9); });
    ctx.setLineDash([5, 4]); ctx.strokeStyle = 'rgba(255,255,255,.7)'; ctx.lineWidth = 1.2; ctx.strokeRect(minX - 12, minY - 12, maxX - minX + 24, maxY - minY + 24);
    ctx.restore();
  }
}
