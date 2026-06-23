// Deterministic colors for people and areas, so the same name/area always
// reads with the same hue across the app. oklch keeps lightness/chroma
// constant while only the hue varies — legible on light and dark alike.

const AVATAR_HUES = [250, 150, 45, 300, 190, 70, 20];

function hueFrom(name: string, palette: number[]): number {
    let sum = 0;
    for (const ch of name) sum += ch.charCodeAt(0);
    return palette[sum % palette.length];
}

export function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export function avatarColor(name: string): string {
    return `oklch(0.62 0.07 ${hueFrom(name, AVATAR_HUES)})`;
}

// Areas are free-form (no fixed catalog), so the color comes from the area's
// family when set — keeping each family visually coherent — and falls back to
// a per-name hue for areas without one.
const FAMILY_HUE: Record<string, number> = {
    food_service: 60,
    logistics: 250,
    entertainment: 300,
    support: 190,
};
const FALLBACK_HUES = [150, 45, 20, 220, 330, 100, 280];

export function areaColors(family: string | null | undefined, name: string): { solid: string; soft: string } {
    const hue = family && family in FAMILY_HUE ? FAMILY_HUE[family] : hueFrom(name, FALLBACK_HUES);
    return { solid: `oklch(0.6 0.1 ${hue})`, soft: `oklch(0.95 0.03 ${hue})` };
}
