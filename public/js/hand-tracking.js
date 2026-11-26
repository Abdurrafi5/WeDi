/* WeDi Hand Tracking Engine (Phase 2.3)
 * MediaPipe Hand Landmarker berjalan 100% di browser: video webcam tidak pernah
 * dikirim atau disimpan di server. Kamera hanya menyala setelah pengguna menekan
 * tombol dan memberi izin.
 */
const MP_VERSION = '0.10.14';
const MP_BUNDLE = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MP_VERSION}/+esm`;
const MP_WASM = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MP_VERSION}/wasm`;
const MP_MODEL = 'https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task';

export const HAND_CONNECTIONS = [
  [0, 1], [1, 2], [2, 3], [3, 4], [0, 5], [5, 6], [6, 7], [7, 8], [5, 9], [9, 10], [10, 11], [11, 12],
  [9, 13], [13, 14], [14, 15], [15, 16], [13, 17], [17, 18], [18, 19], [19, 20], [0, 17]
];

export class HandError extends Error {
  constructor(code, message) { super(message); this.code = code; }
}

/** Mengelola akses webcam (izin, start, stop). */
export class CameraManager {
  constructor(video) {
    this.video = video;
    this.stream = null;
  }

  get active() { return !!this.stream; }

  async start() {
    if (this.stream) return;
    if (!window.isSecureContext) {
      throw new HandError('insecure', 'Kamera hanya bisa dipakai lewat HTTPS atau localhost.');
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new HandError('unsupported', 'Browser ini tidak mendukung akses kamera.');
    }
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { ideal: 30 } }
      });
    } catch (e) {
      if (e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')) throw new HandError('denied', 'Izin kamera ditolak. Aktifkan izin kamera di browser lalu coba lagi.');
      if (e && (e.name === 'NotFoundError' || e.name === 'OverconstrainedError')) throw new HandError('no_camera', 'Kamera tidak ditemukan di perangkat ini.');
      if (e && e.name === 'NotReadableError') throw new HandError('busy', 'Kamera sedang dipakai aplikasi lain.');
      throw new HandError('unknown', 'Kamera tidak bisa diaktifkan.');
    }
    this.video.srcObject = this.stream;
    this.video.muted = true;
    this.video.playsInline = true;
    await this.video.play();
  }

  stop() {
    if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
    this.stream = null;
    this.video.srcObject = null;
  }
}

const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

/** Jari dianggap terbuka bila ujungnya lebih jauh dari pergelangan daripada sendi PIP. */
const extended = (lm, tip, pip) => dist(lm[tip], lm[0]) > dist(lm[pip], lm[0]) * 1.12;

/** Klasifikasi gestur sederhana: point | pinch | open | fist | peace | other */
export function classifyGesture(lm, pinch) {
  if (pinch) return 'pinch';
  const idx = extended(lm, 8, 6), mid = extended(lm, 12, 10), ring = extended(lm, 16, 14), pink = extended(lm, 20, 18);
  const n = idx + mid + ring + pink;
  if (n === 4) return 'open';
  if (n === 0) return 'fist';
  if (idx && !mid && !ring && !pink) return 'point';
  if (idx && mid && !ring && !pink) return 'peace';
  return 'other';
}

/**
 * Mendeteksi 21 landmark tangan dan menghasilkan:
 *  - pointer: ujung jari telunjuk (x,y 0..1, sudah dicerminkan & dihaluskan)
 *  - pinch: true saat ibu jari + telunjuk menyatu (dengan hysteresis)
 *  - pinchPoint: titik tengah ibu jari-telunjuk (stabil untuk menggambar)
 */
export class HandTracker {
  /**
   * @param {object} o
   * @param {HTMLVideoElement} o.video
   * @param {(state: object) => void} o.onFrame
   * @param {number} [o.maxFps=30]
   * @param {number} [o.smoothing=0.55]  0..1, makin besar makin responsif
   */
  constructor({ video, onFrame, maxFps = 30, smoothing = 0.55, numHands = 1 }) {
    this.video = video;
    this.numHands = numHands;
    this.onFrame = onFrame;
    this.minInterval = 1000 / maxFps;
    this.alpha = smoothing;
    this.camera = new CameraManager(video);
    this.landmarker = null;
    this.running = false;
    this._raf = 0;
    this._lastVideoTime = -1;
    this._lastRun = 0;
    this._pointer = null;
    this._pinchPoint = null;
    this._pinch = false;
    this._fpsFrames = 0;
    this._fpsStart = performance.now();
    this.fps = 0;
    this._loop = this._loop.bind(this);
    window.addEventListener('pagehide', () => this.stop());
  }

  async load() {
    if (this.landmarker) return;
    let vision;
    try {
      vision = await import(MP_BUNDLE);
    } catch (e) {
      throw new HandError('load', 'Gagal memuat library hand tracking. Periksa koneksi internet.');
    }
    const { FilesetResolver, HandLandmarker } = vision;
    const fileset = await FilesetResolver.forVisionTasks(MP_WASM);
    const make = (delegate) => HandLandmarker.createFromOptions(fileset, {
      baseOptions: { modelAssetPath: MP_MODEL, delegate },
      runningMode: 'VIDEO',
      numHands: this.numHands,
      minHandDetectionConfidence: 0.55,
      minHandPresenceConfidence: 0.55,
      minTrackingConfidence: 0.5
    });
    try {
      this.landmarker = await make('GPU');
    } catch (e) {
      try { this.landmarker = await make('CPU'); }
      catch (e2) { throw new HandError('load', 'Model tangan tidak bisa dimuat.'); }
    }
  }

