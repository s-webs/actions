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

interface DirectionForm {
    id: number;
    number: number;
    name: string;
    in_summary: boolean;
    measures_count: number;
}

interface DirectionsEditProps {
    direction: DirectionForm;
}

export default function DirectionsEdit({ direction }: DirectionsEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Направления', href: '/directions' },
        { title: direction.name, href: route('directions.edit', direction.id) },
    ];

    const { data, setData, put, processing, errors } = useForm({
        number: String(direction.number),
        name: direction.name,
        in_summary: direction.in_summary,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('directions.update', direction.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Направление: ${direction.name}`} />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Изменить направление</h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('directions.index')}>К списку</Link>
                    </Button>
                </div>

                {direction.measures_count > 0 && (
                    <p className="text-sm text-muted-foreground">
                        Привязано мероприятий: {direction.measures_count}. Удаление недоступно, пока есть связи.
                    </p>
                )}

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
                        Сохранить
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
