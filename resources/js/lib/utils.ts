import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Short label for a file/link: title, otherwise the last path segment instead of the full URL. */
export function evidenceLabel(url: string, title?: string | null): string {
    if (title) {
        return title;
    }

    try {
        const path = new URL(url, 'http://localhost').pathname;
        const name = path.split('/').filter(Boolean).pop();

        if (name) {
            return decodeURIComponent(name);
        }
    } catch {
        // keep the raw url
    }

    return url;
}

const MONTHS_GENITIVE = [
    'января',
    'февраля',
    'марта',
    'апреля',
    'мая',
    'июня',
    'июля',
    'августа',
    'сентября',
    'октября',
    'ноября',
    'декабря',
] as const;

/** Calendar date for UI: «15 сентября 2026». Parses YYYY-MM-DD by parts to avoid UTC shift. */
export function formatDisplayDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const monthName = MONTHS_GENITIVE[month - 1];

    if (!monthName || day < 1 || day > 31) {
        return value;
    }

    return `${day} ${monthName} ${year}`;
}
