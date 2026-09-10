import { Head, Link, router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface DirectionRow {
    id: number;
    number: number;
    name: string;
    in_summary: boolean;
    measures_count: number;
}

interface DirectionsIndexProps {
    directions: DirectionRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Направления', href: '/directions' }];

export default function DirectionsIndex({ directions }: DirectionsIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Направления" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Направления</h1>
                    <Button asChild>
                        <Link href={route('directions.create')}>Добавить направление</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">№</th>
                                <th className="p-3">Название</th>
                                <th className="p-3">В своде дашборда</th>
                                <th className="p-3">Мероприятий</th>
                                <th className="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {directions.map((direction) => (
                                <tr key={direction.id} className="border-t">
                                    <td className="p-3">{direction.number}</td>
                                    <td className="p-3">{direction.name}</td>
                                    <td className="p-3">{direction.in_summary ? 'Да' : 'Нет'}</td>
                                    <td className="p-3">{direction.measures_count}</td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={route('directions.edit', direction.id)}>Изменить</Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                disabled={direction.measures_count > 0}
                                                title={
                                                    direction.measures_count > 0
                                                        ? 'Нельзя удалить: есть связанные мероприятия'
                                                        : 'Удалить'
                                                }
                                                onClick={() => {
                                                    if (confirm(`Удалить направление «${direction.name}»?`)) {
                                                        router.delete(route('directions.destroy', direction.id));
                                                    }
                                                }}
                                            >
                                                Удалить
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {directions.length === 0 && (
                                <tr className="border-t">
                                    <td className="p-3 text-muted-foreground" colSpan={5}>
                                        Направлений пока нет
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
