import { usePage } from '@inertiajs/react';

import { type SharedData } from '@/types';

type LabelGroup = 'measure_status' | 'review_state' | 'risk_level';

export function useLabels() {
    const { labels } = usePage<SharedData>().props;

    function label(group: LabelGroup, value: string | null | undefined, fallback = '—'): string {
        if (!value) {
            return fallback;
        }

        return labels?.[group]?.[value] ?? value;
    }

    return {
        labels,
        measureStatus: (value: string | null | undefined, fallback?: string) => label('measure_status', value, fallback),
        reviewState: (value: string | null | undefined, fallback?: string) =>
            label('review_state', value, fallback ?? labels?.review_state?.draft ?? 'Черновик'),
        riskLevel: (value: string | null | undefined, fallback?: string) => label('risk_level', value, fallback),
    };
}
