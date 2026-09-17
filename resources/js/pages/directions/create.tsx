import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface DirectionsCreateProps {
    nextNumber: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Направления', href: '/directions' },
    { title: 'Новое', href: '/directions/create' },
];

export default function DirectionsCreate({ nextNumber }: DirectionsCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        number: String(nextNumber),
        name: '',
        in_summary: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('directions.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новое направление" />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Новое направление</h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('directions.index')}>К списку</Link>
                    </Button>
                </div>

                <form className="flex flex-col gap-4 rounded-lg border bg-card p-6" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="number">№</Label>
                        <Input
                            id="number"
                            type="number"
                            min={1}
                            max={255}
                            value={data.number}
                            onChange={(e) => setData('number', e.target.value)}
                        />
                        <InputError message={errors.number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Название</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="in_summary"
                            checked={data.in_summary}
                            onCheckedChange={(checked) => setData('in_summary', checked === true)}
                        />
                        <Label htmlFor="in_summary">Показывать в своде дашборда</Label>
                    </div>
                    <InputError message={errors.in_summary} />

                    <Button type="submit" className="w-fit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Создать
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
