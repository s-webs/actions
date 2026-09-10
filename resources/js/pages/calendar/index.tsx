import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useLabels } from '@/lib/labels';
import { type BreadcrumbItem } from '@/types';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';

interface MonthEntry {
    month: string;
    focus_text: string;
    review_body: string;
    measures: { id: number; number: number; title: string; status: MeasureStatus; percent: number }[];
}

interface CalendarProps {
    months: MonthEntry[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Календарь контроля', href: '/calendar' }];

/**
 * Календарь контроля — task-012,
 * [[Функциональные требования#4.4 Модуль «Календарь контроля»]].
 */
export default function CalendarIndex({ months }: CalendarProps) {
    const { measureStatus } = useLabels();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Календарь контроля" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Календарь контроля</h1>

                {months.map((m) => (
                    <Card key={m.month}>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">{m.month}</CardTitle>
                                <Badge variant="outline">{m.review_body}</Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">{m.focus_text}</p>
                        </CardHeader>
                        <CardContent>
                            {m.measures.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Нет мероприятий со сроком в этом месяце.</p>
                            ) : (
                                <ul className="flex flex-col gap-1 text-sm">
                                    {m.measures.map((measure) => (
                                        <li key={measure.id} className="flex justify-between border-t py-1 first:border-t-0">
                                            <span>
                                                №{measure.number}. {measure.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {measureStatus(measure.status)} · {measure.percent}%
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
