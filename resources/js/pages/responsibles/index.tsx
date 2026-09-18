import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

interface ResponsibleRow {
    name: string;
    occupant: string | null;
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
    const [search, setSearch] = useState('');

    const filteredRows = useMemo(() => {
        const needle = search.trim().toLowerCase();

        if (needle === '') {
            return rows;
        }

        return rows.filter(
            (row) =>
                row.name.toLowerCase().includes(needle) || (row.occupant ?? '').toLowerCase().includes(needle),
        );
    }, [rows, search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Свод по ответственным" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Свод по ответственным</h1>

                <div className="grid max-w-sm gap-2">
                    <Label htmlFor="responsible-search">Поиск по должности или ФИО</Label>
                    <Input
                        id="responsible-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Начните вводить имя"
                    />
                </div>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">Должность</th>
                                <th className="p-3">Сотрудник</th>
                                <th className="p-3">Мероприятий</th>
                                <th className="p-3">Средний %</th>
                                <th className="p-3">Высокий риск</th>
                                <th className="p-3">Просрочено</th>
                                <th className="p-3">Ближайший срок</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredRows.map((row) => (
                                <tr key={row.name} className="border-t">
                                    <td className="p-3">
                                        {row.name}
                                        {row.overloaded && (
                                            <Badge variant="destructive" className="ml-2">
                                                перегружен
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="p-3">{row.occupant ?? '—'}</td>
                                    <td className="p-3">{row.count}</td>
                                    <td className="p-3">{row.avg_percent}%</td>
                                    <td className="p-3">{row.risks}</td>
                                    <td className="p-3">{row.overdue}</td>
                                    <td className="p-3">{formatDisplayDate(row.nearest_deadline)}</td>
                                </tr>
                            ))}
                            {filteredRows.length === 0 && (
                                <tr className="border-t">
                                    <td className="p-3 text-muted-foreground" colSpan={7}>
                                        Ничего не найдено
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
