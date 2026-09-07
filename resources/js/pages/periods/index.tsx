import { Head, router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface UnreviewedRow {
    measure_number: number;
    measure_title: string;
    stage_title: string;
    review_state: 'submitted' | 'rework';
}

interface PeriodsProps {
    period: { id: number; month: string; state: 'open' | 'closed' };
    measureCount: number;
    unreviewedCount: number;
    unreviewed: UnreviewedRow[];
    recentSnapshots: string[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Закрытие периода', href: '/periods' }];

/**
 * Закрытие отчётного периода — task-016,
 * [[Заполнение и утверждение#Закрытие периода]].
 */
export default function PeriodsIndex({ period, measureCount, unreviewedCount, unreviewed, recentSnapshots }: PeriodsProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Закрытие периода" />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-medium">Закрытие отчётного периода</h1>

                <div className="rounded-lg border p-4">
                    <p>
                        Текущий период: <strong>{period.month}</strong> —{' '}
                        <Badge variant={period.state === 'open' ? 'outline' : 'secondary'}>{period.state === 'open' ? 'открыт' : 'закрыт'}</Badge>
                    </p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Закрытие создаст неизменяемый срез утверждённых % и статусов по всем {measureCount} мероприятиям на этот месяц.
                    </p>

                    {unreviewedCount > 0 && (
                        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                            <p className="font-medium">
                                Не проверено проректором: {unreviewedCount} этап(ов). При закрытии они перейдут в следующий период —
                                эскалация на рабочую группу.
                            </p>
                            <ul className="mt-2 list-disc pl-5">
                                {unreviewed.map((u, i) => (
                                    <li key={i}>
                                        №{u.measure_number}. {u.measure_title} — {u.stage_title} ({u.review_state === 'submitted' ? 'подан' : 'на доработке'})
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <Button
                        className="mt-4"
                        disabled={period.state === 'closed'}
                        onClick={() => confirm(`Закрыть период ${period.month}? Действие необратимо.`) && router.post(route('periods.close'))}
                    >
                        Закрыть период
                    </Button>
                </div>

                {recentSnapshots.length > 0 && (
                    <div>
                        <h2 className="font-medium">Архив закрытых периодов</h2>
                        <ul className="text-muted-foreground mt-2 flex flex-wrap gap-2 text-sm">
                            {recentSnapshots.map((m) => (
                                <li key={m}>
                                    <Badge variant="outline">{m}</Badge>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
