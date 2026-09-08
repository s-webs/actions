import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
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

interface StageEvidence {
    id: number;
    title: string | null;
    type: 'file' | 'link';
    path_or_url: string;
}

interface StageUpdate {
    id: number;
    done_text: string | null;
    next_step: string | null;
    next_step_date: string | null;
    review_state: ReviewState;
    review_comment: string | null;
    approved_percent: number | null;
}

interface Stage {
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
    stages: Stage[];
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

function EvidenceUploader({ stageId }: { stageId: number }) {
    const [file, setFile] = useState<File | null>(null);
    const [url, setUrl] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        const form = new FormData();
        if (file) form.append('file', file);
        if (url) form.append('url', url);

        router.post(route('measure.workspace.evidence', stageId), form, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setError(errors.file ?? errors.url ?? 'Не удалось прикрепить документ.'),
            onSuccess: () => {
                setFile(null);
                setUrl('');
            },
            onFinish: () => setBusy(false),
        });
    }

    return (
        <div className="flex flex-col gap-1">
            <form onSubmit={submit} className="flex flex-wrap items-center gap-2 text-sm">
                <Input
                    type="file"
                    accept={ACCEPTED_FILE_EXTENSIONS}
                    className="w-56"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                />
                <span className="text-muted-foreground">или</span>
                <Input type="url" placeholder="ссылка на документ" className="w-56" value={url} onChange={(e) => setUrl(e.target.value)} />
                <Button type="submit" size="sm" variant="secondary" disabled={busy || (!file && !url)}>
                    Прикрепить
                </Button>
            </form>
            <p className="text-muted-foreground text-xs">Word, Excel, PDF или изображение, до 25 МБ.</p>
            {error && <p className="text-destructive text-xs">{error}</p>}
        </div>
    );
}

/**
 * Рабочее место мероприятия (guard `measure`) — task-007,
 * [[Функциональные требования#4.7 Рабочее место мероприятия]].
 */
export default function Workspace({ measure, period, measureState, stages, history, changeLog }: WorkspaceProps) {
    const { data, setData, patch, transform, processing, errors } = useForm({
        measure_status: measureState.status,
        risk_text: measureState.risk_text ?? '',
        needs_decision: measureState.needs_decision,
        submitted_by_name: '',
        stages: stages.map((s) => ({
            id: s.id,
            done_text: s.update?.done_text ?? '',
            next_step: s.update?.next_step ?? '',
            next_step_date: s.update?.next_step_date ?? '',
        })),
    });

    const locked = measureState.locked;

    function updateStageField(stageId: number, field: 'done_text' | 'next_step' | 'next_step_date', value: string) {
        setData(
            'stages',
            data.stages.map((s) => (s.id === stageId ? { ...s, [field]: value } : s)),
        );
    }

    function save(e: FormEvent) {
        e.preventDefault();
        transform((data) => ({ ...data, action: 'save' }));
        patch(route('measure.workspace.update'));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        transform((data) => ({ ...data, action: 'submit' }));
        patch(route('measure.workspace.update'));
    }

    return (
        <div className="mx-auto flex max-w-4xl flex-col gap-8 p-6">
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

            {locked && (
                <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    Этапы поданы на проверку — редактирование заблокировано до решения проректора.
                </div>
            )}

            <section className="flex flex-col gap-4 rounded-lg border p-4">
                <h2 className="font-medium">Статус мероприятия</h2>

                <div className="grid gap-2">
                    <Label>Статус</Label>
                    <Select value={data.measure_status} onValueChange={(v) => setData('measure_status', v as MeasureStatus)} disabled={locked}>
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
            </section>

            {stages.map((stage) => {
                const stageData = data.stages.find((s) => s.id === stage.id)!;
                const reviewState = stage.update?.review_state ?? 'draft';

                return (
                    <section key={stage.id} className="flex flex-col gap-4 rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">
                                Этап {stage.order}. {stage.title}
                            </h2>
                            <Badge variant="outline">{REVIEW_LABELS[reviewState]}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Плановая дата: {stage.planned_date ?? '—'} · Вес: {stage.weight}%
                        </p>

                        {stage.update?.review_comment && (
                            <p className="rounded-md bg-muted p-2 text-sm">Комментарий проректора: {stage.update.review_comment}</p>
                        )}

                        <div className="grid gap-2">
                            <Label>Что сделано за период</Label>
                            <Textarea
                                value={stageData.done_text}
                                onChange={(e) => updateStageField(stage.id, 'done_text', e.target.value)}
                                disabled={locked}
                            />
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Следующий шаг</Label>
                                <Textarea
                                    value={stageData.next_step}
                                    onChange={(e) => updateStageField(stage.id, 'next_step', e.target.value)}
                                    disabled={locked}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Срок следующего шага</Label>
                                <Input
                                    type="date"
                                    value={stageData.next_step_date}
                                    onChange={(e) => updateStageField(stage.id, 'next_step_date', e.target.value)}
                                    disabled={locked}
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label>Подтверждающие документы</Label>
                            {stage.evidences.length > 0 && (
                                <ul className="list-disc pl-5 text-sm">
                                    {stage.evidences.map((e) => (
                                        <li key={e.id}>
                                            <a href={e.path_or_url} target="_blank" rel="noreferrer" className="text-primary underline-offset-4 hover:underline">
                                                {e.title ?? e.path_or_url}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {!locked && <EvidenceUploader stageId={stage.id} />}
                        </div>
                    </section>
                );
            })}

            <section className="flex flex-col gap-4 rounded-lg border p-4">
                <div className="grid gap-2">
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
    );
}
