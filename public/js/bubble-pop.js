/* Bubble Pop — Phase 2.4 (Canvas API + Hand Tracking Engine + HandStudio UI) */
import { HandStudio } from './hand-ui.js';

const root = document.getElementById('bubble-pop');
if (root) {
  const $ = (s) => root.querySelector(s);
  const board = $('[data-bp-board]');
  const canvas = $('[data-bp-canvas]');
  const ctx = canvas.getContext('2d');
  const hud = { time: $('[data-bp-time]'), score: $('[data-bp-score]'), level: $('[data-bp-level]'), combo: $('[data-bp-combo]'), comboPill: $('[data-bp-combo-pill]'), burst: $('[data-bp-burst-state]'), burstPill: $('[data-bp-burst]') };
  const ui = { intro: $('[data-bp-intro]'), count: $('[data-bp-count]'), countNum: $('[data-bp-count-num]'), pause: $('[data-bp-pausemenu]'), result: $('[data-bp-result]'), corner: $('[data-bp-corner]'), timebar: $('[data-bp-timebar]'), soundBtn: $('[data-bp-sound]') };

  const LEVELS = {
    easy: { duration: 60, spawn: 0.95, speed: [38, 70], radius: [36, 54], gold: 0.08, dark: 0, stars: [20, 40, 65] },
    normal: { duration: 60, spawn: 0.72, speed: [60, 110], radius: [28, 46], gold: 0.08, dark: 0.08, stars: [30, 55, 85] },
    hard: { duration: 60, spawn: 0.52, speed: [92, 160], radius: [24, 40], gold: 0.1, dark: 0.14, stars: [35, 65, 100] }
  };
  const COLORS = ['#f2a7c3', '#c9b6f2', '#9fdcc3', '#9fcdea', '#f9c6a5', '#f6d98a'];
  const BURST_COOLDOWN = 3, BURST_RADIUS = 125;

  /* ---------------- sound ---------------- */
  const sfx = (() => {
    let ac = null, muted = false, lastPop = 0;
    try { muted = localStorage.getItem('wedi-sfx') === 'off'; } catch (e) { /* noop */ }
    function init() {
      if (!ac) { try { ac = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ac = null; } }
      if (ac && ac.state === 'suspended') ac.resume();
    }
    function tone(freq, dur, type, vol, slide) {
      if (muted || !ac) return;
      const t = ac.currentTime, o = ac.createOscillator(), g = ac.createGain();
      o.type = type || 'sine'; o.frequency.setValueAtTime(freq, t);
      if (slide) o.frequency.exponentialRampToValueAtTime(Math.max(40, freq + slide), t + dur);
      g.gain.setValueAtTime(vol || 0.08, t); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
      o.connect(g); g.connect(ac.destination); o.start(t); o.stop(t + dur + 0.02);
    }
    return {
      init, get muted() { return muted; },
      setMuted(v) { muted = v; try { localStorage.setItem('wedi-sfx', v ? 'off' : 'on'); } catch (e) { /* noop */ } },
      pop(combo) { const n = performance.now(); if (n - lastPop < 28) return; lastPop = n; tone(500 + Math.min(combo, 14) * 38, 0.11, 'sine', 0.09, 260); },
      gold() { tone(880, 0.14, 'triangle', 0.1, 420); setTimeout(() => tone(1320, 0.16, 'triangle', 0.08), 70); },
      bad() { tone(170, 0.28, 'sawtooth', 0.07, -90); },
      burst() { tone(260, 0.34, 'square', 0.05, 700); },
      level() { [660, 880, 1100].forEach((f, i) => setTimeout(() => tone(f, 0.16, 'triangle', 0.09), i * 90)); },
      tick() { tone(720, 0.07, 'square', 0.04); },
      go() { tone(1000, 0.24, 'triangle', 0.1, 320); },
      win() { [523, 659, 784, 1047].forEach((f, i) => setTimeout(() => tone(f, 0.2, 'triangle', 0.09), i * 110)); }
    };
  })();

  /* ---------------- state ---------------- */
  let W = 640, H = 440, dpr = 1;
  let state = 'intro'; // intro | countdown | play | paused | result
  let cfg = LEVELS.normal, levelKey = 'normal';
  let bubbles = [], particles = [], floaters = [], bokeh = [];
  let timeLeft = 0, spawnTimer = 0, score = 0, popped = 0, combo = 0, bestCombo = 0, comboTimer = 0, level = 1, burstCd = 0, shake = 0, cd = 0, cdShown = 0, banner = null;
  let best = parseInt(root.dataset.best || '0', 10) || 0;
  const pointer = { x: -999, y: -999, px: null, py: null, active: false, pinch: false, source: 'mouse' };
  let handSeen = false, lastPinch = false, overTarget = false, arOn = false;

  /* ---------------- sizing ---------------- */
  function resize() {
    const rect = board.getBoundingClientRect();
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = Math.max(280, Math.round(rect.width));
    H = Math.round(Math.min(Math.max(W * 0.66, 340), Math.max(window.innerHeight * 0.64, 340)));
    board.style.height = H + 'px';
    canvas.style.height = H + 'px';
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (!bokeh.length) for (let i = 0; i < 16; i++) bokeh.push({ x: Math.random(), y: Math.random(), r: 14 + Math.random() * 46, sp: 4 + Math.random() * 10, a: 0.05 + Math.random() * 0.1 });
  }
  window.addEventListener('resize', resize);
  resize();

  /* ---------------- helpers ---------------- */
  const rand = (a, b) => a + Math.random() * (b - a);
  const css = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  const rgba = (hex, a) => { const n = parseInt(hex.slice(1), 16); return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')'; };
  function distToSeg(px, py, ax, ay, bx, by) {
    const dx = bx - ax, dy = by - ay, l2 = dx * dx + dy * dy;
    let t = l2 ? ((px - ax) * dx + (py - ay) * dy) / l2 : 0; t = Math.max(0, Math.min(1, t));
    return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
  }
  const vibrate = (ms) => { if (navigator.vibrate) navigator.vibrate(ms); };

  /* ---------------- spawn / pop ---------------- */
  function spawn() {
    const r = rand(cfg.radius[0], cfg.radius[1]);
    const roll = Math.random();
    let type = 'normal';
    if (roll < cfg.dark) type = 'dark'; else if (roll < cfg.dark + cfg.gold) type = 'gold';
    bubbles.push({
      x: rand(r, W - r), y: H + r, r: type === 'gold' ? r * 0.82 : r,
      vy: rand(cfg.speed[0], cfg.speed[1]) * (1 + 0.06 * (level - 1)),
      wob: rand(10, 36), wobSpeed: rand(1, 2.4), phase: rand(0, 6.28), t: 0, type,
      color: COLORS[(Math.random() * COLORS.length) | 0]
    });
  }

  function addScore(delta, x, y, color, big) {
    score = Math.max(0, score + delta);
    floaters.push({ x, y, text: (delta > 0 ? '+' : '') + delta, life: 0.95, color: color || '#fff', big });
  }

  function burstParticles(x, y, color, n, rings) {
    for (let i = 0; i < n; i++) { const a = rand(0, Math.PI * 2), s = rand(60, 230); particles.push({ x, y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: rand(0.35, 0.75), max: 0.75, color, size: rand(2, 5) }); }
    if (rings) particles.push({ ring: true, x, y, life: 0.4, max: 0.4, size: rings, color: '#fff' });
  }

  function pop(b) {
    b.dead = true;
    if (b.type === 'dark') {
      combo = 0; shake = 0.3; sfx.bad(); vibrate(60);
      addScore(-3, b.x, b.y, '#ff8aa0');
      burstParticles(b.x, b.y, '#7a2a5e', 20, b.r * 1.6);
      return;
    }
    popped++; combo++; comboTimer = 1.3; bestCombo = Math.max(bestCombo, combo);
    const mult = Math.min(3, 1 + Math.floor(combo / 6));
    if (b.type === 'gold') { sfx.gold(); addScore(5 * mult, b.x, b.y, '#ffd166', true); burstParticles(b.x, b.y, '#ffd166', 26, b.r * 1.8); vibrate(25); }
    else { sfx.pop(combo); addScore(mult, b.x, b.y, '#fff'); burstParticles(b.x, b.y, b.color, 12, b.r * 1.4); vibrate(8); }
    const nl = 1 + Math.floor(popped / 12);
    if (nl > level) { level = nl; banner = { text: 'Level ' + level + '!', t: 1.4 }; sfx.level(); }
  }

  function shockwave(x, y) {
    if (burstCd > 0 || state !== 'play') return;
    burstCd = BURST_COOLDOWN; sfx.burst(); vibrate(30);
    particles.push({ ring: true, x, y, life: 0.5, max: 0.5, size: BURST_RADIUS, color: '#fff' });
    bubbles.forEach((b) => { if (!b.dead && b.type !== 'dark' && Math.hypot(b.x - x, b.y - y) < BURST_RADIUS + b.r) pop(b); });
  }

  /* ---------------- flow ---------------- */
  const show = (el, on) => { el.hidden = !on; };
  function setState(s) {
    state = s;
    show(ui.intro, s === 'intro'); show(ui.count, s === 'countdown'); show(ui.pause, s === 'paused'); show(ui.result, s === 'result');
    show(ui.corner, s === 'play'); root.dataset.bpState = s;
  }

  function resetRound() {
    bubbles = []; particles = []; floaters = []; banner = null;
    timeLeft = cfg.duration; spawnTimer = 0.2; score = 0; popped = 0; combo = 0; bestCombo = 0; comboTimer = 0; level = 1; burstCd = 0; shake = 0;
  }

  function startGame() {
    sfx.init();
    const picked = root.querySelector('input[name="bp-level"]:checked');
    levelKey = picked ? picked.value : 'normal'; cfg = LEVELS[levelKey];
    resetRound();
    cd = 3; cdShown = 0; setState('countdown');
  }
  function toMenu() { resetRound(); setState('intro'); }
  function togglePause() {
    if (state === 'play') { setState('paused'); }
    else if (state === 'paused') { setState('play'); }
  }

  function starsFor(sc) { return cfg.stars.filter((t) => sc >= t).length; }

  function finish() {
    setState('result');
    const before = best, isRecord = score > before && score > 0;
    $('[data-bp-final]').textContent = score;
    $('[data-bp-stat-popped]').textContent = popped;
    $('[data-bp-stat-combo]').textContent = bestCombo;
    $('[data-bp-stat-level]').textContent = level;
    $('[data-bp-stat-best]').textContent = Math.max(best, score) || '—';
    $('[data-bp-result-title]').textContent = isRecord ? 'Luar biasa!' : 'Waktu habis!';
    show($('[data-bp-record]'), isRecord);
    const n = starsFor(score);
    $('[data-bp-stars]').querySelectorAll('i').forEach((s, i) => { s.classList.remove('on'); void s.offsetWidth; s.classList.toggle('on', i < n); });
    if (isRecord) { sfx.win(); confetti(); } else if (n > 0) sfx.level();
    const status = $('[data-bp-save-status]');
    status.textContent = 'Menyimpan skor…';
    const meta = document.querySelector('meta[name="csrf-token"]');
    fetch(root.dataset.scoreUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
      body: JSON.stringify({ game_id: 'bubble-pop', score: Math.min(score, 99999), duration_seconds: Math.round(cfg.duration) })
    }).then((r) => (r.ok ? r.json() : Promise.reject())).then((d) => {
      best = d.best_score != null ? d.best_score : Math.max(best, score);
      $('[data-bp-stat-best]').textContent = best || '—';
      status.textContent = 'Skor tersimpan ✓';
    }).catch(() => { best = Math.max(best, score); status.textContent = 'Skor belum tersimpan (cek koneksi).'; });
  }

  function confetti() {
    const cols = ['#ff4d8d', '#ffd166', '#3df5c8', '#4dc3ff', '#b48cff'];
    for (let i = 0; i < 110; i++) particles.push({ confetti: true, x: rand(0, W), y: rand(-40, 0), vx: rand(-60, 60), vy: rand(80, 220), rot: rand(0, 6), vr: rand(-8, 8), life: rand(1.8, 3), max: 3, color: cols[i % cols.length], size: rand(5, 9) });
  }

  /* ---------------- update ---------------- */
  function update(dt) {
    if (burstCd > 0) burstCd = Math.max(0, burstCd - dt);
    if (shake > 0) shake = Math.max(0, shake - dt);
    if (banner) { banner.t -= dt; if (banner.t <= 0) banner = null; }
    particles.forEach((p) => { p.life -= dt; if (p.ring) return; p.x += p.vx * dt; p.y += p.vy * dt; if (p.confetti) p.rot += p.vr * dt; else p.vy += 260 * dt; });
    particles = particles.filter((p) => p.life > 0);
    floaters.forEach((f) => { f.life -= dt; f.y -= 42 * dt; });
    floaters = floaters.filter((f) => f.life > 0);
    bokeh.forEach((b) => { b.y -= (b.sp * dt) / H; if (b.y < -0.1) b.y = 1.1; });

    if (state === 'countdown') {
      cd -= dt;
      const shown = Math.ceil(cd);
      if (shown !== cdShown && shown > 0) { cdShown = shown; ui.countNum.textContent = shown; ui.countNum.classList.remove('pulse'); void ui.countNum.offsetWidth; ui.countNum.classList.add('pulse'); sfx.tick(); }
      if (cd <= 0) { sfx.go(); setState('play'); floaters.push({ x: W / 2, y: H / 2, text: 'GO!', life: 1, color: '#fff', big: true }); }
      return;
    }
    if (state === 'paused') return;
    if (state !== 'play') { bubbles.forEach((b) => { b.t += dt; b.y -= b.vy * 0.25 * dt; }); bubbles = bubbles.filter((b) => b.y > -b.r * 2); return; }

    timeLeft -= dt;
    if (comboTimer > 0) { comboTimer -= dt; if (comboTimer <= 0) combo = 0; }
    spawnTimer -= dt;
    if (spawnTimer <= 0) { spawn(); spawnTimer = cfg.spawn * Math.max(0.5, Math.pow(0.93, level - 1)) * rand(0.8, 1.2); }

    const canPop = pointer.active && !overTarget;
    bubbles.forEach((b) => {
      b.t += dt; b.y -= b.vy * dt; b.x += Math.sin(b.t * b.wobSpeed + b.phase) * b.wob * dt;
      b.x = Math.min(W - b.r, Math.max(b.r, b.x));
      if (canPop && !b.dead) {
        const ax = pointer.px == null ? pointer.x : pointer.px, ay = pointer.py == null ? pointer.y : pointer.py;
        if (distToSeg(b.x, b.y, ax, ay, pointer.x, pointer.y) <= b.r + 10) pop(b);
      }
    });
    pointer.px = pointer.active ? pointer.x : null; pointer.py = pointer.active ? pointer.y : null;
    bubbles = bubbles.filter((b) => !b.dead && b.y > -b.r * 2);
    if (timeLeft <= 0) { timeLeft = 0; finish(); }
  }

  /* ---------------- render ---------------- */
  function drawBubble(b, now) {
    const sc = Math.min(1, 0.4 + b.t * 3), r = b.r * sc;
    ctx.save();
    if (b.type === 'gold') { ctx.shadowColor = '#ffd166'; ctx.shadowBlur = 18 + Math.sin(now / 160) * 6; }
    if (b.type === 'dark') { ctx.shadowColor = '#ff4d6d'; ctx.shadowBlur = 10 + Math.sin(now / 120) * 4; }
    const g = ctx.createRadialGradient(b.x - r * 0.3, b.y - r * 0.35, r * 0.05, b.x, b.y, r);
    if (b.type === 'dark') { g.addColorStop(0, '#7a3d70'); g.addColorStop(1, '#2a0f26'); }
    else if (b.type === 'gold') { g.addColorStop(0, '#fff6cf'); g.addColorStop(1, '#f2b705'); }
    else { g.addColorStop(0, 'rgba(255,255,255,.88)'); g.addColorStop(0.28, rgba(b.color, 0.38)); g.addColorStop(0.82, rgba(b.color, 0.3)); g.addColorStop(1, rgba(b.color, 0.8)); }
    ctx.beginPath(); ctx.arc(b.x, b.y, r, 0, Math.PI * 2); ctx.fillStyle = g; ctx.fill();
    ctx.shadowBlur = 0;
    if (b.type === 'normal' && ctx.createConicGradient) { // kilau pelangi tipis
      const cg = ctx.createConicGradient(now / 1600 + b.phase, b.x, b.y);
      cg.addColorStop(0, 'rgba(255,120,200,.20)'); cg.addColorStop(0.33, 'rgba(120,200,255,.20)'); cg.addColorStop(0.66, 'rgba(160,255,200,.20)'); cg.addColorStop(1, 'rgba(255,120,200,.20)');
      ctx.beginPath(); ctx.arc(b.x, b.y, r, 0, Math.PI * 2); ctx.fillStyle = cg; ctx.fill();
    }
    ctx.lineWidth = 2; ctx.strokeStyle = b.type === 'dark' ? 'rgba(255,120,150,.8)' : 'rgba(255,255,255,.75)'; ctx.beginPath(); ctx.arc(b.x, b.y, r, 0, Math.PI * 2); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.78)'; ctx.beginPath(); ctx.ellipse(b.x - r * 0.38, b.y - r * 0.42, r * 0.22, r * 0.12, -0.7, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.4)'; ctx.beginPath(); ctx.arc(b.x + r * 0.42, b.y + r * 0.4, r * 0.07, 0, Math.PI * 2); ctx.fill();
    if (b.type !== 'normal') {
      ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(r * 0.8) + 'px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(b.type === 'gold' ? '★' : '✕', b.x, b.y + 1);
    }
    ctx.restore();
  }

  function render(now) {
    ctx.save();
    if (shake > 0) ctx.translate(rand(-4, 4), rand(-4, 4));
    if (arOn) { ctx.clearRect(-10, -10, W + 20, H + 20); const v = ctx.createRadialGradient(W / 2, H / 2, H * 0.35, W / 2, H / 2, H * 0.85); v.addColorStop(0, 'rgba(0,0,0,0)'); v.addColorStop(1, 'rgba(20,8,30,.35)'); ctx.fillStyle = v; ctx.fillRect(0, 0, W, H); }
    else {
      const bg = ctx.createLinearGradient(0, 0, 0, H);
      bg.addColorStop(0, css('--brand-soft', '#e6e9f5')); bg.addColorStop(1, css('--surface-2', '#f4ece2'));
      ctx.fillStyle = bg; ctx.fillRect(-10, -10, W + 20, H + 20);
      bokeh.forEach((b) => { ctx.fillStyle = rgba('#ffffff', b.a * 2.2); ctx.beginPath(); ctx.arc(b.x * W, b.y * H, b.r, 0, Math.PI * 2); ctx.fill(); });
    }
    bubbles.forEach((b) => drawBubble(b, now));
    particles.forEach((p) => {
      const a = Math.max(0, p.life / p.max);
      if (p.ring) { ctx.beginPath(); ctx.arc(p.x, p.y, p.size * (1 - a) + 6, 0, Math.PI * 2); ctx.strokeStyle = 'rgba(255,255,255,' + a + ')'; ctx.lineWidth = 4 * a + 1; ctx.stroke(); return; }
      if (p.confetti) { ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.globalAlpha = Math.min(1, a * 2); ctx.fillStyle = p.color; ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2); ctx.restore(); return; }
      ctx.globalAlpha = a; ctx.fillStyle = p.color; ctx.beginPath(); ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
    });
    floaters.forEach((f) => {
      ctx.globalAlpha = Math.min(1, f.life * 1.6); ctx.fillStyle = f.color; ctx.strokeStyle = 'rgba(61,21,52,.6)'; ctx.lineWidth = 3.5; ctx.lineJoin = 'round';
      ctx.font = '800 ' + (f.big ? 30 : 18) + 'px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.strokeText(f.text, f.x, f.y); ctx.fillText(f.text, f.x, f.y); ctx.globalAlpha = 1;
    });
    if (banner) {
      const k = Math.min(1, banner.t / 0.35, (1.4 - banner.t) / 0.25 + 0.2);
      ctx.save(); ctx.globalAlpha = Math.max(0, Math.min(1, k)); ctx.translate(W / 2, H * 0.32); ctx.scale(1 + (1 - Math.min(1, k)) * 0.3, 1 + (1 - Math.min(1, k)) * 0.3);
      ctx.font = '800 44px Fraunces, serif'; ctx.textAlign = 'center'; ctx.lineWidth = 6; ctx.strokeStyle = 'rgba(61,21,52,.55)'; ctx.fillStyle = '#fff';
      ctx.strokeText(banner.text, 0, 0); ctx.fillText(banner.text, 0, 0); ctx.restore();
    }
    if (pointer.active && pointer.source === 'mouse') { // kursor mouse; kursor tangan digambar oleh HandFX
      ctx.beginPath(); ctx.arc(pointer.x, pointer.y, 12, 0, Math.PI * 2);
      ctx.lineWidth = 3; ctx.strokeStyle = css('--brand', '#3e4b8e'); ctx.fillStyle = 'rgba(255,255,255,.35)'; ctx.fill(); ctx.stroke();
    }
    ctx.restore();
  }

  let hudCache = {};
  function setText(key, el, v) { if (hudCache[key] !== v) { hudCache[key] = v; el.textContent = v; } }
  function updateHud() {
    const total = cfg.duration, t = state === 'play' || state === 'paused' ? timeLeft : total;
    setText('t', hud.time, Math.ceil(t)); setText('s', hud.score, score); setText('l', hud.level, level);
    const mult = Math.min(3, 1 + Math.floor(combo / 6));
    setText('c', hud.combo, 'x' + mult + (combo > 1 ? ' · ' + combo : ''));
    hud.comboPill.classList.toggle('hot', combo >= 6);
    setText('b', hud.burst, burstCd > 0 ? Math.ceil(burstCd) + 's' : 'siap');
    hud.burstPill.style.setProperty('--cd', (burstCd > 0 ? 1 - burstCd / BURST_COOLDOWN : 1).toFixed(3));
    hud.burstPill.classList.toggle('ready', burstCd <= 0);
    ui.timebar.style.transform = 'scaleX(' + Math.max(0, t / total).toFixed(4) + ')';
    ui.timebar.classList.toggle('warn', state === 'play' && t <= 10);
  }

  let last = performance.now();
  function frame(now) {
    const dt = Math.min(0.05, (now - last) / 1000); last = now;
    update(dt); render(now); updateHud();
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);

  /* ---------------- input: mouse / touch ---------------- */
  function setFromEvent(e) {
    if (handSeen) return;
    const r = canvas.getBoundingClientRect();
    pointer.x = e.clientX - r.left; pointer.y = e.clientY - r.top; pointer.active = true; pointer.source = 'mouse';
  }
  canvas.style.touchAction = 'none';
  canvas.addEventListener('pointermove', setFromEvent);
  canvas.addEventListener('pointerdown', (e) => { sfx.init(); setFromEvent(e); try { canvas.setPointerCapture(e.pointerId); } catch (x) { /* noop */ } });
  canvas.addEventListener('pointerleave', () => { if (!handSeen) pointer.active = false; });
  canvas.addEventListener('pointerup', (e) => { if (e.pointerType === 'touch' && !handSeen) pointer.active = false; });

  /* ---------------- hand ---------------- */
  const hand = new HandStudio({
    root, board, arSupported: true,
    fx: { cursor: true, skeleton: 0.95 },
    onAR: (on) => { arOn = on; board.classList.toggle('is-ar', on); },
    onFrame: (s) => {
      handSeen = s.detected; overTarget = !!s.overTarget;
      if (!s.detected) { if (pointer.source === 'hand') { pointer.active = false; pointer.px = null; } pointer.pinch = false; lastPinch = false; return; }
      pointer.x = s.pointer.x * W; pointer.y = s.pointer.y * H; pointer.active = true; pointer.source = 'hand'; pointer.pinch = s.pinch;
      if (s.pinch && !lastPinch && !overTarget) shockwave(pointer.x, pointer.y);
      lastPinch = s.pinch;
    }
  });

  /* ---------------- controls ---------------- */
  $('[data-bp-start]').addEventListener('click', startGame);
  $('[data-bp-again]').addEventListener('click', startGame);
  $('[data-bp-menu]').addEventListener('click', toMenu);
  $('[data-bp-pause]').addEventListener('click', togglePause);
  $('[data-bp-resume]').addEventListener('click', togglePause);
  $('[data-bp-quit]').addEventListener('click', toMenu);
  function syncSound() { const i = ui.soundBtn.querySelector('i'); i.className = sfx.muted ? 'ri-volume-mute-line' : 'ri-volume-up-line'; ui.soundBtn.classList.toggle('off', sfx.muted); }
  ui.soundBtn.addEventListener('click', () => { sfx.init(); sfx.setMuted(!sfx.muted); syncSound(); });
  syncSound();
  root.addEventListener('pointerdown', () => sfx.init(), { once: true });
  hand.el.toggle.addEventListener('click', () => sfx.init());

  document.addEventListener('keydown', (e) => {
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) && e.target.type !== 'radio') return;
    if (e.code === 'Space' && (state === 'intro' || state === 'result')) { e.preventDefault(); startGame(); }
    else if (e.key.toLowerCase() === 'p' || (e.key === 'Escape' && (state === 'play' || state === 'paused'))) togglePause();
    else if (e.key.toLowerCase() === 'm') ui.soundBtn.click();
  });
  document.addEventListener('visibilitychange', () => { if (document.hidden && state === 'play') togglePause(); });

  $('[data-bp-stat-best]').textContent = best || '—';
  setState('intro');
}
