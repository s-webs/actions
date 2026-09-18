import { Head, Link } from '@inertiajs/react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useLabels } from '@/lib/labels';
import { formatDisplayDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';

const STATUS_COLORS: Record<MeasureStatus, string> = {
    not_started: '#a1a1aa',
    in_progress: '#3b82f6',
    at_risk: '#eab308',
    overdue: '#ef4444',
    done: '#22c55e',
};

interface MeasureSummary {
    id: number;
    number: number;
    title: string;
    responsible: string | null;
    deadline: string | null;
    percent: number;
    risk_level: 'high' | 'medium' | 'low' | null;
    status: MeasureStatus;
    problem: string | null;
}

interface DashboardProps {
    kpis: {
        total: number;
        done: number;
        in_progress: number;
        at_risk: number;
        overdue: number;
        high_risk: number;
        avg_percent: number;
        stages_to_review: number;
    };
    statusBreakdown: { status: MeasureStatus; count: number }[];
    directionSummary: { name: string; count: number; avg_percent: number }[];
    upcomingDeadlines: MeasureSummary[];
    needsDecision: MeasureSummary[];
    topRisks: MeasureSummary[];
    generatedAt: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Кабинет проректора', href: '/dashboard' }];

function MeasureRow({ m }: { m: MeasureSummary }) {
    const { measureStatus } = useLabels();

    return (
        <tr className="border-t">
            <td className="p-2">{m.number}</td>
            <td className="p-2">{m.title}</td>
            <td className="p-2">{m.responsible}</td>
            <td className="p-2">{formatDisplayDate(m.deadline)}</td>
            <td className="p-2">{m.percent}%</td>
            <td className="p-2">
                <Badge variant="outline">{measureStatus(m.status)}</Badge>
            </td>
            <td className="p-2 text-muted-foreground">{m.problem ?? '—'}</td>
        </tr>
    );
}

/**
 * «Кабинет проректора» — task-009,
 * [[Функциональные требования#4.2 Модуль «Кабинет проректора» — дашборд]].
 */
export default function Dashboard({ kpis, statusBreakdown, directionSummary, upcomingDeadlines, needsDecision, topRisks, generatedAt }: DashboardProps) {
    const { measureStatus, riskLevel } = useLabels();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Кабинет проректора" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex justify-end">
                    <Button asChild size="sm">
                        <a href={route('reports.dashboard-pdf')} target="_blank" rel="noreferrer">
                            Экспорт в PDF
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle>{kpis.total}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">Всего мероприятий</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle>{kpis.done}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">Выполнено</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle>{kpis.in_progress}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">В работе по графику</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-amber-600">{kpis.at_risk}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">Есть риск</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-destructive">{kpis.overdue}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">Просрочено</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-destructive">{kpis.high_risk}</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0 text-sm text-muted-foreground">Высокий риск</CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Статусы</CardTitle>
                        </CardHeader>
                        <CardContent className="h-56">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={statusBreakdown} dataKey="count" nameKey="status" innerRadius={50} outerRadius={80}>
                                        {statusBreakdown.map((s) => (
                                            <Cell key={s.status} fill={STATUS_COLORS[s.status]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value, _name, entry) => [value, measureStatus(entry.payload.status as MeasureStatus)]} />
                                </PieChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Средний % исполнения</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">{kpis.avg_percent}%</p>
                            <div className="mt-3 h-3 w-full rounded-full bg-muted">
                                <div className="h-3 rounded-full bg-primary" style={{ width: `${kpis.avg_percent}%` }} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Этапы на проверку</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">{kpis.stages_to_review}</p>
                            <Button asChild size="sm" className="mt-2">
                                <Link href={route('approval.index')}>Перейти к проверке →</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Свод по направлениям</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {directionSummary.map((d) => (
                            <div key={d.name} className="flex items-center gap-3 text-sm">
                                <span className="w-64 shrink-0 truncate">{d.name}</span>
                                <div className="h-3 flex-1 rounded-full bg-muted">
                                    <div
                                        className="h-3 rounded-full bg-primary"
                                        style={{ width: `${d.avg_percent}%` }}
                                    />
                                </div>
                                <span className="w-16 text-right text-muted-foreground">
                                    {d.avg_percent}% ({d.count})
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Ближайшие сроки (30 дней)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {upcomingDeadlines.length === 0 && <p className="text-sm text-muted-foreground">Нет мероприятий с близким сроком.</p>}
                            <ul className="flex flex-col gap-2 text-sm">
                                {upcomingDeadlines.map((m) => (
                                    <li key={m.id} className="flex justify-between border-t pt-2 first:border-t-0 first:pt-0">
                                        <span>
                                            №{m.number}. {m.title}
                                        </span>
                                        <span className="text-muted-foreground">{formatDisplayDate(m.deadline)}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">ТОП-5 рисков месяца</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-col gap-2 text-sm">
                                {topRisks.map((m) => (
                                    <li key={m.id} className="border-t pt-2 first:border-t-0 first:pt-0">
                                        <div className="flex justify-between">
                                            <span>
                                                №{m.number}. {m.title}
                                            </span>
                                            {m.risk_level && <Badge variant="outline">{riskLevel(m.risk_level)}</Badge>}
                                        </div>
                                        <p className="text-muted-foreground">
                                            {m.responsible} · срок {formatDisplayDate(m.deadline)} · {m.percent}%
                                            {m.problem ? ` · ${m.problem}` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Требует решения проректора ({needsDecision.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground">
                                <tr>
                                    <th className="p-2">№</th>
                                    <th className="p-2">Мероприятие</th>
                                    <th className="p-2">Должность</th>
                                    <th className="p-2">Срок</th>
                                    <th className="p-2">%</th>
                                    <th className="p-2">Статус</th>
                                    <th className="p-2">Проблема</th>
                                </tr>
                            </thead>
                            <tbody>
                                {needsDecision.map((m) => (
                                    <MeasureRow key={m.id} m={m} />
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <p className="text-center text-xs text-muted-foreground">Данные актуальны на {generatedAt}</p>
            </div>
        </AppLayout>
    );
}
