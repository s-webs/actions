import { Head, Link, router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { evidenceLabel } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

interface EvidenceRow {
    id: number;
    title: string | null;
    form: string | null;
    type: 'file' | 'link';
    url: string;
    measure: { id: number; number: number; title: string };
    stage_title: string | null;
    period: string | null;
    uploaded_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

interface EvidenceIndexProps {
    evidences: Paginated<EvidenceRow>;
    measures: { id: number; number: number; title: string }[];
    filters: Record<string, string | undefined>;
    canDelete: boolean;
}

const ALL = '__all__';

function isImage(url: string) {
    return /\.(png|jpe?g|gif|webp)$/i.test(url);
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Доказательная база', href: '/evidence' }];

/**
 * Доказательная база — task-014,
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]].
 */
export default function EvidenceIndex({ evidences, measures, filters, canDelete }: EvidenceIndexProps) {
    function applyFilter(measureId: string) {
        router.get(route('evidence.index'), { ...filters, measure_id: measureId === ALL ? undefined : measureId }, { preserveState: true });
    }

    function destroy(id: number) {
        if (confirm('Удалить документ?')) {
            router.delete(route('evidence.destroy', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Доказательная база" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Доказательная база</h1>

                <Select value={filters.measure_id ?? ALL} onValueChange={applyFilter}>
                    <SelectTrigger className="w-72">
                        <SelectValue placeholder="Все мероприятия" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Все мероприятия</SelectItem>
                        {measures.map((m) => (
                            <SelectItem key={m.id} value={String(m.id)}>
                                №{m.number}. {m.title}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {evidences.data.map((e) => (
                        <div key={e.id} className="flex min-w-0 flex-col gap-2 overflow-hidden rounded-lg border p-3 text-sm">
                            {isImage(e.url) ? (
                                <img src={e.url} alt={e.title ?? ''} className="h-32 w-full rounded object-cover" />
                            ) : (
                                <div className="flex h-32 items-center justify-center rounded bg-muted text-muted-foreground">
                                    {e.type === 'link' ? 'Ссылка' : 'Файл'}
                                </div>
                            )}
                            <a
                                href={e.url}
                                target="_blank"
                                rel="noreferrer"
                                className="break-all font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {evidenceLabel(e.url, e.title)}
                            </a>
                            <p className="text-muted-foreground break-words">
                                №{e.measure.number}. {e.measure.title}
                                {e.stage_title && ` · ${e.stage_title}`}
                            </p>
                            <p className="text-muted-foreground">
                                {e.period ?? '—'} · {e.uploaded_at}
                            </p>
                            {canDelete && (
                                <Button size="sm" variant="destructive" className="w-full" onClick={() => destroy(e.id)}>
                                    Удалить
                                </Button>
                            )}
                        </div>
                    ))}
                </div>

                {evidences.data.length === 0 && <p className="text-muted-foreground text-sm">Документов не найдено.</p>}

                <div className="flex items-center justify-between text-sm">
                    <p className="text-muted-foreground">
                        Показано {evidences.from ?? 0}–{evidences.to ?? 0} из {evidences.total}
                    </p>
                    <div className="flex gap-1">
                        {evidences.links.map((link, i) => (
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