  /** Start Camera: minta izin, muat model, mulai deteksi. */
  async start() {
    await this.camera.start();
    try {
      await this.load();
    } catch (e) {
      this.camera.stop();
      throw e;
    }
    this.running = true;
    this._lastVideoTime = -1;
    this._fpsStart = performance.now();
    this._fpsFrames = 0;
    this._raf = requestAnimationFrame(this._loop);
  }

  /** Stop Camera: hentikan deteksi & matikan webcam. */
  stop() {
    this.running = false;
    cancelAnimationFrame(this._raf);
    this.camera.stop();
    this._pointer = null;
    this._pinchPoint = null;
    this._pinch = false;
  }

  get active() { return this.running; }

  /** 0.15 (sangat halus) .. 0.9 (sangat responsif) */
  setSmoothing(a) { this.alpha = clamp(a, 0.15, 0.9); }

  _loop(now) {
    if (!this.running) return;
    this._raf = requestAnimationFrame(this._loop);
    const v = this.video;
    if (v.readyState < 2 || v.currentTime === this._lastVideoTime) return;
    if (now - this._lastRun < this.minInterval) return;
    this._lastRun = now;
    this._lastVideoTime = v.currentTime;

    let res;
    try { res = this.landmarker.detectForVideo(v, now); } catch (e) { return; }

    this._fpsFrames++;
    if (now - this._fpsStart >= 1000) {
      this.fps = Math.round((this._fpsFrames * 1000) / (now - this._fpsStart));
      this._fpsFrames = 0;
      this._fpsStart = now;
    }

    const lm = res && res.landmarks && res.landmarks[0];
    if (!lm) {
      this._pointer = null;
      this._pinchPoint = null;
      this._pinch = false;
      this.onFrame({ detected: false, hands: [], landmarks: null, pointer: null, pinchPoint: null, pinch: false, pinchStrength: 0, gesture: 'none', fps: this.fps });
      return;
    }

    const tip = lm[8];
    const raw = { x: 1 - tip.x, y: tip.y }; // cermin, agar gerak tangan terasa natural
    const mid = { x: 1 - (lm[4].x + lm[8].x) / 2, y: (lm[4].y + lm[8].y) / 2 };
    this._pointer = this._pointer ? { x: this._pointer.x + (raw.x - this._pointer.x) * this.alpha, y: this._pointer.y + (raw.y - this._pointer.y) * this.alpha } : raw;
    this._pinchPoint = this._pinchPoint ? { x: this._pinchPoint.x + (mid.x - this._pinchPoint.x) * this.alpha, y: this._pinchPoint.y + (mid.y - this._pinchPoint.y) * this.alpha } : mid;

    // Rasio jarak ibu jari-telunjuk terhadap ukuran telapak => tahan terhadap jarak ke kamera.
    const palm = Math.max(dist(lm[0], lm[9]), 1e-4);
    const ratio = dist(lm[4], lm[8]) / palm;
    if (!this._pinch && ratio < 0.3) this._pinch = true;
    else if (this._pinch && ratio > 0.45) this._pinch = false;

    this.onFrame({
      detected: true,
      hands: res.landmarks, // semua tangan terdeteksi (untuk mode dua tangan)
      landmarks: lm,
      pointer: { ...this._pointer },
      pinchPoint: { ...this._pinchPoint },
      pinch: this._pinch,
      pinchStrength: clamp(1 - (ratio - 0.2) / 0.35, 0, 1),
      gesture: classifyGesture(lm, this._pinch),
      fps: this.fps
    });
  }
}

/** Gambar kerangka tangan pada canvas overlay (canvas & video dicerminkan lewat CSS). */
export function drawHandSkeleton(canvas, landmarks) {
  const ctx = canvas.getContext('2d');
  const w = canvas.width, h = canvas.height;
  ctx.clearRect(0, 0, w, h);
  if (!landmarks) return;
  ctx.lineWidth = 2;
  ctx.strokeStyle = 'rgba(255,255,255,.85)';
  ctx.beginPath();
  HAND_CONNECTIONS.forEach(([a, b]) => {
    ctx.moveTo(landmarks[a].x * w, landmarks[a].y * h);
    ctx.lineTo(landmarks[b].x * w, landmarks[b].y * h);
  });
  ctx.stroke();
  landmarks.forEach((p, i) => {
    ctx.beginPath();
    ctx.fillStyle = i === 8 ? '#ff5d8f' : i === 4 ? '#ffd166' : '#3e4b8e';
    ctx.arc(p.x * w, p.y * h, i === 8 || i === 4 ? 5 : 3, 0, Math.PI * 2);
    ctx.fill();
  });
}

/** Pesan ramah untuk error kamera/model. */
export function describeError(e) {
  return e && e.message ? e.message : 'Kamera tidak bisa diaktifkan.';
}
