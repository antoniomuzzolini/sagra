// Web push service worker (D10). Kept intentionally tiny: no offline
// caching yet, just notifications.
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Turni', {
            body: payload.body || '',
            icon: payload.icon || '/icon-192.png',
            badge: '/icon-192.png',
            data: { url: (payload.data && payload.data.url) || '/me' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/me';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (client.url.includes('/me') && 'focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        }),
    );
});
