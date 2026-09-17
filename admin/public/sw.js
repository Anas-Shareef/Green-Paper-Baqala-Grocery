/* Baqqala Admin Service Worker for Web Push & Realtime Order Notifications */
self.addEventListener('push', (event) => {
  let data = { title: '🛒 Baqqala New Order Received', body: 'A new grocery order needs action.' };
  try {
    if (event.data) {
      data = event.data.json();
    }
  } catch (e) {
    if (event.data) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body || data.message || 'New Order Received',
    icon: '/icon.png',
    badge: '/icon.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/orders',
    },
  };

  event.waitUntil(
    self.registration.showNotification(data.title || '🛒 Baqqala New Order Received', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/orders';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes('/admin') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
