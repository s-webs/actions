import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';
type RiskLevel = 'high' | 'medium' | 'low';

interface StageRow {
    id: number;
    order: number;
    title: string;
    planned_date: string | null;
    weight: number;
    review_state: string | null;
}

interface MeasureRow {
    id: number;
    number: number;
    title: string;
    direction: string | null;
    responsible: string | null;
    deadline: string | null;
    status: MeasureStatus;
    percent: number;
    risk_level: RiskLevel | null;
    needs_decision: boolean;
    stages: StageRow[];
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

interface PlanIndexProps {
    measures: Paginated<MeasureRow>;
    filters: Record<string, string | undefined>;
    directions: { id: number; number: number; name: string }[];
    responsibles: { id: number; name: string }[];
    statuses: MeasureStatus[];
    canManageStages: boolean;
}

const STATUS_LABELS: Record<MeasureStatus, string> = {
    not_started: 'Не начато',
    in_progress: 'В работе',
    at_risk: 'Есть риск',
    overdue: 'Просрочено',
    done: 'Выполнено',
};

const STATUS_VARIANT: Record<MeasureStatus, 'secondary' | 'default' | 'destructive' | 'outline'> = {
    not_started: 'outline',
    in_progress: 'secondary',
    at_risk: 'default',
    overdue: 'destructive',
    done: 'default',
};

const RISK_LABELS: Record<RiskLevel, string> = {
    high: 'Высокий',
    medium: 'Средний',
    low: 'Низкий',
};

const ALL = '__all__';

function AddStageForm({ measureId }: { measureId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        planned_date: '',
        weight: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(route('plan.stages.store', measureId), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-3 border-t pt-3">
            <div className="grid gap-1">
                <Label htmlFor={`stage-title-${measureId}`}>Название этапа</Label>
                <Input
                    id={`stage-title-${measureId}`}
                    className="w-64"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                />
                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`stage-date-${measureId}`}>Плановая дата</Label>
                <Input
                    id={`stage-date-${measureId}`}
                    type="date"
                    className="w-40"
                    value={data.planned_date}
                    onChange={(e) => setData('planned_date', e.target.value)}
                />
                {errors.planned_date && <p className="text-xs text-destructive">{errors.planned_date}</p>}
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`stage-weight-${measureId}`}>Вес, %</Label>
                <Input
                    id={`stage-weight-${measureId}`}
                    type="number"
                    min={1}
                    max={100}
                    className="w-24"
                    value={data.weight}
                    onChange={(e) => setData('weight', e.target.value)}
                />
                {errors.weight && <p className="text-xs text-destructive">{errors.weight}</p>}
            </div>
            <Button type="submit" size="sm" disabled={processing}>
                Добавить этап
            </Button>
        </form>
    );
}

