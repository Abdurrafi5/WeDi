(function () {
  'use strict';

  /* ---------------- Theme ---------------- */
  function applyTheme(t) {
    if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    else document.documentElement.setAttribute('data-theme', 'light');
  }
  var savedTheme = null;
  try { savedTheme = localStorage.getItem('wedi-theme'); } catch (e) {}
  applyTheme(savedTheme || 'light');

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-theme-set]');
    if (!btn) return;
    var val = btn.getAttribute('data-theme-set');
    applyTheme(val);
    try { localStorage.setItem('wedi-theme', val); } catch (err) {}
    document.querySelectorAll('[data-theme-set]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-theme-set') === val);
    });
  });
  document.addEventListener('DOMContentLoaded', function () {
    var current = document.documentElement.getAttribute('data-theme') || 'light';
    document.querySelectorAll('[data-theme-set]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-theme-set') === current);
    });
  });

  /* ---------------- Mobile drawer ---------------- */
  document.addEventListener('DOMContentLoaded', function () {
    var drawer = document.querySelector('.mobile-drawer');
    var overlay = document.querySelector('.drawer-overlay');
    var openBtn = document.querySelector('[data-drawer-open]');
    if (!drawer) return;

    function setOpen(state) {
      drawer.classList.toggle('open', state);
      if (overlay) overlay.classList.toggle('open', state);
      document.body.classList.toggle('drawer-open', state);
      if (openBtn) openBtn.setAttribute('aria-expanded', state ? 'true' : 'false');
    }

    if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
    document.querySelectorAll('[data-drawer-close]').forEach(function (b) {
      b.addEventListener('click', function () { setOpen(false); });
    });
    drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 900) setOpen(false); });
  });

  /* ---------------- Flash auto-dismiss ---------------- */
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flash-item').forEach(function (el) {
      setTimeout(function () {
        el.style.transition = 'opacity .3s ease';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 300);
      }, 4500);
    });
  });

  /* ---------------- Simple modal ---------------- */
  document.addEventListener('click', function (e) {
    var openTrigger = e.target.closest('[data-modal-open]');
    if (openTrigger) {
      var m = document.getElementById(openTrigger.getAttribute('data-modal-open'));
      if (m) { m.classList.add('open'); var f = m.querySelector('input,textarea,select'); if (f) f.focus(); }
    }
    var closeTrigger = e.target.closest('[data-modal-close]');
    if (closeTrigger) {
      var mc = closeTrigger.closest('.modal-overlay');
      if (mc) mc.classList.remove('open');
    }
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('open');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    }
  });

  /* ==================================================================
     GAMES
     ================================================================== */
  function csrfToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
  }

  function saveScore(gameId, score, duration) {
    return fetch('/games/score', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
      body: JSON.stringify({ game_id: gameId, score: score, duration_seconds: duration })
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  /* ---- Memory Match ---- */
  (function () {
    var root = document.getElementById('game-memory-match');
    if (!root) return;
    var SYMBOLS = ['A', 'B', 'C', 'D', 'E', 'F'];
    var grid = root.querySelector('.memory-grid');
    var timerEl = root.querySelector('[data-timer]');
    var pairsEl = root.querySelector('[data-pairs]');
    var startBtn = root.querySelector('[data-start]');
    var restartBtn = root.querySelector('[data-restart]');
    var resultStage = root.querySelector('.game-result');
    var playStage = root.querySelector('.game-play');
    var scoreBig = resultStage ? resultStage.querySelector('.score-big') : null;
    var timeLeft, timerHandle, flipped, matchedCount, lock, cards;

    function buildCards() {
      var deck = SYMBOLS.concat(SYMBOLS);
      for (var i = deck.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = deck[i]; deck[i] = deck[j]; deck[j] = t;
      }
      grid.innerHTML = '';
      cards = deck.map(function (sym, idx) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'memory-card';
        b.dataset.symbol = sym;
        b.dataset.index = idx;
        b.textContent = sym;
        b.addEventListener('click', function () { flip(b); });
        grid.appendChild(b);
        return b;
      });
    }

    function flip(card) {
      if (lock || card.classList.contains('flipped') || card.classList.contains('matched') || timeLeft <= 0) return;
      card.classList.add('flipped');
      flipped.push(card);
      if (flipped.length === 2) {
        lock = true;
        var a = flipped[0], b = flipped[1];
        if (a.dataset.symbol === b.dataset.symbol) {
          a.classList.add('matched'); b.classList.add('matched');
          matchedCount++;
          pairsEl.textContent = matchedCount + ' / ' + SYMBOLS.length;
          flipped = []; lock = false;
          if (matchedCount === SYMBOLS.length) finish(true);
        } else {
          setTimeout(function () {
            a.classList.remove('flipped'); b.classList.remove('flipped');
            flipped = []; lock = false;
          }, 700);
        }
      }
    }

    function tick() {
      timeLeft--;
      timerEl.textContent = timeLeft + 's';
      if (timeLeft <= 0) finish(false);
    }

    function finish(won) {
      clearInterval(timerHandle);
      var score = matchedCount * 100 + Math.max(0, timeLeft) * 5;
      playStage.classList.remove('active');
      resultStage.classList.add('active');
      scoreBig.textContent = score;
      saveScore('memory-match', score, 60 - timeLeft).then(function (data) {
        if (data && data.best_score !== undefined) {
          var bestEl = root.querySelector('[data-best-score]');
          if (bestEl) bestEl.textContent = data.best_score;
        }
      });
    }

    function start() {
      matchedCount = 0; flipped = []; lock = false; timeLeft = 60;
      pairsEl.textContent = '0 / ' + SYMBOLS.length;
      timerEl.textContent = timeLeft + 's';
      buildCards();
      var intro = root.querySelector('.game-intro');
      if (intro) intro.classList.remove('active');
      resultStage.classList.remove('active');
      playStage.classList.add('active');
      clearInterval(timerHandle);
      timerHandle = setInterval(tick, 1000);
    }

    if (startBtn) startBtn.addEventListener('click', start);
    if (restartBtn) restartBtn.addEventListener('click', start);
  })();

  /* ---- Zen Tap ---- */
  (function () {
    var root = document.getElementById('game-zen-tap');
    if (!root) return;
    var field = root.querySelector('.zen-field');
    var timerEl = root.querySelector('[data-timer]');
    var tapsEl = root.querySelector('[data-taps]');
    var startBtn = root.querySelector('[data-start]');
    var restartBtn = root.querySelector('[data-restart]');
    var resultStage = root.querySelector('.game-result');
    var playStage = root.querySelector('.game-play');
    var scoreBig = resultStage ? resultStage.querySelector('.score-big') : null;
    var taps, timeLeft, timerHandle, target;

    function placeTarget() {
      if (!target) {
        target = document.createElement('button');
        target.type = 'button';
        target.className = 'zen-target';
        target.textContent = '+';
        target.addEventListener('click', function () {
          taps++; tapsEl.textContent = taps;
          placeTarget();
        });
        field.appendChild(target);
      }
      var maxX = field.clientWidth - 56;
      var maxY = field.clientHeight - 56;
      target.style.left = Math.max(0, Math.random() * maxX) + 'px';
      target.style.top = Math.max(0, Math.random() * maxY) + 'px';
    }

    function tick() {
      timeLeft--;
      timerEl.textContent = timeLeft + 's';
      if (timeLeft <= 0) finish();
    }

    function finish() {
      clearInterval(timerHandle);
      if (target) { target.remove(); target = null; }
      playStage.classList.remove('active');
      resultStage.classList.add('active');
      scoreBig.textContent = taps;
      saveScore('zen-tap', taps, 20).then(function (data) {
        if (data && data.best_score !== undefined) {
          var bestEl = root.querySelector('[data-best-score]');
          if (bestEl) bestEl.textContent = data.best_score;
        }
      });
    }

    function start() {
      taps = 0; timeLeft = 20;
      tapsEl.textContent = '0';
      timerEl.textContent = timeLeft + 's';
      var intro = root.querySelector('.game-intro');
      if (intro) intro.classList.remove('active');
      resultStage.classList.remove('active');
      playStage.classList.add('active');
      placeTarget();
      clearInterval(timerHandle);
      timerHandle = setInterval(tick, 1000);
    }

    if (startBtn) startBtn.addEventListener('click', start);
    if (restartBtn) restartBtn.addEventListener('click', start);
  })();

  /* ---- Word Bloom ---- */
  (function () {
    var root = document.getElementById('game-word-bloom');
    if (!root) return;
    var HINTS = {
      id: {
        DIARY: 'Tempat menulis cerita harianmu', TARGET: 'Sesuatu yang ingin kamu capai', MOOD: 'Perasaanmu hari ini',
        RECAP: 'Ringkasan minggu ini', SPARK: 'Percikan semangat kecil', WEEKLY: 'Terjadi setiap tujuh hari', JOURNAL: 'Catatan pribadi harianmu'
      },
      en: {
        DIARY: 'Where you write your daily stories', TARGET: 'Something you want to achieve', MOOD: 'How you feel today',
        RECAP: 'A summary of this week', SPARK: 'A small burst of energy', WEEKLY: 'Happens every seven days', JOURNAL: 'Your personal daily notes'
      }
    };
    var LANG = (document.documentElement.lang || 'id').slice(0, 2) === 'en' ? 'en' : 'id';
    var WORDS = Object.keys(HINTS[LANG]).map(function (w) { return { word: w, hint: HINTS[LANG][w] }; });
    var letterWrap = root.querySelector('.bloom-letters');
    var answerWrap = root.querySelector('.bloom-answer');
    var hintEl = root.querySelector('[data-hint]');
    var roundEl = root.querySelector('[data-round]');
    var scoreEl = root.querySelector('[data-score]');
    var submitBtn = root.querySelector('[data-submit]');
    var clearBtn = root.querySelector('[data-clear]');
    var startBtn = root.querySelector('[data-start]');
    var restartBtn = root.querySelector('[data-restart]');
    var resultStage = root.querySelector('.game-result');
    var playStage = root.querySelector('.game-play');
    var scoreBig = resultStage ? resultStage.querySelector('.score-big') : null;
    var round, score, current, answer, letterButtons;

    function shuffle(arr) {
      arr = arr.slice();
      for (var i = arr.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
      }
      return arr;
    }

    function renderAnswer() {
      answerWrap.textContent = answer.join('') || '\u00A0';
    }

    function loadRound() {
      current = WORDS[round];
      answer = [];
      hintEl.textContent = current.hint;
      roundEl.textContent = (round + 1) + ' / 5';
      var letters = shuffle(current.word.split(''));
      letterWrap.innerHTML = '';
      letterButtons = letters.map(function (ch, idx) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'bloom-letter';
        b.textContent = ch;
        b.addEventListener('click', function () {
          if (b.disabled) return;
          b.disabled = true;
          answer.push(ch);
          renderAnswer();
        });
        letterWrap.appendChild(b);
        return b;
      });
      renderAnswer();
    }

    function clearAnswer() {
      answer = [];
      letterButtons.forEach(function (b) { b.disabled = false; });
      renderAnswer();
    }

    function submit() {
      if (answer.join('') === current.word) {
        score += 20;
      }
      scoreEl.textContent = score;
      round++;
      if (round >= 5) { finish(); } else { loadRound(); }
    }

    function finish() {
      playStage.classList.remove('active');
      resultStage.classList.add('active');
      scoreBig.textContent = score;
      saveScore('word-bloom', score, null).then(function (data) {
        if (data && data.best_score !== undefined) {
          var bestEl = root.querySelector('[data-best-score]');
          if (bestEl) bestEl.textContent = data.best_score;
        }
      });
    }

    function start() {
      round = 0; score = 0;
      scoreEl.textContent = '0';
      var intro = root.querySelector('.game-intro');
      if (intro) intro.classList.remove('active');
      resultStage.classList.remove('active');
      playStage.classList.add('active');
      loadRound();
    }

    if (submitBtn) submitBtn.addEventListener('click', submit);
    if (clearBtn) clearBtn.addEventListener('click', clearAnswer);
    if (startBtn) startBtn.addEventListener('click', start);
    if (restartBtn) restartBtn.addEventListener('click', start);
  })();
})();

