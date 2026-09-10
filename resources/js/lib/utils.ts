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
