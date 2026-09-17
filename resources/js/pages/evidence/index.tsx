import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface MeasureRow {
    id: number;
    number: number;
    title: string;
    evidences_count: number;
}

interface EvidenceIndexProps {
    measures: MeasureRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Доказательная база', href: '/evidence' }];

/**
 * Доказательная база — task-014,
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]].
 */
export default function EvidenceIndex({ measures }: EvidenceIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Доказательная база" />

            <div className="flex flex-col gap-4 p-6">
                <h1 className="text-xl font-medium">Доказательная база</h1>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">№</th>
                                <th className="p-3">Название</th>
                                <th className="p-3">Документов</th>
                            </tr>
                        </thead>
                        <tbody>
                            {measures.map((measure) => (
                                <tr key={measure.id} className="border-t">
                                    <td className="p-3">{measure.number}</td>
                                    <td className="p-3">
                                        <Link
                                            href={route('evidence.show', measure.id)}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {measure.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">{measure.evidences_count}</td>
                                </tr>
                            ))}
                            {measures.length === 0 && (
                                <tr className="border-t">
                                    <td className="text-muted-foreground p-3" colSpan={3}>
                                        Мероприятий пока нет
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
