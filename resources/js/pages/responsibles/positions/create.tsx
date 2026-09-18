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

interface MeasureOption {
    id: number;
    number: number;
    title: string;
    responsible_id: number | null;
}

interface Props {
    measures: MeasureOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Должности и ответственные', href: '/responsibles/accounts' },
    { title: 'Новая должность', href: '/responsibles/positions/create' },
];

export default function ResponsiblePositionCreate({ measures }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        measure_ids: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('responsibles.positions.store'));
    };

    function toggleMeasure(id: number, checked: boolean) {
        setData('measure_ids', checked ? [...data.measure_ids, id] : data.measure_ids.filter((current) => current !== id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новая должность" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Новая должность</h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('responsibles.accounts.index')}>К списку</Link>
                    </Button>
                </div>

                <form className="flex flex-col gap-6 rounded-lg border bg-card p-6" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Должность</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-3">
                        <Label>Мероприятия</Label>
                        <div className="max-h-80 overflow-y-auto rounded-lg border p-3">
                            {measures.length === 0 && <p className="text-sm text-muted-foreground">Мероприятий пока нет.</p>}
                            {measures.map((measure) => (
                                <label key={measure.id} className="flex items-start gap-2 py-1.5 text-sm">
                                    <Checkbox
                                        checked={data.measure_ids.includes(measure.id)}
                                        onCheckedChange={(checked) => toggleMeasure(measure.id, checked === true)}
                                    />
                                    <span>
                                        №{measure.number}. {measure.title}
                                        {measure.responsible_id ? (
                                            <span className="text-muted-foreground"> (сейчас у другой должности)</span>
                                        ) : null}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.measure_ids} />
                    </div>

                    <Button type="submit" className="w-fit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Создать
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
