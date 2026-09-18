import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ResponsibleAccountRow {
    id: number;
    name: string;
    occupant: string | null;
    email: string | null;
    measures_count: number;
    has_account: boolean;
}

interface Props {
    responsibles: ResponsibleAccountRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Должности и ответственные', href: '/responsibles/accounts' }];

export default function ResponsibleAccountsIndex({ responsibles }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Должности и ответственные" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Должности и ответственные</h1>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('responsibles.positions.create')}>Новая должность</Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('responsibles.accounts.create')}>Зарегистрировать сотрудника</Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-3">Должность</th>
                                <th className="p-3">Сотрудник</th>
                                <th className="p-3">Email</th>
                                <th className="p-3">Мероприятий</th>
                                <th className="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {responsibles.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-3">{row.name}</td>
                                    <td className="p-3">{row.occupant ?? '—'}</td>
                                    <td className="p-3">{row.email ?? '—'}</td>
                                    <td className="p-3">{row.measures_count}</td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={route('responsibles.positions.edit', row.id)}>Изменить должность</Link>
                                            </Button>
                                            {row.has_account ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={route('responsibles.accounts.edit', row.id)}>Учётная запись</Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm">
                                                    <Link href={route('responsibles.accounts.create', { responsible_id: row.id })}>
                                                        Назначить сотрудника
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {responsibles.length === 0 && (
                                <tr className="border-t">
                                    <td className="p-3 text-muted-foreground" colSpan={5}>
                                        Пока нет должностей в справочнике.
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
