/* WeDi AI — chat, journal assistant & weekly reflection (Phase 2.2) + UX polish */
(function () {
  'use strict';

  var csrf = function () {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
  };

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) {
          var msg = data.message;
          if (!msg && data.errors) { msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' '); }
          if (r.status === 419) msg = 'Sesi habis. Muat ulang halaman lalu coba lagi.';
          if (r.status === 429 && !msg) msg = 'Terlalu banyak permintaan. Tunggu sebentar.';
          var err = new Error(msg || 'Terjadi kesalahan. Coba lagi.');
          err.status = r.status;
          throw err;
        }
        return data;
      });
    }, function () {
      throw new Error('Tidak bisa terhubung ke server. Periksa koneksi internetmu.');
    });
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  /* ---------- rich text (aman: hanya DOM node, tanpa innerHTML) ---------- */
  function inline(parent, text) {
    var re = /(\*\*[^*]+\*\*|`[^`]+`|\*[^*\s][^*]*\*)/g, last = 0, m;
    while ((m = re.exec(text))) {
      if (m.index > last) parent.appendChild(document.createTextNode(text.slice(last, m.index)));
      var t = m[0];
      if (t.indexOf('**') === 0) parent.appendChild(el('strong', null, t.slice(2, -2)));
      else if (t[0] === '`') parent.appendChild(el('code', null, t.slice(1, -1)));
      else parent.appendChild(el('em', null, t.slice(1, -1)));
      last = m.index + t.length;
    }
    if (last < text.length) parent.appendChild(document.createTextNode(text.slice(last)));
  }

  function renderRich(container, text) {
    container.textContent = '';
    var lines = String(text).replace(/\r/g, '').split('\n'), list = null, listType = null, para = null;
    function flush() { list = null; listType = null; para = null; }
    lines.forEach(function (raw) {
      var line = raw.replace(/\s+$/, '');
      var ul = /^\s*[-*•]\s+(.*)$/.exec(line), ol = /^\s*(\d+)[.)]\s+(.*)$/.exec(line), h = /^#{1,3}\s+(.*)$/.exec(line);
      if (!line.trim()) { flush(); return; }
      if (ul || ol) {
        var type = ul ? 'ul' : 'ol';
        if (!list || listType !== type) { list = el(type, 'ai-list'); container.appendChild(list); listType = type; }
        var li = el('li'); inline(li, ul ? ul[1] : ol[2]); list.appendChild(li); para = null; return;
      }
      list = null; listType = null;
      if (h) { var hh = el('p', 'ai-h'); inline(hh, h[1]); container.appendChild(hh); para = null; return; }
      if (!para) { para = el('p'); container.appendChild(para); } else para.appendChild(document.createElement('br'));
      inline(para, line);
    });
  }

  /* konfirmasi form destruktif */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  function copyText(text, btn) {
    var done = function () { var old = btn.innerHTML; btn.classList.add('ok'); btn.textContent = 'Tersalin ✓'; setTimeout(function () { btn.classList.remove('ok'); btn.innerHTML = old; }, 1500); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, function () {});
    else { var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (x) { /* noop */ } ta.remove(); }
  }

  /* ================= Chat ================= */
  document.addEventListener('DOMContentLoaded', function () {
    var hist = document.querySelector('.ai-history');
    if (hist && window.innerWidth < 900) hist.removeAttribute('open');

    var root = document.querySelector('[data-ai-chat]');
    if (!root) return;
    var list = root.querySelector('[data-ai-messages]');
    var form = root.querySelector('[data-ai-form]');
    var input = root.querySelector('[data-ai-input]');
    var sendBtn = root.querySelector('[data-ai-send]');
    var remainingEl = root.querySelector('[data-ai-remaining]');
    var countEl = root.querySelector('[data-ai-count]');
    var scrollBtn = root.querySelector('[data-ai-scroll]');
    var url = root.getAttribute('data-url');
    var busy = false, skipType = false;

    function nearBottom() { return list.scrollHeight - list.scrollTop - list.clientHeight < 120; }
    function scrollDown(smooth) { list.scrollTo({ top: list.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); }
    scrollDown();
    list.addEventListener('scroll', function () { scrollBtn.hidden = nearBottom(); });
    scrollBtn.addEventListener('click', function () { scrollDown(true); });

    function nowTime() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

    function decorate(wrap, text) { // tombol salin untuk balasan AI
      var bubble = wrap.querySelector('.ai-bubble');
      var btn = el('button', 'ai-copy');
      btn.type = 'button'; btn.title = 'Salin'; btn.setAttribute('aria-label', 'Salin balasan');
      btn.innerHTML = '<i class="ri-file-copy-line" aria-hidden="true"></i>';
      btn.addEventListener('click', function () { copyText(text, btn); });
      bubble.appendChild(btn);
    }

    // render ulang riwayat dari server dengan format rapi
    list.querySelectorAll('.ai-msg.assistant').forEach(function (wrap) {
      var b = wrap.querySelector('.ai-bubble'), text = b.textContent;
      renderRich(b, text); decorate(wrap, text);
    });

    function addMsg(role, text, rich) {
      var empty = list.querySelector('[data-ai-empty]');
      if (empty) empty.remove();
      var wrap = el('div', 'ai-msg ' + role);
      wrap.setAttribute('data-time', nowTime());
      var b = el('div', 'ai-bubble');
      if (rich) renderRich(b, text); else b.textContent = text;
      wrap.appendChild(b);
      list.appendChild(wrap);
      scrollDown(true);
      return wrap;
    }

    function typing() {
      var wrap = el('div', 'ai-msg assistant typing');
      var b = el('div', 'ai-bubble');
      b.innerHTML = '<span class="ai-dots"><i></i><i></i><i></i></span>';
      wrap.appendChild(b);
      list.appendChild(wrap);
      scrollDown(true);
      return wrap;
    }

    // efek mengetik; klik di mana saja pada bubble untuk melewati
    function typewriter(text) {
      var wrap = addMsg('assistant', '', false), b = wrap.querySelector('.ai-bubble');
      var i = 0, total = text.length, step = Math.max(2, Math.ceil(total / 90)), stick = true;
      skipType = false;
      function done() { renderRich(b, text); decorate(wrap, text); if (stick) scrollDown(true); busyOff(); }
      function tick() {
        if (skipType || i >= total) return done();
        i = Math.min(total, i + step);
        b.textContent = text.slice(0, i);
        if (stick && nearBottom()) scrollDown(false); else stick = false;
        requestAnimationFrame(tick);
      }
      b.addEventListener('click', function () { skipType = true; });
      tick();
    }

    function busyOff() { busy = false; sendBtn.disabled = false; input.focus(); }

    function autosize() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 160) + 'px';
      var n = input.value.length;
      countEl.hidden = n < 3200;
      countEl.textContent = n + '/4000';
      countEl.classList.toggle('over', n > 3900);
    }

    function send(text) {
      if (busy || !text) return;
      busy = true;
      sendBtn.disabled = true;
      var userNode = addMsg('user', text, false);
      var t = typing();
      post(url, { message: text }).then(function (data) {
        t.remove();
        if (remainingEl && data.remaining != null) remainingEl.textContent = data.remaining;
        var titleEl = document.querySelector('[data-conv-title="' + root.getAttribute('data-conv-id') + '"]');
        if (titleEl && data.title) titleEl.textContent = data.title;
        typewriter(data.reply);
      }).catch(function (err) {
        t.remove();
        userNode.classList.add('failed');
        var box = el('div', 'ai-msg assistant error');
        var bubble = el('div', 'ai-bubble', err.message);
        var retry = el('button', 'btn btn-secondary btn-sm', 'Coba lagi');
        retry.type = 'button';
        retry.addEventListener('click', function () { box.remove(); userNode.remove(); busyOff(); send(text); });
        bubble.appendChild(document.createElement('br'));
        bubble.appendChild(retry);
        box.appendChild(bubble);
        list.appendChild(box);
        scrollDown(true);
        busyOff();
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;
      input.value = '';
      autosize();
      send(text);
    });
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
    });
    root.querySelectorAll('[data-ai-suggest]').forEach(function (b) {
      b.addEventListener('click', function () { input.value = b.getAttribute('data-ai-suggest'); autosize(); input.focus(); });
    });
    input.focus({ preventScroll: true });
  });

  /* ================= Journal assistant ================= */
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-ai-journal]');
    if (!root) return;
    var url = root.getAttribute('data-url');
    var preview = root.querySelector('[data-ai-preview]');
    var loading = root.querySelector('[data-ai-loading]');
    var errorBox = root.querySelector('[data-ai-error]');
    var wrap = root.querySelector('[data-ai-result-wrap]');
    var result = root.querySelector('[data-ai-result]');
    var textarea = document.getElementById('content');
    var buttons = root.querySelectorAll('[data-ai-action]');
    var raw = '', busy = false;

    function setBusy(state) {
      busy = state;
      buttons.forEach(function (b) { b.disabled = state; });
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (busy) return;
        var moodEl = document.querySelector('input[name="mood"]:checked');
        var titleEl = document.getElementById('title');
        buttons.forEach(function (b) { b.classList.toggle('active', b === btn); });
        preview.hidden = false; wrap.hidden = true; errorBox.hidden = true; loading.hidden = false;
        setBusy(true);
        post(url, {
          action: btn.getAttribute('data-ai-action'),
          content: textarea ? textarea.value : '',
          title: titleEl ? titleEl.value : '',
          mood: moodEl ? moodEl.value : null
        }).then(function (data) {
          raw = data.result;
          renderRich(result, raw);
          wrap.hidden = false;
          preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }).catch(function (err) {
          errorBox.textContent = err.message;
          errorBox.hidden = false;
        }).then(function () { loading.hidden = true; setBusy(false); });
      });
    });

    root.querySelector('[data-ai-insert]').addEventListener('click', function (e) {
      if (!textarea) return;
      var pos = textarea.selectionStart != null ? textarea.selectionStart : textarea.value.length;
      var before = textarea.value.slice(0, pos), after = textarea.value.slice(pos);
      var sep = before && !/\n\n$/.test(before) ? (before.endsWith('\n') ? '\n' : '\n\n') : '';
      textarea.value = before + sep + raw + (after ? '\n\n' : '') + after;
      textarea.focus();
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      var b = e.currentTarget, old = b.innerHTML; b.textContent = 'Disisipkan ✓'; setTimeout(function () { b.innerHTML = old; }, 1400);
    });
    root.querySelector('[data-ai-copy]').addEventListener('click', function (e) { copyText(raw, e.currentTarget); });
    root.querySelector('[data-ai-dismiss]').addEventListener('click', function () { preview.hidden = true; buttons.forEach(function (b) { b.classList.remove('active'); }); });
  });

  /* ================= Weekly reflection ================= */
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-ai-reflection]');
    if (!root) return;
    var url = root.getAttribute('data-url');
    var saveBase = root.getAttribute('data-save-url');
    var picks = root.querySelectorAll('input[name="journal_ids[]"]');
    var consent = root.querySelector('[data-ai-consent]');
    var targets = root.querySelector('[data-ai-targets]');
    var genBtn = root.querySelector('[data-ai-generate]');
    var statusEl = root.querySelector('[data-ai-status]');
    var errorBox = root.querySelector('[data-ai-error]');
    var loading = root.querySelector('[data-ai-loading]');
    var form = root.querySelector('[data-ai-result]');
    var countEl = root.querySelector('[data-ai-count]');
    var steps = root.querySelectorAll('[data-step]');
    if (!genBtn) return;
    var timer = 0;

    function selected() { return Array.prototype.filter.call(picks, function (p) { return p.checked; }).map(function (p) { return parseInt(p.value, 10); }); }
    function setStep(n) { steps.forEach(function (s) { var i = parseInt(s.getAttribute('data-step'), 10); s.classList.toggle('active', i === n); s.classList.toggle('done', i < n); }); }
    function refresh() {
      var n = selected().length;
      countEl.textContent = n + ' / ' + picks.length;
      genBtn.disabled = !(consent.checked && n > 0);
      if (form.hidden) setStep(n === 0 ? 1 : (consent.checked ? 3 : 2));
    }
    picks.forEach(function (p) { p.addEventListener('change', refresh); });
    consent.addEventListener('change', refresh);
    root.querySelector('[data-ai-select-all]').addEventListener('click', function () {
      var all = selected().length !== picks.length;
      picks.forEach(function (p) { p.checked = all; });
      refresh();
    });

    var phrases = ['Membaca jurnalmu…', 'Mencari pola mood…', 'Menyusun sorotan minggu ini…', 'Menulis refleksi…'];
    genBtn.addEventListener('click', function () {
      errorBox.hidden = true; form.hidden = true; loading.hidden = false;
      genBtn.disabled = true;
      var i = 0; statusEl.textContent = phrases[0];
      timer = setInterval(function () { i = (i + 1) % phrases.length; statusEl.textContent = phrases[i]; }, 2200);
      post(url, { journal_ids: selected(), consent: consent.checked, include_targets: !!(targets && targets.checked) }).then(function (data) {
        Object.keys(data.sections).forEach(function (k) {
          var f = form.elements[k]; if (f) f.value = data.sections[k];
        });
        form.action = saveBase + '/' + data.generation_id + '/save';
        form.hidden = false; setStep(4);
        statusEl.textContent = (data.remaining != null ? data.remaining + ' sisa permintaan AI hari ini' : '');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function (err) {
        errorBox.textContent = err.message; errorBox.hidden = false; statusEl.textContent = '';
      }).then(function () { clearInterval(timer); loading.hidden = true; refresh(); });
    });
    refresh();
  });
})();
