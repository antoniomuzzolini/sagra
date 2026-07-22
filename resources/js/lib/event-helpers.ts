export const phaseTypeLabels: Record<string, string> = {
    preparation: 'Preparazione',
    service: 'Apertura al pubblico',
    teardown: 'Smontaggio',
};

export const areaFamilyLabels: Record<string, string> = {
    food_service: 'Somministrazione',
    logistics: 'Logistica',
    entertainment: 'Intrattenimento',
    support: 'Supporto',
};

// Shift times are wall-clock times at the event ("dalle 18 alle 22"), not
// instants to convert: read date and time straight from the ISO string, never
// via `new Date(iso)`, which would shift them by the browser's timezone.

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('it-IT', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatTime(datetime: string): string {
    return datetime.slice(11, 16);
}

export function formatDayLong(value: string): string {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
}

export function formatDayShort(value: string): string {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'numeric' });
}
