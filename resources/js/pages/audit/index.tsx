import { Head, router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface AuditRow {
    id: number;
    event: string;
    model: string;
    auditable_id: number;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    user: string | null;
    created_at: string;
}

interface AuditProps {
    audits: { data: AuditRow[]; from: number | null; to: number | null; total: number };
    models: Record<string, string>;
    filters: Record<string, string | undefined>;
}

const EVENT_LABELS: Record<string, string> = { created: 'создано', updated: 'изменено', deleted: 'удалено' };
const ALL = '__all__';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'История изменений', href: '/audit' }];

/**
 * История изменений — task-019,
 * [[Функциональные требования#4.11 Аудит и история изменений]].
 */
export default function AuditIndex({ audits, models, filters }: AuditProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="История изменений" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">История изменений</h1>

                <Select
                    value={filters.model ?? ALL}
                    onValueChange={(v) => router.get(route('audit.index'), { model: v === ALL ? undefined : v }, { preserveState: true })}
                >
                    <SelectTrigger className="w-64">
                        <SelectValue placeholder="Все модели" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Все модели</SelectItem>
                        {Object.entries(models).map(([key, label]) => (
                            <SelectItem key={key} value={key}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">Когда</th>
                                <th className="p-3">Кто</th>
                                <th className="p-3">Что</th>
                                <th className="p-3">Событие</th>
                                <th className="p-3">Изменения</th>
                            </tr>
                        </thead>
                        <tbody>
                            {audits.data.map((a) => (
                                <tr key={a.id} className="border-t align-top">
                                    <td className="p-3 whitespace-nowrap">{a.created_at}</td>
                                    <td className="p-3">{a.user ?? '—'}</td>
                                    <td className="p-3">
                                        {a.model} #{a.auditable_id}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant="outline">{EVENT_LABELS[a.event] ?? a.event}</Badge>
                                    </td>
                                    <td className="p-3">
                                        {Object.keys(a.new_values ?? {}).map((field) => (
                                            <div key={field}>
                                                <span className="font-mono text-xs">{field}</span>:{' '}
                                                <span className="text-muted-foreground line-through">{String(a.old_values?.[field] ?? '—')}</span>{' '}
                                                → {String(a.new_values[field])}
                                            </div>
                                        ))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {audits.data.length === 0 && <p className="text-muted-foreground text-sm">Записей не найдено.</p>}

                <p className="text-muted-foreground text-sm">
                    Показано {audits.from ?? 0}–{audits.to ?? 0} из {audits.total}
                </p>
            </div>
        </AppLayout>
    );
}
