/* Hand Gesture Drawing — Phase 2.5 (Canvas 2D + Hand Tracking + HandStudio UI) */
import { HandStudio } from './hand-ui.js';

const root = document.getElementById('draw-studio');
if (root) {
  const $ = (s) => root.querySelector(s);
  const $$ = (s) => Array.from(root.querySelectorAll(s));
  const board = $('[data-draw-board]');
  const main = $('[data-draw-canvas]'), stroke = $('[data-draw-stroke]'), cursor = $('[data-draw-cursor]');
  const ctx = main.getContext('2d', { willReadFrequently: true });
  const sctx = stroke.getContext('2d');
  const cctx = cursor.getContext('2d');
  const colorInput = $('[data-color]'), sizeInput = $('[data-size]'), opacityInput = $('[data-opacity]');
  const titleInput = $('[data-title]'), toastEl = $('[data-draw-toast]'), tipEl = $('[data-draw-tip]');

  const W = 960, H = 600, MAX_UNDO = 20;
  const BG = { white: '#ffffff', cream: '#fbf1dd', night: '#14101f', grid: '#ffffff' };
  let brush = 'pen', eraser = false, color = colorInput.value, size = parseInt(sizeInput.value, 10), opacity = 1, bg = 'white';
  let undoStack = [], redoStack = [], drawing = false, last = null, wSmooth = 8, hue = 0, dirty = false, hasStroke = false;
  let currentId = root.dataset.artworkId || null;
  let handSeen = false, ignorePinch = false;

  [main, stroke, cursor].forEach((c) => { c.width = W; c.height = H; });

  /* ---------------- helpers ---------------- */
  let toastTimer = 0;
  function toast(text, ms = 1600) {
    toastEl.textContent = text; toastEl.hidden = false; clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.hidden = true; }, ms);
  }
  const hexToRgb = (h) => { const n = parseInt(h.replace('#', ''), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; };
  const lighten = (h) => { const [r, g, b] = hexToRgb(h); const m = (v) => Math.round(v + (255 - v) * 0.72); return 'rgb(' + m(r) + ',' + m(g) + ',' + m(b) + ')'; };

  /* Buka kembali karya tersimpan */
  if (root.dataset.artworkImage) {
    const img = new Image();
    img.onload = () => { ctx.drawImage(img, 0, 0, W, H); undoStack = []; redoStack = []; toast('Karya dibuka. Lanjutkan menggambar lalu simpan.', 2600); };
    img.onerror = () => toast('Gagal membuka karya.');
    img.src = root.dataset.artworkImage;
  }

  /* ---------------- undo / redo ---------------- */
  const snap = () => ctx.getImageData(0, 0, W, H);
  function pushUndo() { try { undoStack.push(snap()); } catch (e) { return; } if (undoStack.length > MAX_UNDO) undoStack.shift(); redoStack = []; syncButtons(); }
  function undo() { const s = undoStack.pop(); if (!s) { toast('Tidak ada yang bisa di-undo'); return; } redoStack.push(snap()); ctx.putImageData(s, 0, 0); dirty = true; syncButtons(); }
  function redo() { const s = redoStack.pop(); if (!s) { toast('Tidak ada yang bisa di-redo'); return; } undoStack.push(snap()); ctx.putImageData(s, 0, 0); dirty = true; syncButtons(); }
  function clearAll() { pushUndo(); ctx.clearRect(0, 0, W, H); dirty = true; toast('Kanvas dibersihkan (Undo untuk membatalkan)'); }
  function syncButtons() { $('[data-undo]').disabled = !undoStack.length; $('[data-redo]').disabled = !redoStack.length; }
  syncButtons();

  /* ---------------- tool state ---------------- */
  function syncUI() {
    $$('[data-brush]').forEach((b) => { const on = !eraser && b.dataset.brush === brush; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    const eb = $('[data-tool-eraser]'); eb.classList.toggle('active', eraser); eb.setAttribute('aria-pressed', eraser ? 'true' : 'false');
    const pe = $('[data-dh="eraser"]'); if (pe) pe.classList.toggle('active', eraser);
    $('[data-size-label]').textContent = size;
    const dot = $('[data-size-dot]'); dot.style.width = dot.style.height = Math.min(44, Math.max(4, size)) + 'px'; dot.style.background = eraser ? '#bbb' : color;
    $('[data-opacity-label]').textContent = Math.round(opacity * 100) + '%';
    $$('[data-swatch]').forEach((s) => s.classList.toggle('active', s.dataset.swatch.toLowerCase() === color.toLowerCase()));
    tipEl.hidden = !(brush === 'neon' && !eraser && bg !== 'night');
  }
  function setColor(c) { color = c; colorInput.value = c; eraser = false; syncUI(); }
  function setSize(n) { size = Math.max(2, Math.min(60, n)); sizeInput.value = size; syncUI(); }

  $$('[data-brush]').forEach((b) => b.addEventListener('click', () => { brush = b.dataset.brush; eraser = false; syncUI(); }));
  $('[data-tool-eraser]').addEventListener('click', () => { eraser = !eraser; syncUI(); });
  colorInput.addEventListener('input', () => setColor(colorInput.value));
  $$('[data-swatch]').forEach((b) => b.addEventListener('click', () => setColor(b.dataset.swatch)));
  sizeInput.addEventListener('input', () => setSize(parseInt(sizeInput.value, 10)));
  opacityInput.addEventListener('input', () => { opacity = parseInt(opacityInput.value, 10) / 100; syncUI(); });
  $$('[data-bg]').forEach((b) => b.addEventListener('click', () => {
    bg = b.dataset.bg; board.dataset.bg = bg;
    $$('[data-bg]').forEach((x) => x.classList.toggle('active', x === b));
    dirty = true; syncUI();
  }));
  $('[data-undo]').addEventListener('click', undo);
  $('[data-redo]').addEventListener('click', redo);
  $('[data-clear]').addEventListener('click', () => { if (window.confirm('Bersihkan seluruh kanvas?')) clearAll(); });

  /* warna terakhir */
  let recent = [];
  try { recent = JSON.parse(localStorage.getItem('wedi-draw-recent') || '[]'); } catch (e) { recent = []; }
  function renderRecent() {
    const wrap = $('[data-recent]'); wrap.innerHTML = '';
    recent.forEach((c) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'draw-swatch sm'; b.style.setProperty('--c', c); b.setAttribute('aria-label', 'Warna ' + c); b.addEventListener('click', () => setColor(c)); wrap.appendChild(b); });
  }
  function addRecent(c) {
    if (eraser) return;
    recent = [c].concat(recent.filter((x) => x.toLowerCase() !== c.toLowerCase())).slice(0, 6);
    try { localStorage.setItem('wedi-draw-recent', JSON.stringify(recent)); } catch (e) { /* noop */ }
    renderRecent();
  }
  renderRecent(); syncUI();

  /* ---------------- stroke engine ---------------- */
  function style(c, p, speed) {
    c.lineCap = 'round'; c.lineJoin = 'round';
    if (eraser) { c.globalCompositeOperation = 'destination-out'; c.strokeStyle = '#000'; c.fillStyle = '#000'; c.lineWidth = size * 1.8; return; }
    c.globalCompositeOperation = 'source-over';
    if (brush === 'rainbow') { hue = (hue + 2 + speed * 0.6) % 360; c.strokeStyle = c.fillStyle = 'hsl(' + hue + ' 92% 56%)'; c.lineWidth = size; }
    else if (brush === 'marker') { c.strokeStyle = c.fillStyle = color; c.lineWidth = size * 1.6; }
    else if (brush === 'pen') { c.strokeStyle = c.fillStyle = color; const target = size * (1.25 - Math.min(speed / 45, 0.75)); wSmooth = wSmooth * 0.7 + target * 0.3; c.lineWidth = wSmooth; }
    else { c.strokeStyle = c.fillStyle = color; c.lineWidth = size; }
  }

  function dot(p) {
    const c = eraser ? ctx : sctx;
    c.save(); style(c, p, 0);
    if (!eraser && brush === 'neon') { c.shadowColor = color; c.shadowBlur = size * 1.6 + 6; }
    c.beginPath(); c.arc(p.x, p.y, Math.max(0.8, c.lineWidth / 2), 0, Math.PI * 2); c.fill();
    if (!eraser && brush === 'neon') { c.shadowBlur = 0; c.fillStyle = lighten(color); c.beginPath(); c.arc(p.x, p.y, Math.max(0.6, size * 0.19), 0, Math.PI * 2); c.fill(); }
    c.restore();
  }

  function segment(p) {
    const mx = (last.x + p.x) / 2, my = (last.y + p.y) / 2;
    const speed = Math.hypot(p.x - last.x, p.y - last.y);
    const c = eraser ? ctx : sctx;
    c.save(); style(c, p, speed);
    const path = () => { c.beginPath(); c.moveTo(last.mx, last.my); c.quadraticCurveTo(last.x, last.y, mx, my); c.stroke(); };
    if (!eraser && brush === 'neon') {
      c.shadowColor = color; c.shadowBlur = size * 1.6 + 6; path();
      c.shadowBlur = 0; c.strokeStyle = lighten(color); c.lineWidth = Math.max(1, size * 0.38); path();
    } else path();
    c.restore();
    last = { x: p.x, y: p.y, mx, my };
  }

  function begin(p) {
    pushUndo(); drawing = true; hasStroke = !eraser; dirty = true;
    last = { x: p.x, y: p.y, mx: p.x, my: p.y }; wSmooth = size;
    stroke.style.opacity = eraser ? 1 : opacity;
    dot(p);
  }
  function move(p) { if (drawing) segment(p); }
  function end() {
    if (!drawing) return;
    drawing = false; last = null;
    if (hasStroke) { ctx.save(); ctx.globalAlpha = opacity; ctx.globalCompositeOperation = 'source-over'; ctx.drawImage(stroke, 0, 0); ctx.restore(); sctx.clearRect(0, 0, W, H); stroke.style.opacity = 1; addRecent(color); }
    hasStroke = false;
  }

  function drawCursor(p, active) {
    cctx.clearRect(0, 0, W, H);
    if (!p) return;
    const r = Math.max(4, (eraser ? size * 1.8 : brush === 'marker' ? size * 1.6 : size) / 2);
    cctx.lineWidth = 3.5; cctx.strokeStyle = 'rgba(255,255,255,.9)'; cctx.beginPath(); cctx.arc(p.x, p.y, r + 0.5, 0, Math.PI * 2); cctx.stroke();
    cctx.lineWidth = 1.6; cctx.strokeStyle = eraser ? '#666' : color; cctx.fillStyle = active ? 'rgba(255,93,143,.2)' : 'rgba(255,255,255,0)'; cctx.beginPath(); cctx.arc(p.x, p.y, r + 0.5, 0, Math.PI * 2); cctx.fill(); cctx.stroke();
    cctx.fillStyle = '#222'; cctx.beginPath(); cctx.arc(p.x, p.y, 1.8, 0, Math.PI * 2); cctx.fill();
  }

  /* ---------------- mouse / touch ---------------- */
  const toCanvas = (e) => { const r = board.getBoundingClientRect(); return { x: ((e.clientX - r.left) / r.width) * W, y: ((e.clientY - r.top) / r.height) * H }; };
  cursor.style.touchAction = 'none';
  cursor.addEventListener('pointerdown', (e) => { if (handSeen) return; e.preventDefault(); cursor.setPointerCapture(e.pointerId); begin(toCanvas(e)); });
  cursor.addEventListener('pointermove', (e) => { if (handSeen) return; const p = toCanvas(e); drawCursor(p, drawing); if (drawing) move(p); });
  cursor.addEventListener('pointerup', () => { if (!handSeen) end(); });
  cursor.addEventListener('pointerleave', () => { if (!handSeen) { end(); drawCursor(null); } });

  /* ---------------- hand: pinch = kuas turun ---------------- */
  const hand = new HandStudio({
    root, board, arSupported: false, fx: { cursor: false, skeleton: 0.55, hideTrailOnPinch: true },
    onFrame: (s) => {
      handSeen = s.detected;
      if (!s.detected) { end(); drawCursor(null); ignorePinch = false; return; }
      if (s.overTarget && s.pinch) ignorePinch = true;
      if (!s.pinch) ignorePinch = false;
      if (s.overTarget) { end(); drawCursor(null); return; }
      const pt = { x: s.pointer.x * W, y: s.pointer.y * H }, pp = { x: s.pinchPoint.x * W, y: s.pinchPoint.y * H };
      if (s.pinch && !ignorePinch) { if (!drawing) begin(pp); else move(pp); } else end();
      drawCursor(s.pinch ? pp : pt, s.pinch && !ignorePinch);
    }
  });

  /* palet yang bisa "ditekan" dengan menunjuk (dwell) */
  $$('[data-dh-color]').forEach((b) => b.addEventListener('click', () => { setColor(b.dataset.dhColor); toast('Warna dipilih'); }));
  const dh = {
    undo, redo,
    eraser: () => { eraser = !eraser; syncUI(); toast(eraser ? 'Penghapus aktif' : 'Kuas aktif'); },
    smaller: () => { setSize(size - 4); toast('Ukuran ' + size); },
    bigger: () => { setSize(size + 4); toast('Ukuran ' + size); },
    clear: clearAll,
    save: () => $('[data-save]').click()
  };
  $$('[data-dh]').forEach((b) => b.addEventListener('click', () => dh[b.dataset.dh] && dh[b.dataset.dh]()));

  /* ---------------- save / download ---------------- */
  const meta = document.querySelector('meta[name="csrf-token"]');
  function composite() {
    const t = document.createElement('canvas'); t.width = W; t.height = H;
    const c = t.getContext('2d');
    c.fillStyle = BG[bg]; c.fillRect(0, 0, W, H);
    if (bg === 'grid') { c.strokeStyle = '#e3e6ef'; c.lineWidth = 1; c.beginPath(); for (let x = 0; x <= W; x += 32) { c.moveTo(x + 0.5, 0); c.lineTo(x + 0.5, H); } for (let y = 0; y <= H; y += 32) { c.moveTo(0, y + 0.5); c.lineTo(W, y + 0.5); } c.stroke(); }
    c.drawImage(main, 0, 0);
    return t;
  }
  function send(url, method) {
    toast('Menyimpan…', 8000);
    return fetch(url, {
      method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
      body: JSON.stringify({ image: composite().toDataURL('image/png'), title: titleInput.value.trim() || null })
    }).then((r) => r.json().catch(() => ({})).then((d) => ({ ok: r.ok, d }))).then(({ ok, d }) => {
      if (!ok) throw new Error(d.message || 'Gagal menyimpan karya.');
      dirty = false; currentId = d.id; root.dataset.updateUrl = root.dataset.storeUrl + '/' + d.id;
      toast('Tersimpan ✓ — lihat di galeri', 2600);
    }).catch((e) => toast(e.message || 'Gagal menyimpan. Coba lagi.', 3200));
  }
  $('[data-save]').addEventListener('click', () => (currentId && root.dataset.updateUrl ? send(root.dataset.updateUrl, 'PUT') : send(root.dataset.storeUrl, 'POST')));
  const saveNew = $('[data-save-new]');
  if (saveNew) saveNew.addEventListener('click', () => send(root.dataset.storeUrl, 'POST'));
  $('[data-download]').addEventListener('click', (e) => { e.currentTarget.href = composite().toDataURL('image/png'); });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------------- keyboard ---------------- */
  document.addEventListener('keydown', (e) => {
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) && e.target.type !== 'range') return;
    const k = e.key.toLowerCase(), mod = e.ctrlKey || e.metaKey;
    if (mod && k === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
    else if (mod && k === 'y') { e.preventDefault(); redo(); }
    else if (!mod && k === 'b') { eraser = false; syncUI(); }
    else if (!mod && k === 'e') { eraser = true; syncUI(); }
    else if (!mod && k === '[') setSize(size - 2);
    else if (!mod && k === ']') setSize(size + 2);
  });
}
