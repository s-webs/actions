import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useLabels } from '@/lib/labels';
import { evidenceLabel } from '@/lib/utils';

export type StageDetailReviewState = 'draft' | 'submitted' | 'approved' | 'rejected' | 'rework';

export interface StageDetailEvidence {
    id: number;
    title: string | null;
    type: 'file' | 'link';
    path_or_url: string;
}

export interface StageDetailUpdate {
    id: number;
    done_text: string | null;
    review_state: StageDetailReviewState;
    review_comment: string | null;
    approved_percent: number | null;
    submitted_by_name?: string | null;
    submitted_at?: string | null;
}

export interface StageDetail {
    id: number;
    order: number;
    title: string;
    planned_date: string | null;
    weight: number;
    update: StageDetailUpdate | null;
    evidences: StageDetailEvidence[];
}

export function EvidenceList({ evidences }: { evidences: StageDetailEvidence[] }) {
    if (evidences.length === 0) {
        return <p className="text-muted-foreground text-sm">Документов нет.</p>;
    }

    return (
        <ul className="min-w-0 list-disc pl-5 text-sm">
            {evidences.map((e) => (
                <li key={e.id} className="min-w-0 break-all">
                    <a
                        href={e.path_or_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary underline-offset-4 hover:underline"
                    >
                        {evidenceLabel(e.path_or_url, e.title)}
                    </a>
                </li>
            ))}
        </ul>
    );
}

export function StageDetailCard({ stage }: { stage: StageDetail }) {
    const { reviewState: reviewStateLabel } = useLabels();

    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader className="flex min-w-0 flex-row items-start justify-between gap-4 space-y-0 pr-8">
                <div className="min-w-0">
                    <CardTitle className="text-base break-words">
                        Этап {stage.order}. {stage.title}
                    </CardTitle>
                    <CardDescription className="break-words">
                        Плановая дата: {stage.planned_date ?? '—'} · Вес: {stage.weight}%
                        {stage.update?.approved_percent != null ? ` · Утверждено ${stage.update.approved_percent}%` : ''}
                    </CardDescription>
                </div>
                {stage.update?.review_state && (
                    <Badge variant="outline" className="shrink-0">
                        {reviewStateLabel(stage.update.review_state)}
                    </Badge>
                )}
            </CardHeader>
            <CardContent className="flex min-w-0 flex-col gap-4 text-sm">
                <div className="grid min-w-0 gap-1">
                    <p className="font-medium">Что сделано</p>
                    <p className="whitespace-pre-wrap break-words">{stage.update?.done_text || '—'}</p>
                </div>
                {stage.update?.review_comment && (
                    <div className="grid min-w-0 gap-1">
                        <p className="font-medium">Комментарий администратора</p>
                        <p className="break-words">{stage.update.review_comment}</p>
                    </div>
                )}
                {(stage.update?.submitted_by_name || stage.update?.submitted_at) && (
                    <p className="text-muted-foreground">
                        Подал: {stage.update.submitted_by_name ?? '—'}
                        {stage.update.submitted_at ? ` · ${stage.update.submitted_at}` : ''}
                    </p>
                )}
                <div className="grid min-w-0 gap-1">
                    <p className="font-medium">Документы</p>
                    <EvidenceList evidences={stage.evidences} />
                </div>
            </CardContent>
        </Card>
    );
}
