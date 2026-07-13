import { describe, expect, it } from 'vitest';
import { areaColors, avatarColor, initials } from './colors';

describe('initials', () => {
    it('takes the first two words', () => {
        expect(initials('Anna Ricci')).toBe('AR');
        expect(initials('Marta Bo')).toBe('MB');
    });

    it('handles a single name and extra whitespace', () => {
        expect(initials('Madonna')).toBe('M');
        expect(initials('  gino   verdi  ')).toBe('GV');
    });

    it('ignores words past the second', () => {
        expect(initials('Anna Maria Ricci')).toBe('AM');
    });
});

describe('avatarColor', () => {
    it('is deterministic for a given name', () => {
        expect(avatarColor('Anna Ricci')).toBe(avatarColor('Anna Ricci'));
    });

    it('returns an oklch color', () => {
        expect(avatarColor('Anna Ricci')).toMatch(/^oklch\(/);
    });
});

describe('areaColors', () => {
    it('keys off the family when known', () => {
        // food_service is hue 60 in the family map.
        expect(areaColors('food_service', 'Cucina').solid).toContain('60');
        expect(areaColors('food_service', 'Bar').solid).toContain('60');
    });

    it('falls back to a per-name hue without a family', () => {
        const a = areaColors(null, 'Pulizie');
        const b = areaColors(null, 'Pulizie');
        expect(a.solid).toBe(b.solid);
        expect(a.solid).toMatch(/^oklch\(/);
        expect(a.soft).toMatch(/^oklch\(/);
    });

    it('treats an unknown family like no family', () => {
        expect(areaColors('made_up', 'Cucina').solid).toBe(areaColors(null, 'Cucina').solid);
    });
});
