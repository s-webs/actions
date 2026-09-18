import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface PositionOption {
    id: number;
    name: string;
    occupied: boolean;
}

interface Props {
    positions: PositionOption[];
    prefillResponsibleId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Должности и ответственные', href: '/responsibles/accounts' },
    { title: 'Регистрация', href: '/responsibles/accounts/create' },
];

export default function ResponsibleAccountCreate({ positions, prefillResponsibleId }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        responsible_id: prefillResponsibleId ? String(prefillResponsibleId) : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('responsibles.accounts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Регистрация ответственного" />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Регистрация сотрудника</h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('responsibles.accounts.index')}>К списку</Link>
                    </Button>
                </div>

                {positions.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Сначала{' '}
                        <Link href={route('responsibles.positions.create')} className="underline">
                            создайте должность
                        </Link>
                        , затем назначьте на неё сотрудника.
                    </p>
                )}

                <form className="flex flex-col gap-4 rounded-lg border bg-card p-6" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="name">ФИО</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Пароль</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Должность</Label>
                        <Select
                            value={data.responsible_id || undefined}
                            onValueChange={(value) => setData('responsible_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Выберите должность" />
                            </SelectTrigger>
                            <SelectContent>
                                {positions.map((position) => (
                                    <SelectItem key={position.id} value={String(position.id)}>
                                        {position.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.responsible_id} />
                    </div>

                    <Button type="submit" className="w-fit" disabled={processing || positions.length === 0}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Создать учётную запись
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
