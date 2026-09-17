import { Head } from '@inertiajs/react';
import { useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { useLabels } from '@/lib/labels';
import { type BreadcrumbItem } from '@/types';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';

interface Cell {
    month: string;
    live: boolean;
    symbol: string;
    status: MeasureStatus | null;
    percent: number | null;
    risk_level?: string | null;
}

interface Row {
    id: number;
    number: number;
    title: string;
    cells: Cell[];
}

interface MonitoringProps {
    months: string[];
    currentMonth: string;
    rows: Row[];
}

const SYMBOL_COLOR: Record<string, string> = {
    '✓': 'text-green-600',
    '↗': 'text-blue-600',
    '⚠': 'text-amber-600',
    '!': 'text-destructive',
    '—': 'text-muted-foreground',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Помесячный мониторинг', href: '/monitoring' }];

/**
 * Помесячный мониторинг — task-011,
 * [[Функциональные требования#4.3 Модуль «Помесячный мониторинг»]].
 */
export default function MonitoringIndex({ months, currentMonth, rows }: MonitoringProps) {
    const { measureStatus } = useLabels();
    const [selected, setSelected] = useState<{ row: Row; cell: Cell } | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Помесячный мониторинг" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Помесячный мониторинг</h1>

                <div className="max-h-[70vh] overflow-auto rounded-lg border bg-card">
                    <table className="border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="sticky top-0 left-0 z-20 min-w-64 border-b bg-card p-2 text-left">Мероприятие</th>
                                {months.map((m) => (
                                    <th key={m} className="sticky top-0 z-10 min-w-14 border-b bg-card p-2 text-center">
                                        {m}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="sticky left-0 z-10 max-w-64 truncate bg-card p-2" title={row.title}>
                                        №{row.number}. {row.title}
                                    </td>
                                    {row.cells.map((cell) => (
                                        <td
                                            key={cell.month}
                                            onClick={() => setSelected({ row, cell })}
                                            className={`cursor-pointer p-2 text-center hover:bg-muted/50 ${SYMBOL_COLOR[cell.symbol] ?? ''} ${cell.live ? 'italic' : ''}`}
                                        >
                                            {cell.symbol}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {selected && (
                    <div className="rounded-lg border bg-card p-4 text-sm">
                        <p className="font-medium">
                            №{selected.row.number}. {selected.row.title} — {selected.cell.month}
                            {selected.cell.live && ' (незакрытый период)'}
                        </p>
                        {selected.cell.status ? (
                            <p className="text-muted-foreground">
                                Статус: {measureStatus(selected.cell.status)} · % исполнения: {selected.cell.percent}
                            </p>
                        ) : (
                            <p className="text-muted-foreground">Данных за этот месяц нет.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
