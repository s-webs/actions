import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface Evidence {
    id: number;
    title: string | null;
    path_or_url: string;
    type: 'file' | 'link';
}

interface QueueItem {
    id: number;
    review_state: 'submitted' | 'rework';
    done_text: string | null;
    next_step: string | null;
    next_step_date: string | null;
    submitted_by_name: string | null;
    submitted_at: string | null;
    period: string;
    stage: { id: number; title: string; planned_date: string | null; weight: number };
    measure: { number: number; title: string; direction: string | null; deadline: string | null; risk_level: string | null };
    evidences: Evidence[];
}

interface ApprovalIndexProps {
    updates: QueueItem[];
}

const RISK_LABELS: Record<string, string> = { high: 'Высокий', medium: 'Средний', low: 'Низкий' };

/**
 * Очередь «Этапы на проверку» — task-008,
 * [[Функциональные требования#4.13 Модуль «Проверка и утверждение этапов» (проректор)]].
 */
export default function ApprovalIndex({ updates }: ApprovalIndexProps) {
    const [selectedId, setSelectedId] = useState<number | null>(updates[0]?.id ?? null);
    const [percent, setPercent] = useState('');
    const [comment, setComment] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const selected = updates.find((u) => u.id === selectedId) ?? null;

    function act(action: 'approve' | 'reject' | 'rework') {
        if (!selected) return;

        const payload = action === 'approve' ? { approved_percent: percent } : { review_comment: comment };

        router.post(route(`approval.${action}`, selected.id), payload, {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => {
                setPercent('');
                setComment('');
                setErrors({});
            },
        });
    }

    function submitApprove(e: FormEvent) {
        e.preventDefault();
        act('approve');
    }

    return (
        <AppLayout>
            <Head title="Проверка этапов" />

            <div className="grid grid-cols-1 gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-medium">Этапы на проверку ({updates.length})</h1>
                    {updates.length === 0 && <p className="text-muted-foreground text-sm">Очередь пуста.</p>}
                    {updates.map((u) => (
                        <button
                            key={u.id}
                            onClick={() => setSelectedId(u.id)}
                            className={`rounded-lg border p-3 text-left text-sm ${selectedId === u.id ? 'border-primary bg-muted/50' : ''}`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    №{u.measure.number}. {u.measure.title}
                                </span>
                                <Badge variant={u.review_state === 'rework' ? 'secondary' : 'outline'}>
                                    {u.review_state === 'rework' ? 'на доработке' : 'подан'}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground">{u.stage.title}</p>
                            {u.measure.risk_level === 'high' && <Badge variant="destructive">Высокий риск</Badge>}
                        </button>
                    ))}
                </div>

                {selected && (
                    <div className="flex flex-col gap-4 rounded-lg border p-4">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                №{selected.measure.number} · {selected.measure.direction} · период {selected.period}
                            </p>
                            <h2 className="text-lg font-medium">{selected.measure.title}</h2>
                            <p className="text-muted-foreground text-sm">
                                Этап: {selected.stage.title} (вес {selected.stage.weight}%, плановая дата{' '}
                                {selected.stage.planned_date ?? '—'}) · Срок мероприятия: {selected.measure.deadline ?? '—'} · Риск:{' '}
                                {selected.measure.risk_level ? RISK_LABELS[selected.measure.risk_level] : '—'}
                            </p>
                        </div>

                        <div className="grid gap-2 text-sm">
                            <p>
                                <strong>Что сделано:</strong> {selected.done_text ?? '—'}
                            </p>
                            <p>
                                <strong>Следующий шаг:</strong> {selected.next_step ?? '—'} ({selected.next_step_date ?? '—'})
                            </p>
                            <p>
                                <strong>Подал:</strong> {selected.submitted_by_name ?? '—'} · {selected.submitted_at ?? '—'}
                            </p>
                        </div>

                        {selected.evidences.length > 0 && (
                            <div>
                                <p className="text-sm font-medium">Документы</p>
                                <ul className="list-disc pl-5 text-sm">
                                    {selected.evidences.map((e) => (
                                        <li key={e.id}>
                                            <a href={e.path_or_url} target="_blank" rel="noreferrer" className="text-primary underline-offset-4 hover:underline">
                                                {e.title ?? e.path_or_url}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {selected.evidences.length === 0 && (
                            <p className="text-muted-foreground text-sm">Документов нет — 100% недоступно без доказательства.</p>
                        )}

                        <form onSubmit={submitApprove} className="flex items-end gap-2 border-t pt-4">
                            <div className="grid gap-2">
                                <Label htmlFor="approved_percent">Утвердить, %</Label>
                                <Input
                                    id="approved_percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    className="w-24"
                                    value={percent}
                                    onChange={(e) => setPercent(e.target.value)}
                                />
                                {errors.approved_percent && <p className="text-sm text-destructive">{errors.approved_percent}</p>}
                            </div>
                            <Button type="submit">Утвердить</Button>
                        </form>

                        <div className="grid gap-2 border-t pt-4">
                            <Label htmlFor="review_comment">Комментарий (для отклонения / доработки)</Label>
                            <Textarea id="review_comment" value={comment} onChange={(e) => setComment(e.target.value)} />
                            {errors.review_comment && <p className="text-sm text-destructive">{errors.review_comment}</p>}
                            <div className="flex gap-2">
                                <Button type="button" variant="destructive" onClick={() => act('reject')}>
                                    Отклонить
                                </Button>
                                <Button type="button" variant="secondary" onClick={() => act('rework')}>
                                    На доработку
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
