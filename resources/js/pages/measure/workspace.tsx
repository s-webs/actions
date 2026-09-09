import { Head, router, useForm } from '@inertiajs/react';
import { Check, Lock, LoaderCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type MeasureStatus = 'not_started' | 'in_progress' | 'at_risk' | 'overdue' | 'done';
type ReviewState = 'draft' | 'submitted' | 'approved' | 'rejected' | 'rework';
type StageStatus = 'current' | 'completed' | 'locked';

interface StageEvidence {
    id: number;
    title: string | null;
    type: 'file' | 'link';
    path_or_url: string;
}

interface StageUpdate {
    id: number;
    done_text: string | null;
    review_state: ReviewState;
    review_comment: string | null;
    approved_percent: number | null;
}

interface StageListItem {
    id: number;
    order: number;
    title: string;
    planned_date: string | null;
    weight: number;
    status: StageStatus | null;
}

interface CurrentStage {
    id: number;
    order: number;
    title: string;
    planned_date: string | null;
    weight: number;
    update: StageUpdate | null;
    evidences: StageEvidence[];
}

interface HistoryEntry {
    id: number;
    stage_title: string;
    review_state: ReviewState;
    approved_percent: number | null;
    review_comment: string | null;
    approved_at: string | null;
}

interface ChangeLogEntry {
    id: number;
    event: 'created' | 'updated' | 'deleted';
    model: string;
    user: string;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    created_at: string;
}

interface WorkspaceProps {
    measure: {
        number: number;
        title: string;
        direction: string | null;
        deadline: string | null;
        status: MeasureStatus;
        percent: number;
    };
    period: { id: number; month: string };
    measureState: { status: MeasureStatus; risk_text: string | null; needs_decision: boolean; locked: boolean };
    stagesConfirmed: boolean;
    stageList: StageListItem[];
    currentStage: CurrentStage | null;
    history: HistoryEntry[];
    changeLog: ChangeLogEntry[];
}

const EVENT_LABELS: Record<ChangeLogEntry['event'], string> = {
    created: 'Создано',
    updated: 'Изменено',
    deleted: 'Удалено',
};

const FIELD_LABELS: Record<string, string> = {
    title: 'Название',
    done_text: 'Что сделано',
    next_step: 'Следующий шаг',
    next_step_date: 'Срок следующего шага',
    review_state: 'Состояние проверки',
    approved_percent: '% готовности',
    review_comment: 'Комментарий проверки',
    status: 'Статус',
    risk_text: 'Риск/проблема',
    needs_decision: 'Требуется решение',
    weight: 'Вес',
    planned_date: 'Плановая дата',
    proctor_comment: 'Комментарий проректора',
    stages_confirmed_at: 'Список этапов зафиксирован',
};

function ChangeLogCard({ entry }: { entry: ChangeLogEntry }) {
    const fields = Array.from(new Set([...Object.keys(entry.old_values ?? {}), ...Object.keys(entry.new_values ?? {})]));

    return (
        <div className="rounded-lg border p-3 text-sm">
            <div className="flex items-center justify-between">
                <span className="font-medium">
                    {entry.model} · {EVENT_LABELS[entry.event]}
                </span>
                <span className="text-muted-foreground text-xs">{entry.created_at}</span>
            </div>
            <p className="text-muted-foreground text-xs">Кто: {entry.user}</p>
            {fields.length > 0 && (
                <ul className="mt-2 flex flex-col gap-0.5">
                    {fields.map((field) => (
                        <li key={field}>
                            <span className="text-muted-foreground">{FIELD_LABELS[field] ?? field}:</span>{' '}
                            {entry.old_values?.[field] !== undefined && (
                                <span className="text-muted-foreground line-through">{String(entry.old_values[field] ?? '—')}</span>
                            )}{' '}
                            {entry.old_values?.[field] !== undefined && '→ '}
                            {String(entry.new_values?.[field] ?? '—')}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

const STATUS_LABELS: Record<MeasureStatus, string> = {
    not_started: 'Не начато',
    in_progress: 'В работе',
    at_risk: 'Есть риск',
    overdue: 'Просрочено',
    done: 'Выполнено',
};

const REVIEW_LABELS: Record<ReviewState, string> = {
    draft: 'Черновик',
    submitted: 'На проверке',
    approved: 'Утверждён',
    rejected: 'Отклонён',
    rework: 'На доработку',
};

const ACCEPTED_FILE_EXTENSIONS = '.doc,.docx,.pdf,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.bmp,.webp,.svg,.tif,.tiff,.heic,.heif';

function StageEditForm({ stage, onDone }: { stage: { id: number; title: string; planned_date: string | null; weight: number }; onDone: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        title: stage.title,
        planned_date: stage.planned_date ?? '',
        weight: String(stage.weight),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        patch(route('measure.stages.update', stage.id), { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 rounded-md bg-muted/40 p-3 text-sm">
            <div className="grid gap-1">
                <Label>Название этапа</Label>
                <Input className="w-56" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
            </div>
            <div className="grid gap-1">
                <Label>Плановая дата</Label>
                <Input type="date" className="w-40" value={data.planned_date} onChange={(e) => setData('planned_date', e.target.value)} />
                {errors.planned_date && <p className="text-xs text-destructive">{errors.planned_date}</p>}
            </div>
            <div className="grid gap-1">
                <Label>Вес, %</Label>
                <Input
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
                Сохранить
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                Отмена
            </Button>
        </form>
    );
}

function AddStageForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        planned_date: '',
        weight: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(route('measure.stages.store'), { preserveScroll: true, onSuccess: () => reset() });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 border-t pt-4">
            <div className="grid gap-1">
                <Label htmlFor="new-stage-title">Название этапа</Label>
                <Input id="new-stage-title" className="w-64" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
            </div>
            <div className="grid gap-1">
                <Label htmlFor="new-stage-date">Плановая дата</Label>
                <Input
                    id="new-stage-date"
                    type="date"
                    className="w-40"
                    value={data.planned_date}
                    onChange={(e) => setData('planned_date', e.target.value)}
                />
                {errors.planned_date && <p className="text-xs text-destructive">{errors.planned_date}</p>}
            </div>
            <div className="grid gap-1">
                <Label htmlFor="new-stage-weight">Вес, %</Label>
                <Input
                    id="new-stage-weight"
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

/** Режим наполнения — до фиксации списка, [[Заполнение и утверждение#Последовательное заполнение этапов]]. */
function StageSetup({ stages }: { stages: StageListItem[] }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    function confirmStages() {
        if (!confirm('Утвердить список этапов? После этого состав и веса менять будет нельзя.')) {
            return;
        }
        router.post(route('measure.stages.confirm'), {}, { preserveScroll: true });
    }

    return (
        <section className="flex flex-col gap-4 rounded-lg border p-4">
            <h2 className="font-medium">Этапы мероприятия</h2>
            <p className="text-muted-foreground text-sm">
                Добавьте все этапы мероприятия, затем нажмите «Утвердить этапы» — после этого состав и веса менять будет нельзя, а
                работа перейдёт в последовательный режим: один этап за раз.
            </p>

            {stages.length === 0 ? (
                <p className="text-muted-foreground text-sm">Этапов пока нет — добавьте хотя бы один ниже.</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {stages.map((s) => (
                        <li key={s.id} className="rounded-md border p-3">
                            {editingId === s.id ? (
                                <StageEditForm stage={s} onDone={() => setEditingId(null)} />
                            ) : (
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {s.order}. {s.title}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {s.planned_date ?? '—'} · Вес: {s.weight}%
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setEditingId(s.id)}>
                                            Изменить
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm(`Удалить этап «${s.title}»?`)) {
                                                    router.delete(route('measure.stages.destroy', s.id), { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            Удалить
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <AddStageForm />

            <Button type="button" onClick={confirmStages} disabled={stages.length === 0} className="self-start">
                Утвердить этапы
            </Button>
        </section>
    );
}

function StageSidebar({ stages }: { stages: StageListItem[] }) {
    return (
        <aside className="flex w-full shrink-0 flex-col gap-2 sm:w-56">
            <h2 className="text-muted-foreground text-sm font-medium">Этапы</h2>
            <ul className="flex flex-col gap-1">
                {stages.map((s) => (
                    <li
                        key={s.id}
                        className={`flex items-start gap-2 rounded-md border p-2 text-sm ${
                            s.status === 'current' ? 'border-primary bg-primary/5' : ''
                        }`}
                    >
                        {s.status === 'completed' && <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />}
                        {s.status === 'locked' && <Lock className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />}
                        {s.status === 'current' && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                        <div className="flex flex-col">
                            <span className={s.status === 'locked' ? 'text-muted-foreground' : 'font-medium'}>
                                {s.order}. {s.title}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {s.planned_date ?? '—'} · {s.weight}%
                            </span>
                        </div>
                    </li>
                ))}
            </ul>
        </aside>
    );
}

/**
 * Рабочее место мероприятия (guard `measure`) — task-007, task-020
 * (последовательное заполнение), [[Функциональные требования#4.7 Рабочее место мероприятия]].
 */
export default function Workspace({ measure, period, measureState, stagesConfirmed, stageList, currentStage, history, changeLog }: WorkspaceProps) {
    const { data, setData, patch, transform, processing, errors } = useForm({
        measure_status: measureState.status,
        risk_text: measureState.risk_text ?? '',
        needs_decision: measureState.needs_decision,
        submitted_by_name: '',
        done_text: currentStage?.update?.done_text ?? '',
        files: [] as File[],
        evidence_url: '',
    });

    const locked = measureState.locked;

    function clearEvidenceFields() {
        setData((prev) => ({ ...prev, files: [], evidence_url: '' }));
    }

    function save(e: FormEvent) {
        e.preventDefault();
        transform((data) => ({ ...data, action: 'save' }));
        patch(route('measure.workspace.update'), { onSuccess: clearEvidenceFields });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        transform((data) => ({ ...data, action: 'submit' }));
        patch(route('measure.workspace.update'), { onSuccess: clearEvidenceFields });
    }

    const reviewState = currentStage?.update?.review_state ?? 'draft';

    return (
        <div className="mx-auto flex max-w-5xl flex-col gap-8 p-6">
            <Head title={`Мероприятие №${measure.number}`} />

            <header className="flex items-start justify-between">
                <div>
                    <p className="text-muted-foreground text-sm">
                        №{measure.number} · {measure.direction} · период {period.month}
                    </p>
                    <h1 className="text-xl font-medium">{measure.title}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Срок: {measure.deadline ?? '—'} · Статус: {STATUS_LABELS[measure.status]} · % исполнения: {measure.percent}
                    </p>
                </div>
                <Button type="button" variant="ghost" size="sm" onClick={() => router.post(route('measure.logout'))}>
                    Выйти
                </Button>
            </header>

            {!stagesConfirmed ? (
                <StageSetup stages={stageList} />
            ) : (
                <div className="flex flex-col gap-6 sm:flex-row">
                    <StageSidebar stages={stageList} />

                    <div className="flex flex-1 flex-col gap-8">
                        {locked && (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                                Этап подан на проверку — редактирование заблокировано до решения администратора.
                            </div>
                        )}

                        {!currentStage ? (
                            <section className="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-900">
                                Все этапы утверждены — мероприятие выполнено на {measure.percent}%.
                            </section>
                        ) : (
                            <>
                                <section className="flex flex-col gap-4 rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <h2 className="font-medium">
                                            Этап {currentStage.order}. {currentStage.title}
                                        </h2>
                                        <Badge variant="outline">{REVIEW_LABELS[reviewState]}</Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Плановая дата: {currentStage.planned_date ?? '—'} · Вес: {currentStage.weight}%
                                    </p>

                                    {currentStage.update?.review_comment && (
                                        <p className="rounded-md bg-muted p-2 text-sm">
                                            Комментарий администратора: {currentStage.update.review_comment}
                                        </p>
                                    )}

                                    <div className="grid gap-2">
                                        <Label>Статус</Label>
                                        <Select
                                            value={data.measure_status}
                                            onValueChange={(v) => setData('measure_status', v as MeasureStatus)}
                                            disabled={locked}
                                        >
                                            <SelectTrigger className="w-64">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(Object.keys(STATUS_LABELS) as MeasureStatus[]).map((s) => (
                                                    <SelectItem key={s} value={s}>
                                                        {STATUS_LABELS[s]}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Что сделано за период</Label>
                                        <Textarea
                                            value={data.done_text}
                                            onChange={(e) => setData('done_text', e.target.value)}
                                            disabled={locked}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="risk_text">Риск / проблема</Label>
                                        <Textarea
                                            id="risk_text"
                                            value={data.risk_text}
                                            onChange={(e) => setData('risk_text', e.target.value)}
                                            disabled={locked}
                                        />
                                    </div>

                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={data.needs_decision}
                                            onCheckedChange={(v) => setData('needs_decision', Boolean(v))}
                                            disabled={locked}
                                        />
                                        Требуется решение руководства
                                    </label>

                                    <div className="flex flex-col gap-2">
                                        <Label>Подтверждающие документы</Label>
                                        {currentStage.evidences.length > 0 && (
                                            <ul className="list-disc pl-5 text-sm">
                                                {currentStage.evidences.map((e) => (
                                                    <li key={e.id}>
                                                        <a
                                                            href={e.path_or_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-primary underline-offset-4 hover:underline"
                                                        >
                                                            {e.title ?? e.path_or_url}
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {!locked && (
                                            <div className="flex flex-col gap-1">
                                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                                    <Input
                                                        type="file"
                                                        multiple
                                                        accept={ACCEPTED_FILE_EXTENSIONS}
                                                        className="w-56"
                                                        onChange={(e) => setData('files', Array.from(e.target.files ?? []))}
                                                    />
                                                    <span className="text-muted-foreground">или</span>
                                                    <Input
                                                        type="url"
                                                        placeholder="ссылка на документ"
                                                        className="w-56"
                                                        value={data.evidence_url}
                                                        onChange={(e) => setData('evidence_url', e.target.value)}
                                                    />
                                                </div>
                                                <p className="text-muted-foreground text-xs">
                                                    Word, Excel, PDF или изображение, до 25 МБ каждый — можно выбрать сразу несколько
                                                    файлов.
                                                </p>
                                                {(errors.files || errors.evidence_url) && (
                                                    <p className="text-destructive text-xs">{errors.files ?? errors.evidence_url}</p>
                                                )}
                                                {data.files.length > 0 && (
                                                    <p className="text-xs">Выбрано файлов: {data.files.length}</p>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div className="grid gap-2 border-t pt-4">
                                        <Label htmlFor="submitted_by_name">ФИО и должность (обязательно при отправке на проверку)</Label>
                                        <Input
                                            id="submitted_by_name"
                                            value={data.submitted_by_name}
                                            onChange={(e) => setData('submitted_by_name', e.target.value)}
                                            disabled={locked}
                                        />
                                        {errors.submitted_by_name && <p className="text-sm text-destructive">{errors.submitted_by_name}</p>}
                                    </div>

                                    <div className="flex gap-3">
                                        <Button type="button" variant="secondary" onClick={save} disabled={processing || locked}>
                                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                            Сохранить черновик
                                        </Button>
                                        <Button type="button" onClick={submit} disabled={processing || locked}>
                                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                            Отправить на проверку
                                        </Button>
                                    </div>
                                </section>
                            </>
                        )}

                        {history.length > 0 && (
                            <section className="flex flex-col gap-3 rounded-lg border p-4">
                                <h2 className="font-medium">Лента результатов проверки</h2>
                                <ul className="flex flex-col gap-2 text-sm">
                                    {history.map((h) => (
                                        <li key={h.id} className="border-t pt-2 first:border-t-0 first:pt-0">
                                            <strong>{REVIEW_LABELS[h.review_state]}</strong> — {h.stage_title}
                                            {h.approved_percent !== null && ` (${h.approved_percent}%)`}
                                            {h.approved_at && <span className="text-muted-foreground"> · {h.approved_at}</span>}
                                            {h.review_comment && <p className="text-muted-foreground">{h.review_comment}</p>}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        <section className="flex flex-col gap-3 rounded-lg border p-4">
                            <h2 className="font-medium">Все изменения</h2>
                            {changeLog.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Изменений пока нет.</p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {changeLog.map((entry) => (
                                        <ChangeLogCard key={entry.id} entry={entry} />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            )}
        </div>
    );
}
