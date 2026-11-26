/* HandStudio — UI/UX bersama untuk semua fitur berbasis tangan.
 * Mengelola: tombol kamera + status, filter preview, gaya efek tangan, panduan
 * pertama kali, mode AR (kamera sebagai latar), pengaturan tersimpan, dan
 * dwell-click (tunjuk tombol 1 detik / pinch = klik) agar bisa dipakai tanpa mouse.
 */
import { HandTracker, describeError } from './hand-tracking.js';
import { HandFX, STYLES } from './hand-fx.js';

const PREF_KEY = 'wedi-hand-prefs';
const GUIDE_KEY = 'wedi-hand-guide-v1';
const DEFAULTS = { style: 'neon', filter: 'natural', trail: true, ar: false, debug: false, sens: 55 };
const GESTURES = { point: '☝️ Menunjuk', pinch: '🤏 Pinch', open: '🖐️ Telapak', fist: '✊ Kepal', peace: '✌️ Peace' };

const store = {
  get(k, d) { try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
  set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* abaikan */ } }
};

export class HandStudio {
  /**
   * @param {object} o
   * @param {HTMLElement} o.root   elemen pembungkus (memuat bar, pengaturan, board)
   * @param {HTMLElement} o.board  area yang dipetakan ke seluruh frame kamera
   * @param {(s:object)=>void} o.onFrame  state tangan (+ overTarget)
   * @param {(on:boolean)=>void} [o.onAR]
   * @param {boolean} [o.arSupported=false]
   * @param {object} [o.fx]  opsi HandFX (cursor, skeleton, hideTrailOnPinch…)
   */
  constructor({ root, board, onFrame, onAR, arSupported = false, fx = {}, dwellMs = 1000 }) {
    this.root = root; this.board = board; this.onFrameCb = onFrame; this.onAR = onAR;
    this.arSupported = arSupported; this.dwellMs = dwellMs;
    const q = (s) => root.querySelector(s);
    this.el = {
      toggle: q('[data-hs-toggle]'), toggleLabel: q('[data-hs-toggle-label]'), status: q('[data-hs-status]'), statusText: q('[data-hs-status-text]'),
      fps: q('[data-hs-fps]'), gesture: q('[data-hs-gesture]'), help: q('[data-hs-help]'), settingsBtn: q('[data-hs-settings-btn]'), settings: q('[data-hs-settings]'),
      cam: q('[data-hs-cam]'), video: q('[data-hs-video]'), fx: q('[data-hs-fx]'), hint: q('[data-hs-hint]'), guide: q('[data-hs-guide]'), guideOk: q('[data-hs-guide-ok]')
    };
    this.prefs = Object.assign({}, DEFAULTS, store.get(PREF_KEY, {}));
    this.fx = new HandFX(this.el.fx, Object.assign({ style: this.prefs.style, trail: this.prefs.trail, debug: this.prefs.debug }, fx));
    this.tracker = null; this.busy = false; this.state = 'off';
    this._lostSince = 0; this._seen = false; this._wasPinch = false; this._cool = 0; this._dw = { el: null, t0: 0 };

    this._bindUI();
    this._applyPrefs();
    this._setState('off', 'Kamera mati');
    window.addEventListener('pagehide', () => this.stop());
  }

  get active() { return !!(this.tracker && this.tracker.active); }
  get tracking() { return this.state === 'tracking'; }
  get ar() { return this.prefs.ar && this.arSupported && this.active; }