/* ---------------- Final interaction polish ---------------- */
(function () {
  function syncModalLock() {
    document.body.classList.toggle('modal-open', !!document.querySelector('.modal-overlay.open'));
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-modal-open]');
    if (open) { window.setTimeout(syncModalLock, 0); }
    var close = e.target.closest('[data-modal-close]');
    if (close || (e.target.classList && e.target.classList.contains('modal-overlay'))) { window.setTimeout(syncModalLock, 0); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { window.setTimeout(syncModalLock, 0); }
  });
  document.addEventListener('change', function (e) {
    if (!e.target.matches('#avatar')) return;
    var file = e.target.files && e.target.files[0];
    var holder = document.querySelector('.profile-upload-avatar');
    var preview = holder && holder.querySelector('img');
    if (!file || !holder) return;
    var url = URL.createObjectURL(file);
    if (!preview) {
      preview = document.createElement('img');
      var initials = holder.querySelector('span');
      if (initials) initials.remove();
      holder.insertBefore(preview, holder.firstChild);
    }
    preview.src = url;
    preview.alt = file.name;
  });
})();

/* ---------------- History day reader ---------------- */
(function () {
  document.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-day-tab]');
    if (!tab) return;
    var key = tab.getAttribute('data-day-tab');
    var panels = document.querySelectorAll('[data-day-panel]');
    if (!panels.length) return;
    e.preventDefault();
    document.querySelectorAll('[data-day-tab]').forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('is-selected', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) { p.hidden = p.getAttribute('data-day-panel') !== key; });
    try { history.replaceState(null, '', '?day=' + key + '#journal-reader'); } catch (err) {}
  });
})();

/* ---------------- Scroll reveal ---------------- */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var nodes = document.querySelectorAll('[data-reveal]');
    if (!nodes.length) return;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || !('IntersectionObserver' in window)) {
      nodes.forEach(function (el) { el.classList.add('is-revealed'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
    nodes.forEach(function (el) { io.observe(el); });
  });
})();
