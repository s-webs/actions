import { Head, router } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
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
    password: string | null;
    login_url: string | null;
    rotated_at: string | null;
    expires_at: string | null;
    active_sessions: number;
    sessions: SessionRow[];
}

interface CredentialsProps {
    rows: CredentialRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Учётные данные мероприятий', href: '/credentials' }];

function downloadCsv(rows: CredentialRow[]) {
    const header = 'Мероприятие;Логин;Пароль;Ссылка для входа\n';
    const body = rows
        .filter((r) => r.login)
        .map((r) => `${r.number};${r.login};${r.password ?? ''};${r.login_url ?? ''}`)
        .join('\n');
    const blob = new Blob(['﻿' + header + body], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'credentials.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard API недоступен (напр. небезопасный контекст) — молча игнорируем.
        }
    }

    return (
        <Button type="button" size="icon" variant="ghost" className="h-6 w-6" onClick={copy} title={`Копировать: ${label}`}>
            {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
        </Button>
    );
}

/**
 * Учётные данные мероприятий — task-015,
 * [[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]]. Логин, пароль
 * и прямая ссылка входа читаемы постоянно (решение заказчика 2026-09-09) — пароль
 * хранится на бэкенде обратимо зашифрованным, а не хешем, специально ради этого
 * экрана, см. [[Роли и права#Реализация]].
 */
export default function CredentialsIndex({ rows }: CredentialsProps) {
    const [expanded, setExpanded] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Учётные данные мероприятий" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-medium">Учётные данные мероприятий</h1>
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" onClick={() => downloadCsv(rows)}>
                            Скачать CSV
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                confirm('Перегенерировать пароли и ссылки всех 51 мероприятий? Старые сессии закроются, старые ссылки перестанут работать.') &&
                                router.post(route('credentials.rotate-all'))
                            }
                        >
                            Перегенерировать все
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="w-8 p-3"></th>
                                <th className="p-3">Мероприятие</th>
                                <th className="p-3">Логин</th>
                                <th className="p-3">Пароль</th>
                                <th className="p-3">Ссылка для входа</th>
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
                                        <td className="p-3">
                                            {row.login ? (
                                                <span className="flex items-center gap-1 font-mono">
                                                    {row.login}
                                                    <CopyButton value={row.login} label="логин" />
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {row.password ? (
                                                <span className="flex items-center gap-1 font-mono">
                                                    {row.password}
                                                    <CopyButton value={row.password} label="пароль" />
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {row.login_url ? (
                                                <CopyButton value={row.login_url} label="ссылка для входа" />
                                            ) : (
                                                '—'
                                            )}
                                        </td>
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
                                                onClick={() =>
                                                    confirm('Перегенерировать пароль и ссылку входа? Старые сессии закроются, старая ссылка перестанет работать.') &&
                                                    router.post(route('credentials.rotate', row.id))
                                                }
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
                                            <td colSpan={9} className="bg-muted/20 p-4">
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
