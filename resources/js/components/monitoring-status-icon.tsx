import { useId } from 'react';

export function MonitoringStatusIcon({ symbol }: { symbol: string }) {
    const rawId = useId();
    const gradientId = `ok-${rawId.replace(/:/g, '')}`;

    if (symbol === '✓') {
        return (
            <svg viewBox="0 0 64 64" className="mx-auto h-6 w-6" aria-hidden>
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#7CF08A" />
                        <stop offset="55%" stopColor="#22C55E" />
                        <stop offset="100%" stopColor="#16A34A" />
                    </linearGradient>
                </defs>
                <rect x="2" y="2" width="60" height="60" rx="14" fill={`url(#${gradientId})`} />
                <rect x="8" y="6" width="48" height="16" rx="8" fill="#fff" opacity="0.22" />
                <path
                    d="M16 33.5 27.5 45 48.5 21"
                    fill="none"
                    stroke="#fff"
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        );
    }

    if (symbol === '!') {
        return (
            <svg viewBox="0 0 64 64" className="mx-auto h-6 w-6" aria-hidden>
                <circle cx="32" cy="32" r="30" fill="#F04438" />
                <path d="M22 22 42 42M42 22 22 42" stroke="#F3F4F6" strokeWidth="8" strokeLinecap="round" />
            </svg>
        );
    }

    if (symbol === '⚠') {
        return (
            <svg viewBox="0 0 64 64" className="mx-auto h-6 w-6" aria-hidden>
                <path
                    d="M32 8.5 58.5 54.5H5.5Z"
                    fill="#FACC15"
                    stroke="#F59E0B"
                    strokeWidth="5"
                    strokeLinejoin="round"
                />
                <rect x="29" y="24" width="6" height="16" rx="3" fill="#3F4A5A" />
                <circle cx="32" cy="46.5" r="3.2" fill="#3F4A5A" />
            </svg>
        );
    }

    return <span>{symbol}</span>;
}
