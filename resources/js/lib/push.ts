import { router } from '@inertiajs/vue3';

export function pushSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export function pushDenied(): boolean {
    return 'Notification' in window && Notification.permission === 'denied';
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

/**
 * One tap, nothing to type (D10): ask permission, subscribe, store
 * the subscription server side. Resolves to false when unsupported
 * or denied — callers fall back to suggesting an email.
 */
export async function enablePush(vapidPublicKey: string): Promise<boolean> {
    if (!pushSupported() || !vapidPublicKey) {
        return false;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return false;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const json = subscription.toJSON();

    return new Promise((resolve) => {
        router.post(
            route('volunteer.push.store'),
            { endpoint: subscription.endpoint, keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth } },
            { preserveScroll: true, onSuccess: () => resolve(true), onError: () => resolve(false) },
        );
    });
}