function StageManager({ measureId, stages, canManage }: { measureId: number; stages: StageRow[]; canManage: boolean }) {
    if (stages.length === 0 && !canManage) {
        return <p className="text-muted-foreground text-sm">Этапы ещё не заведены координатором.</p>;
    }

    return (
        <div>
            {stages.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    Этапы ещё не заведены — без них рабочее место мероприятия не покажет форму отчёта и загрузки файлов.
                    Добавьте хотя бы один этап ниже.
                </p>
            ) : (
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="text-muted-foreground">
                            <th className="pr-4">№</th>
                            <th className="pr-4">Этап</th>
                            <th className="pr-4">Плановая дата</th>
                            <th className="pr-4">Вес</th>
                            <th className="pr-4">Состояние проверки</th>
                            {canManage && <th></th>}
                        </tr>
                    </thead>
                    <tbody>
                        {stages.map((s) => (
                            <tr key={s.id}>
                                <td className="pr-4">{s.order}</td>
                                <td className="pr-4">{s.title}</td>
                                <td className="pr-4">{s.planned_date ?? '—'}</td>
                                <td className="pr-4">{s.weight}%</td>
                                <td className="pr-4">{s.review_state ?? 'черновик'}</td>
                                {canManage && (
                                    <td>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            title="Удалить этап"
                                            onClick={() => {
                                                if (confirm(`Удалить этап «${s.title}»?`)) {
                                                    router.delete(route('plan.stages.destroy', s.id), { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {canManage && <AddStageForm measureId={measureId} />}
        </div>
    );
}

export default function PlanIndex({ measures, filters, directions, responsibles, statuses, canManageStages }: PlanIndexProps) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    function apply(next: Record<string, string | boolean | undefined>) {
        router.get(
            route('plan.index'),
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function submitSearch(e: FormEvent) {
        e.preventDefault();
        apply({ search });
    }

    return (
        <AppLayout>
            <Head title="План" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-medium">План: реестр мероприятий</h1>
                    <div className="flex gap-4">
                        <a href={route('reports.plan-xlsx')} className="text-sm text-primary underline-offset-4 hover:underline">
                            Экспорт в xlsx
                        </a>
                        <Link href={route('plan.import')} className="text-sm text-primary underline-offset-4 hover:underline">
                            Импортировать из Excel
                        </Link>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Поиск</Label>
                        <Input
                            id="search"
                            className="w-56"
                            placeholder="№ или текст мероприятия"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>Направление</Label>
                        <Select
                            value={filters.direction_id ?? ALL}
                            onValueChange={(v) => apply({ direction_id: v === ALL ? undefined : v })}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Все направления" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все направления</SelectItem>
                                {directions.map((d) => (
                                    <SelectItem key={d.id} value={String(d.id)}>
                                        {d.number}. {d.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>Ответственный</Label>
                        <Select
                            value={filters.responsible_id ?? ALL}
                            onValueChange={(v) => apply({ responsible_id: v === ALL ? undefined : v })}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Все ответственные" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все ответственные</SelectItem>
                                {responsibles.map((r) => (
                                    <SelectItem key={r.id} value={String(r.id)}>
                                        {r.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>Статус</Label>
                        <Select value={filters.status ?? ALL} onValueChange={(v) => apply({ status: v === ALL ? undefined : v })}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Все статусы" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все статусы</SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_LABELS[s]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>Уровень риска</Label>
                        <Select
                            value={filters.risk_level ?? ALL}
                            onValueChange={(v) => apply({ risk_level: v === ALL ? undefined : v })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Любой" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Любой</SelectItem>
                                {(['high', 'medium', 'low'] as RiskLevel[]).map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {RISK_LABELS[r]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <label className="flex items-center gap-2 pb-2 text-sm">
                        <Checkbox
                            checked={filters.needs_decision === '1'}
                            onCheckedChange={(v) => apply({ needs_decision: v ? '1' : undefined })}
                        />
                        Требует решения
                    </label>

                    <label className="flex items-center gap-2 pb-2 text-sm">
                        <Checkbox
                            checked={filters.has_stages_to_review === '1'}
                            onCheckedChange={(v) => apply({ has_stages_to_review: v ? '1' : undefined })}
                        />
                        Есть этапы на проверку
                    </label>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="w-8 p-3"></th>
                                <th className="p-3">№</th>
                                <th className="p-3">Направление</th>
                                <th className="p-3">Мероприятие</th>
                                <th className="p-3">Ответственный</th>
                                <th className="p-3">Срок</th>
                                <th className="p-3">Статус</th>
                                <th className="p-3">%</th>
                                <th className="p-3">Риск</th>
                            </tr>
                        </thead>
                        <tbody>
                            {measures.data.map((m) => (
                                <>
                                    <tr
                                        key={m.id}
                                        className="cursor-pointer border-t hover:bg-muted/30"
                                        onClick={() => setExpanded(expanded === m.id ? null : m.id)}
                                    >
                                        <td className="p-3">
                                            {expanded === m.id ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                                        </td>
                                        <td className="p-3">{m.number}</td>
                                        <td className="p-3">{m.direction}</td>
                                        <td className="p-3">{m.title}</td>
                                        <td className="p-3">{m.responsible}</td>
                                        <td className="p-3">{m.deadline}</td>
                                        <td className="p-3">
                                            <Badge variant={STATUS_VARIANT[m.status]}>{STATUS_LABELS[m.status]}</Badge>
                                            {m.needs_decision && (
                                                <Badge variant="destructive" className="ml-1">
                                                    решение
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3">{m.percent}%</td>
                                        <td className="p-3">{m.risk_level && <Badge variant="outline">{RISK_LABELS[m.risk_level]}</Badge>}</td>
                                    </tr>
                                    {expanded === m.id && (
                                        <tr>
                                            <td colSpan={9} className="bg-muted/20 p-4">
                                                <Collapsible open>
                                                    <CollapsibleTrigger className="hidden" />
                                                    <CollapsibleContent>
                                                        <StageManager measureId={m.id} stages={m.stages} canManage={canManageStages} />
                                                    </CollapsibleContent>
                                                </Collapsible>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-sm">
                    <p className="text-muted-foreground">
                        Показано {measures.from ?? 0}–{measures.to ?? 0} из {measures.total}
                    </p>
                    <div className="flex gap-1">
                        {measures.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded px-2 py-1 ${link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
