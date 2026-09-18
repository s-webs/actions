import { describe, expect, it } from 'vitest';

import { formatDisplayDate } from './utils';

describe('formatDisplayDate', () => {
    it('formats a September date in genitive', () => {
        expect(formatDisplayDate('2026-09-15')).toBe('15 сентября 2026');
    });

    it('drops the leading zero on the day', () => {
        expect(formatDisplayDate('2026-01-05')).toBe('5 января 2026');
    });

    it('returns a dash for empty values', () => {
        expect(formatDisplayDate(null)).toBe('—');
        expect(formatDisplayDate(undefined)).toBe('—');
        expect(formatDisplayDate('')).toBe('—');
    });
});
