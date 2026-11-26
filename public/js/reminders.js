(function () {
  'use strict';

  var shell = document.querySelector('[data-reminder-root]');
  if (!shell) return;

  var statusUrl = shell.getAttribute('data-reminder-status-url');
  var subscribeUrl = shell.getAttribute('data-push-subscribe-url');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var bells = document.querySelectorAll('[data-reminder-bell]');
  var dots = document.querySelectorAll('[data-reminder-dot]');
  var popover = document.querySelector('[data-reminder-popover]');
  var listEl = document.querySelector('[data-reminder-list]');
  var closeBtn = document.querySelector('[data-reminder-close]');

  var vapidPublicKey = null;
  var swReg = null;

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return Promise.resolve(null);
    return navigator.serviceWorker.register('/sw.js').then(function (reg) {
      swReg = reg;
      return reg;
    }).catch(function () { return null; });
  }

  function setUnreadCount(count) {
    dots.forEach(function (dot) {
      dot.hidden = !(count > 0);
    });
    bells.forEach(function (btn) {
      btn.classList.toggle('has-unread', count > 0);
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function renderDeliveries(data) {
    if (!listEl) return;
    var items = data.deliveries || [];
    if (!items.length) {
      var emptyMsg = data.completed_today
        ? listEl.getAttribute('data-done-text')
        : listEl.getAttribute('data-empty-text');
      listEl.innerHTML = '<div class="reminder-empty"><i class="ri-quill-pen-line" aria-hidden="true"></i><p>' + escapeHtml(emptyMsg) + '</p><a href="' + escapeHtml(data.today_url || '/journal/create') + '" class="btn btn-primary btn-sm">' + escapeHtml(listEl.getAttribute('data-cta-text')) + '</a></div>';
      return;
    }

    listEl.innerHTML = items.map(function (item) {
      var unread = !item.read_at;
      return '<div class="reminder-item ' + (unread ? 'is-unread' : '') + '">'
        + '<div class="reminder-item-head"><strong>' + escapeHtml(item.title) + '</strong>' + (unread ? '<span class="reminder-new-dot" aria-hidden="true"></span>' : '') + '</div>'
        + '<p>' + escapeHtml(item.body) + '</p>'
        + '<div class="reminder-item-foot">'
        + '<a href="' + escapeHtml(item.target_url) + '" class="btn btn-primary btn-sm" data-reminder-go="' + item.id + '">' + escapeHtml(listEl.getAttribute('data-cta-text')) + ' →</a>'
        + (unread ? '<button type="button" class="btn btn-ghost btn-sm" data-reminder-read="' + item.id + '">' + escapeHtml(listEl.getAttribute('data-read-text')) + '</button>' : '')
        + '</div></div>';
    }).join('');
  }

  function markRead(id) {
    return fetch('/reminders/' + id + '/read', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res && typeof res.unread_count === 'number') {
        setUnreadCount(res.unread_count);
      }
    }).catch(function () {});
  }

  function showNativeNotification(item) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    var opts = {
      body: item.body,
      icon: '/images/wedi-logo-mark.png',
      tag: 'wedi-daily-reminder-' + item.reminder_date,
      data: { url: item.target_url }
    };
    if (swReg && swReg.showNotification) {
      swReg.showNotification(item.title, opts).catch(function () {});
      return;
    }
    try {
      var n = new Notification(item.title, opts);
      n.onclick = function () {
        window.focus();
        window.location.href = item.target_url;
      };
    } catch (e) {}
  }

  function pollStatus() {
    if (!statusUrl) return;
    fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        vapidPublicKey = data.vapid_public_key || vapidPublicKey;
        setUnreadCount(data.unread_count || 0);
        renderDeliveries(data);
        if (data.newly_triggered) {
          showNativeNotification(data.newly_triggered);
        }
      })
      .catch(function () {});
  }

  function togglePopover(force) {
    if (!popover) return;
    var open = typeof force === 'boolean' ? force : popover.hidden;
    popover.hidden = !open;
    bells.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }

  bells.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      togglePopover();
    });
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () { togglePopover(false); });
  }

  document.addEventListener('click', function (e) {
    if (popover && !popover.hidden && !popover.contains(e.target) && !e.target.closest('[data-reminder-bell]')) {
      togglePopover(false);
    }
    var readBtn = e.target.closest('[data-reminder-read]');
    if (readBtn) {
      var id = readBtn.getAttribute('data-reminder-read');
      markRead(id).then(pollStatus);
    }
    var goBtn = e.target.closest('[data-reminder-go]');
    if (goBtn) {
      markRead(goBtn.getAttribute('data-reminder-go'));
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && popover && !popover.hidden) {
      togglePopover(false);
    }
  });

  /* ---------------- Settings Page Controls ---------------- */
  var settingsCard = document.querySelector('[data-reminder-settings]');
  if (settingsCard) {
    var toggleBtn = settingsCard.querySelector('[data-reminder-toggle]');
    var enabledInput = settingsCard.querySelector('[data-reminder-enabled-input]');
    var tzSelect = settingsCard.querySelector('[data-reminder-timezone]');
    var enablePushBtn = settingsCard.querySelector('[data-enable-push]');
    var pushStatusEl = settingsCard.querySelector('[data-push-status]');
    var iosHint = settingsCard.querySelector('[data-ios-hint]');
    var previewTitle = settingsCard.querySelector('[data-preview-title]');

    if (toggleBtn && enabledInput) {
      toggleBtn.addEventListener('click', function () {
        var next = enabledInput.value !== '1';
        enabledInput.value = next ? '1' : '0';
        toggleBtn.setAttribute('aria-checked', next ? 'true' : 'false');
      });
    }

    if (tzSelect && tzSelect.getAttribute('data-auto-tz') === '1') {
      try {
        var browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (browserTz && Array.from(tzSelect.options).some(function (o) { return o.value === browserTz; })) {
          tzSelect.value = browserTz;
        }
      } catch (e) {}
    }

    var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    var isStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (iosHint && isIos && !isStandalone) {
      iosHint.hidden = false;
    }

    function updatePushUi() {
      if (!pushStatusEl) return;
      if (!('Notification' in window)) {
        pushStatusEl.setAttribute('data-state', 'unsupported');
        pushStatusEl.textContent = pushStatusEl.getAttribute('data-unsupported-text');
        if (enablePushBtn) enablePushBtn.disabled = true;
        return;
      }
      var perm = Notification.permission;
      pushStatusEl.setAttribute('data-state', perm);
      if (perm === 'granted') {
        pushStatusEl.textContent = pushStatusEl.getAttribute('data-granted-text');
        if (enablePushBtn) {
          enablePushBtn.textContent = enablePushBtn.getAttribute('data-active-label');
        }
      } else if (perm === 'denied') {
        pushStatusEl.textContent = pushStatusEl.getAttribute('data-denied-text');
      } else {
        pushStatusEl.textContent = pushStatusEl.getAttribute('data-default-text');
      }
    }

    function subscribeToWebPush() {
      if (!swReg || !swReg.pushManager || !vapidPublicKey || !subscribeUrl) {
        return Promise.resolve(false);
      }
      return swReg.pushManager.getSubscription().then(function (existing) {
        if (existing) return existing;
        return swReg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
        });
      }).then(function (subscription) {
        if (!subscription) return false;
        var json = subscription.toJSON();
        return fetch(subscribeUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            endpoint: json.endpoint,
            keys: json.keys,
            content_encoding: 'aes128gcm'
          })
        }).then(function (r) { return r.ok; });
      }).catch(function () { return false; });
    }

    if (enablePushBtn) {
      vapidPublicKey = enablePushBtn.getAttribute('data-vapid-key') || vapidPublicKey;
      enablePushBtn.addEventListener('click', function () {
        if (!('Notification' in window)) return;
        enablePushBtn.disabled = true;
        Notification.requestPermission().then(function (permission) {
          updatePushUi();
          if (permission === 'granted') {
            return registerServiceWorker().then(subscribeToWebPush);
          }
        }).finally(function () {
          enablePushBtn.disabled = false;
          updatePushUi();
        });
      });
    }

    updatePushUi();
  }

  registerServiceWorker().then(function () {
    pollStatus();
  });
  window.setInterval(pollStatus, 60000);
})();
