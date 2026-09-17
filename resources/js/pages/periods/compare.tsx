import { Head, router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ComparisonRow {
    number: number;
    title: string;
    from_percent: number | null;
    to_percent: number | null;
    from_status: string | null;
    to_status: string | null;
    changed: boolean;
}

interface CompareProps {
    availablePeriods: { id: number; month: string }[];
    fromId: number | null;
    toId: number | null;
    comparison: ComparisonRow[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Закрытие периода', href: '/periods' },
    { title: 'Сравнение периодов', href: '/periods/compare' },
];

/**
 * Сравнение двух закрытых периодов — task-017,
 * [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]].
 */
export default function PeriodsCompare({ availablePeriods, fromId, toId, comparison }: CompareProps) {
    function apply(next: { from?: number; to?: number }) {
        router.get(route('periods.compare'), { from: next.from ?? fromId, to: next.to ?? toId }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Сравнение периодов" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Сравнение периодов</h1>

                {availablePeriods.length < 2 ? (
                    <p className="text-muted-foreground text-sm">Нужно минимум два закрытых периода — пока закрыт не более одного.</p>
                ) : (
                    <>
                        <div className="flex items-end gap-4">
                            <div className="grid gap-2">
                                <span className="text-sm">С периода</span>
                                <Select value={fromId ? String(fromId) : undefined} onValueChange={(v) => apply({ from: Number(v) })}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availablePeriods.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.month}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm">По период</span>
                                <Select value={toId ? String(toId) : undefined} onValueChange={(v) => apply({ to: Number(v) })}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availablePeriods.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.month}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="p-2">№</th>
                                    <th className="p-2">Мероприятие</th>
                                    <th className="p-2">Было</th>
                                    <th className="p-2">Стало</th>
                                </tr>
                            </thead>
                            <tbody>
                                {comparison.map((row) => (
                                    <tr key={row.number} className={`border-t ${row.changed ? 'bg-amber-50' : ''}`}>
                                        <td className="p-2">{row.number}</td>
                                        <td className="p-2">{row.title}</td>
                                        <td className="p-2">
                                            {row.from_status ?? '—'} · {row.from_percent ?? '—'}%
                                        </td>
                                        <td className="p-2">
                                            {row.to_status ?? '—'} · {row.to_percent ?? '—'}%{' '}
                                            {row.changed && <Badge variant="secondary">изменилось</Badge>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
