import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { MonitoringStatusIcon } from '@/components/monitoring-status-icon';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { useLabels } from '@/lib/labels';
import { formatDisplayDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';

interface Cell {
    month: string;
    live: boolean;
    symbol: string;
    status: MeasureStatus | null;
    percent: number | null;
    risk_level: string | null;
    risk_text: string | null;
    needs_decision: boolean;
}

interface Row {
    id: number;
    number: number;
    title: string;
    responsible: string | null;
    deadline: string | null;
    cells: Cell[];
}

interface MonitoringProps {
    months: string[];
    currentMonth: string;
    rows: Row[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Помесячный мониторинг', href: '/monitoring' }];

/**
 * Помесячный мониторинг — task-011,
 * [[Функциональные требования#4.3 Модуль «Помесячный мониторинг»]].
 */
export default function MonitoringIndex({ months, currentMonth, rows }: MonitoringProps) {
    const { measureStatus, riskLevel } = useLabels();
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
                                    {row.cells.map((cell) => {
                                        const isSelected = selected?.row.id === row.id && selected.cell.month === cell.month;

                                        return (
                                            <td
                                                key={cell.month}
                                                onClick={() => setSelected({ row, cell })}
                                                className={`cursor-pointer p-2 text-center hover:bg-muted/50 ${cell.live ? 'italic' : ''} ${cell.symbol === '↗' ? 'text-blue-600' : cell.symbol === '—' ? 'text-muted-foreground' : ''} ${isSelected ? 'bg-muted' : ''}`}
                                            >
                                                <MonitoringStatusIcon symbol={cell.symbol} />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {selected && (
                    <div className="flex flex-col gap-3 rounded-lg border bg-card p-4 text-sm">
                        <div>
                            <p className="font-medium">
                                №{selected.row.number}. {selected.row.title} — {selected.cell.month}
                                {selected.cell.live && ' (незакрытый период)'}
                            </p>
                            <p className="text-muted-foreground">
                                Должность: {selected.row.responsible ?? '—'} · Срок: {formatDisplayDate(selected.row.deadline)}
                            </p>
                        </div>

                        {selected.cell.status ? (
                            <>
                                <p className="flex flex-wrap items-center gap-2 text-muted-foreground">
                                    <span>
                                        Статус: {measureStatus(selected.cell.status)} · % исполнения: {selected.cell.percent}
                                    </span>
                                    {selected.cell.risk_level && (
                                        <Badge variant={selected.cell.risk_level === 'high' ? 'destructive' : 'outline'}>
                                            Риск: {riskLevel(selected.cell.risk_level)}
                                        </Badge>
                                    )}
                                </p>
                                {(selected.cell.risk_text || selected.cell.needs_decision) && (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900">
                                        {selected.cell.risk_text && (
                                            <p>
                                                <strong>Риск / проблема:</strong> {selected.cell.risk_text}
                                            </p>
                                        )}
                                        {selected.cell.needs_decision && <p className="mt-1 font-medium">Требуется решение руководства</p>}
                                    </div>
                                )}
                                {!selected.cell.risk_level && !selected.cell.risk_text && !selected.cell.needs_decision && (
                                    <p className="text-muted-foreground">Рисков по этому месяцу нет.</p>
                                )}
                            </>
                        ) : (
                            <p className="text-muted-foreground">Данных за этот месяц нет.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
