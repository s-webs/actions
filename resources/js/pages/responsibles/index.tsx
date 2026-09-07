import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ResponsibleRow {
    name: string;
    count: number;
    status_breakdown: Record<string, number>;
    avg_percent: number;
    risks: number;
    overdue: number;
    nearest_deadline: string | null;
    overloaded: boolean;
}

interface ResponsiblesProps {
    rows: ResponsibleRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Свод по ответственным', href: '/responsibles' }];

/**
 * Свод по ответственным — task-013,
 * [[Функциональные требования#4.5 Модуль «Свод по ответственным»]].
 */
export default function ResponsiblesIndex({ rows }: ResponsiblesProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Свод по ответственным" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Свод по ответственным</h1>
                <p className="text-muted-foreground text-sm">
                    Подсветка «перегружен» — временная эвристика (число мероприятий выше среднего), методика уточняется у заказчика.
                </p>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">Ответственный</th>
                                <th className="p-3">Мероприятий</th>
                                <th className="p-3">Средний %</th>
                                <th className="p-3">Высокий риск</th>
                                <th className="p-3">Просрочено</th>
                                <th className="p-3">Ближайший срок</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.name} className="border-t">
                                    <td className="p-3">
                                        {row.name}
                                        {row.overloaded && (
                                            <Badge variant="destructive" className="ml-2">
                                                перегружен
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="p-3">{row.count}</td>
                                    <td className="p-3">{row.avg_percent}%</td>
                                    <td className="p-3">{row.risks}</td>
                                    <td className="p-3">{row.overdue}</td>
                                    <td className="p-3">{row.nearest_deadline ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
