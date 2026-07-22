import { describe, expect, it } from 'vitest';
import { formatDate, formatDayLong, formatDayShort, formatTime } from './event-helpers';

describe('formatTime', () => {
    it('shows the wall-clock time, ignoring the offset', () => {
        // Same wall-clock time whatever the ISO offset — never shifted.
        expect(formatTime('2026-07-04T17:00:00+00:00')).toBe('17:00');
        expect(formatTime('2026-07-04T17:00:00+02:00')).toBe('17:00');
        expect(formatTime('2026-07-04T23:30:00Z')).toBe('23:30');
    });
});

describe('formatDate', () => {
    it('reads the calendar date from the string, not the instant', () => {
        expect(formatDate('2026-07-04')).toBe('4 lug 2026');
        // A late-evening time must not roll the date to the next/previous day.
        expect(formatDate('2026-07-04T23:30:00+02:00')).toBe('4 lug 2026');
    });

    it('handles empty values', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate(undefined)).toBe('—');
    });
});

describe('formatDayLong / formatDayShort', () => {
    it('name the day from the calendar date', () => {
        expect(formatDayLong('2026-07-04')).toContain('4 luglio');
        expect(formatDayShort('2026-07-04T23:30:00+02:00')).toContain('4');
    });
});
