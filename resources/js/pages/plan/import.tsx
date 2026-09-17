import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ImportCredential {
    measure_number: number;
    login: string;
    password: string;
}

interface ImportReport {
    accepted: number;
    updated: number;
    warnings: string[];
    credentials: ImportCredential[];
}

interface ImportPageProps {
    report: ImportReport | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'План', href: '/plan' },
    { title: 'Импорт', href: '/plan/import' },
];

/**
 * Минимальный экран импорта листа «План» (task-005). Полноценный реестр мероприятий
 * появится в task-006 — [[Функциональные требования#4.1 Модуль «План» — реестр мероприятий]].
 */
export default function PlanImport({ report }: ImportPageProps) {
    const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('plan.import.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Импорт плана" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-6">
                <h1 className="text-xl font-medium">Импорт плана из Excel</h1>

                <form className="flex flex-col gap-4 rounded-lg border bg-card p-6" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="file">Файл (xlsx)</Label>
                        <Input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.file} />
                    </div>

                    <Button type="submit" className="w-fit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Импортировать
                    </Button>
                </form>

                {report && (
                    <div className="flex flex-col gap-4 rounded-lg border bg-card p-4 text-sm">
                        <p>
                            Принято новых: <strong>{report.accepted}</strong>. Обновлено: <strong>{report.updated}</strong>.
                        </p>

                        {report.warnings.length > 0 && (
                            <div>
                                <p className="font-medium text-amber-600">Предупреждения:</p>
                                <ul className="list-disc pl-5">
                                    {report.warnings.map((warning, i) => (
                                        <li key={i}>{warning}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {report.credentials.length > 0 && (
                            <div>
                                <p className="font-medium">
                                    Сгенерированы учётные данные ({report.credentials.length}) — пароли показываются один раз,
                                    сохраните список сейчас:
                                </p>
                                <table className="mt-2 w-full text-left">
                                    <thead>
                                        <tr>
                                            <th className="pr-4">№</th>
                                            <th className="pr-4">Логин</th>
                                            <th>Пароль</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.credentials.map((c) => (
                                            <tr key={c.login}>
                                                <td className="pr-4">{c.measure_number}</td>
                                                <td className="pr-4 font-mono">{c.login}</td>
                                                <td className="font-mono">{c.password}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