  /* ---------------- UI binding ---------------- */
  _bindUI() {
    const e = this.el, r = this.root;
    e.toggle.addEventListener('click', () => this.toggle());
    if (e.help) e.help.addEventListener('click', () => this._showGuide(true));
    if (e.guideOk) e.guideOk.addEventListener('click', () => { store.set(GUIDE_KEY, true); e.guide.hidden = true; });

    if (e.settingsBtn) {
      e.settingsBtn.addEventListener('click', (ev) => { ev.stopPropagation(); this._toggleSettings(); });
      document.addEventListener('click', (ev) => { if (!e.settings.hidden && !e.settings.contains(ev.target) && ev.target !== e.settingsBtn) this._toggleSettings(false); });
      document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && !e.settings.hidden) this._toggleSettings(false); });
    }
    const seg = (sel, key, after) => {
      const wrap = r.querySelector(sel); if (!wrap) return;
      wrap.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { this.prefs[key] = b.dataset.value; this._save(); this._applyPrefs(); if (after) after(); }));
    };
    seg('[data-hs-style]', 'style'); seg('[data-hs-filter]', 'filter');
    const chk = (sel, key) => { const c = r.querySelector(sel); if (c) c.addEventListener('change', () => { this.prefs[key] = c.checked; this._save(); this._applyPrefs(); }); };
    chk('[data-hs-trail]', 'trail'); chk('[data-hs-debug]', 'debug'); chk('[data-hs-ar]', 'ar');
    const sens = r.querySelector('[data-hs-sens]');
    if (sens) sens.addEventListener('input', () => { this.prefs.sens = parseInt(sens.value, 10); this._save(); this._applyPrefs(); });
    const reset = r.querySelector('[data-hs-reset-guide]');
    if (reset) reset.addEventListener('click', () => { store.set(GUIDE_KEY, false); this._toggleSettings(false); this._showGuide(true); });
  }

  _toggleSettings(force) {
    const open = force === undefined ? this.el.settings.hidden : force;
    this.el.settings.hidden = !open; this.el.settingsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  _save() { store.set(PREF_KEY, this.prefs); }

  _applyPrefs() {
    const p = this.prefs, r = this.root;
    if (!STYLES[p.style]) p.style = 'neon';
    this.fx.set({ style: p.style, trail: p.trail, debug: p.debug });
    if (this.el.cam) this.el.cam.dataset.filter = p.filter;
    const mark = (sel, val) => r.querySelectorAll(sel + ' button').forEach((b) => { const on = b.dataset.value === val; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    mark('[data-hs-style]', p.style); mark('[data-hs-filter]', p.filter);
    const set = (sel, v) => { const c = r.querySelector(sel); if (c) c.checked = !!v; };
    set('[data-hs-trail]', p.trail); set('[data-hs-debug]', p.debug); set('[data-hs-ar]', p.ar);
    const sens = r.querySelector('[data-hs-sens]'); if (sens) sens.value = p.sens;
    if (this.tracker) this.tracker.setSmoothing(this._alpha());
    if (this.el.fps) this.el.fps.hidden = !(p.debug && this.active);
    this._syncAR();
  }

  _alpha() { return 0.2 + (this.prefs.sens / 100) * 0.65; } // 0.2 (halus) .. 0.85 (responsif)

  _syncAR() {
    const on = this.ar;
    if (this.el.cam) this.el.cam.classList.toggle('is-ar', on);
    this.board.dataset.ar = on ? '1' : '0';
    if (this.onAR) this.onAR(on);
  }

  _setState(name, text) {
    this.state = name; this.root.dataset.hsState = name;
    if (this.el.status) { this.el.status.dataset.state = name; this.el.statusText.textContent = text; }
    if (this.el.gesture && name !== 'tracking') this.el.gesture.hidden = true;
  }

  _showGuide(force) {
    if (!this.el.guide) return;
    if (force || !store.get(GUIDE_KEY, false)) this.el.guide.hidden = false;
  }

  /* ---------------- camera ---------------- */
  async toggle() { if (this.busy) return; if (this.active) this.stop(); else await this.start(); }

  async start() {
    this.busy = true; this.el.toggle.disabled = true;
    this._setState('loading', 'Menyiapkan kamera & model tangan…');
    this._showGuide(false);
    try {
      if (!this.tracker) this.tracker = new HandTracker({ video: this.el.video, onFrame: (s) => this._frame(s), smoothing: this._alpha() });
      await this.tracker.start();
      this.fx.start();
      this.el.cam.hidden = false;
      this.el.toggleLabel.textContent = 'Matikan kamera';
      this.el.toggle.classList.add('is-on');
      this._seen = false;
      this._setState('waiting', 'Tunjukkan tanganmu 👋');
      this._syncAR();
      if (this.el.fps) this.el.fps.hidden = !this.prefs.debug;
    } catch (err) {
      this.fx.stop();
      this._setState('error', describeError(err));
    }
    this.busy = false; this.el.toggle.disabled = false;
  }

  stop() {
    if (this.tracker) this.tracker.stop();
    this.fx.stop(); this._clearDwell();
    if (this.el.cam) { this.el.cam.hidden = true; this.el.cam.classList.remove('is-ar'); }
    if (this.el.hint) this.el.hint.hidden = true;
    if (this.el.fps) this.el.fps.hidden = true;
    this.el.toggleLabel.textContent = 'Aktifkan kamera';
    this.el.toggle.classList.remove('is-on');
    this.board.dataset.ar = '0';
    if (this.state !== 'error' || this.active) this._setState('off', 'Kamera mati');
    if (this.onAR) this.onAR(false);
    this.onFrameCb({ detected: false, landmarks: null, pointer: null, pinchPoint: null, pinch: false, gesture: 'none', overTarget: false });
  }

  /* ---------------- per frame ---------------- */
  _frame(s) {
    const now = performance.now();
    this.fx.feed(s);
    if (this.prefs.debug && this.el.fps) this.el.fps.textContent = s.fps + ' FPS';

    if (s.detected) {
      this._lostSince = 0;
      if (this.state !== 'tracking') {
        this._setState('tracking', 'Tangan terdeteksi');
        if (!this._seen) { this._seen = true; this.fx.ripple(s.pointer ? (1 - s.landmarks[9].x) * this.fx.w : 0, s.landmarks[9].y * this.fx.h, '#7ee8ff'); }
        if (this.el.hint) this.el.hint.hidden = true;
      }
      if (this.el.gesture) {
        const g = GESTURES[s.gesture];
        this.el.gesture.hidden = !g; if (g) { this.el.gesture.textContent = g; this.el.gesture.dataset.g = s.gesture; }
      }
    } else if (this.state === 'tracking') {
      this._setState('lost', 'Tangan hilang');
      this._lostSince = now;
    }
    if (!s.detected && this._lostSince && now - this._lostSince > 1300 && this.el.hint) this.el.hint.hidden = false;

    const over = s.detected ? this._dwell(s, now) : (this._clearDwell(), null);
    if (s.detected) this._wasPinch = s.pinch; else this._wasPinch = false;
    this.onFrameCb(Object.assign({}, s, { overTarget: !!over }));
  }

  /* ---------------- dwell-click ---------------- */
  _dwell(s, now) {
    const rect = this.board.getBoundingClientRect();
    const cx = rect.left + s.pointer.x * rect.width, cy = rect.top + s.pointer.y * rect.height;
    let hit = null;
    const scope = this.el.guide && !this.el.guide.hidden ? this.el.guide : this.root; // panduan menutup elemen lain
    const list = scope.querySelectorAll('[data-dwell]');
    for (let i = 0; i < list.length; i++) {
      const el = list[i];
      if (el.disabled) continue;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) continue;
      if (cx >= r.left - 6 && cx <= r.right + 6 && cy >= r.top - 6 && cy <= r.bottom + 6) { hit = el; break; }
    }
    if (!hit) { this._clearDwell(); return null; }

    if (hit !== this._dw.el) { this._clearDwell(); this._dw = { el: hit, t0: now }; hit.classList.add('is-dwelling'); }
    const ms = parseInt(hit.dataset.dwellMs || this.dwellMs, 10);
    const p = Math.min(1, (now - this._dw.t0) / ms);
    hit.style.setProperty('--dwell', p.toFixed(3));
    this.fx.dwell = p;
    const pinchEdge = s.pinch && !this._wasPinch;
    if (now >= this._cool && (p >= 1 || pinchEdge)) {
      this._cool = now + 900;
      this._clearDwell();
      if (navigator.vibrate) navigator.vibrate(15);
      this.fx.ripple(cx - rect.left, cy - rect.top, '#fff');
      hit.click();
    }
    return hit;
  }

  _clearDwell() {
    if (this._dw.el) { this._dw.el.classList.remove('is-dwelling'); this._dw.el.style.removeProperty('--dwell'); }
    this._dw = { el: null, t0: 0 };
    this.fx.dwell = 0;
  }
}
