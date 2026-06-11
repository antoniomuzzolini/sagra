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

export function formatDate(date: string | null | undefined): string {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('it-IT', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatTime(datetime: string): string {
    return new Date(datetime).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
}
