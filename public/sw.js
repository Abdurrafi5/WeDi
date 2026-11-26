/* WeDi Service Worker — Offline Push Reminders */
self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  var data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'WeDi', body: event.data.text() };
    }
  }

  var title = data.title || 'A Little Reminder from WeDi';
  var options = {
    body: data.body || 'Your diary is waiting for you. Take a moment to reflect on your day.',
    icon: data.icon || '/images/wedi-logo-mark.png',
    badge: '/images/wedi-logo-mark.png',
    tag: data.tag || 'wedi-daily-reminder',
    renotify: false,
    data: {
      url: data.url || '/journal/create'
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var targetUrl = (event.notification.data && event.notification.data.url) || '/journal/create';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i];
        if ('focus' in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
    })
  );
});
