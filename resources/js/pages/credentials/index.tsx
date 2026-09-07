import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface SessionRow {
    id: number;
    ip: string | null;
    user_agent: string | null;
    started_at: string;
    last_seen_at: string | null;
}

interface CredentialRow {
    id: number;
    number: number;
    title: string;
    login: string | null;
    rotated_at: string | null;
    expires_at: string | null;
    active_sessions: number;
    sessions: SessionRow[];
}

interface JustRotated {
    measure_number: number;
    login: string;
    password: string;
}

interface CredentialsProps {
    rows: CredentialRow[];
    justRotated: JustRotated | null;
    justRotatedBulk: JustRotated[] | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Учётные данные мероприятий', href: '/credentials' }];

function downloadCsv(rows: JustRotated[]) {
    const header = 'Мероприятие;Логин;Пароль\n';
    const body = rows.map((r) => `${r.measure_number};${r.login};${r.password}`).join('\n');
    const blob = new Blob(['﻿' + header + body], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'credentials.csv';
    a.click();
    URL.revokeObjectURL(url);
}

/**
 * Учётные данные мероприятий — task-015,
 * [[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]]. «Показать
 * пароль» существует только как часть перегенерации — пароль хранится хешем, повторно
 * посмотреть уже установленный пароль невозможно ни на бэкенде, ни здесь.
 */
export default function CredentialsIndex({ rows, justRotated, justRotatedBulk }: CredentialsProps) {
    const [expanded, setExpanded] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Учётные данные мероприятий" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-medium">Учётные данные мероприятий</h1>
                    <Button
                        variant="destructive"
                        onClick={() => confirm('Перегенерировать пароли всех 51 мероприятий? Старые сессии закроются.') && router.post(route('credentials.rotate-all'))}
                    >
                        Перегенерировать все
                    </Button>
                </div>

                {justRotated && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        Новый пароль для №{justRotated.measure_number} ({justRotated.login}): <strong className="font-mono">{justRotated.password}</strong>
                        <br />
                        Показывается один раз — сохраните сейчас.
                    </div>
                )}

                {justRotatedBulk && justRotatedBulk.length > 0 && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <p>Сгенерировано {justRotatedBulk.length} новых паролей — показываются один раз.</p>
                        <Button size="sm" variant="secondary" className="mt-2" onClick={() => downloadCsv(justRotatedBulk)}>
                            Скачать CSV
                        </Button>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="w-8 p-3"></th>
                                <th className="p-3">Мероприятие</th>
                                <th className="p-3">Логин</th>
                                <th className="p-3">Ротация</th>
                                <th className="p-3">Срок действия</th>
                                <th className="p-3">Активные сессии</th>
                                <th className="p-3">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <>
                                    <tr key={row.id} className="border-t">
                                        <td className="p-3">
                                            <button onClick={() => setExpanded(expanded === row.id ? null : row.id)} className="text-muted-foreground">
                                                {expanded === row.id ? '▾' : '▸'}
                                            </button>
                                        </td>
                                        <td className="p-3">
                                            №{row.number}. {row.title}
                                        </td>
                                        <td className="p-3 font-mono">{row.login ?? '—'}</td>
                                        <td className="p-3">{row.rotated_at ?? '—'}</td>
                                        <td className="p-3">
                                            <Input
                                                type="date"
                                                defaultValue={row.expires_at ?? ''}
                                                className="w-40"
                                                onBlur={(e) => router.patch(route('credentials.expiry', row.id), { expires_at: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="p-3">{row.active_sessions}</td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => confirm('Перегенерировать пароль? Старые сессии закроются.') && router.post(route('credentials.rotate', row.id))}
                                            >
                                                Перегенерировать
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={row.active_sessions === 0}
                                                onClick={() => router.post(route('credentials.terminate-sessions', row.id))}
                                            >
                                                Завершить сессии
                                            </Button>
                                        </td>
                                    </tr>
                                    {expanded === row.id && (
                                        <tr>
                                            <td colSpan={7} className="bg-muted/20 p-4">
                                                {row.sessions.length === 0 ? (
                                                    <p className="text-muted-foreground text-sm">Входов ещё не было.</p>
                                                ) : (
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="text-muted-foreground">
                                                            <tr>
                                                                <th className="pr-4">Вход</th>
                                                                <th className="pr-4">Последняя активность</th>
                                                                <th className="pr-4">IP</th>
                                                                <th>Устройство</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {row.sessions.map((s) => (
                                                                <tr key={s.id}>
                                                                    <td className="pr-4">{s.started_at}</td>
                                                                    <td className="pr-4">{s.last_seen_at ?? '—'}</td>
                                                                    <td className="pr-4">{s.ip}</td>
                                                                    <td className="truncate">{s.user_agent}</td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
