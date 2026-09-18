import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface CredentialFlash {
    measure_number: number;
    login: string;
    password: string;
}

interface StageDraft {
    title: string;
    planned_date: string;
    weight: string;
}

interface PlanCreateProps {
    directions: { id: number; number: number; name: string }[];
    responsibles: { id: number; name: string; occupant: string | null }[];
    nextNumber: number;
    credential: CredentialFlash | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'План', href: '/plan' },
    { title: 'Новое мероприятие', href: '/plan/create' },
];

const NONE = '__none__';

/**
 * Ручное создание одного мероприятия — дополнение к импорту Excel.
 */
export default function PlanCreate({ directions, responsibles, nextNumber, credential }: PlanCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        number: String(nextNumber),
        title: '',
        direction_id: '',
        responsible_id: '',
        deadline: '',
        stages: [] as StageDraft[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('plan.measures.store'));
    };

    function addStage() {
        setData('stages', [...data.stages, { title: '', planned_date: '', weight: '' }]);
    }

    function updateStage(index: number, field: keyof StageDraft, value: string) {
        setData(
            'stages',
            data.stages.map((stage, i) => (i === index ? { ...stage, [field]: value } : stage)),
        );
    }

    function removeStage(index: number) {
        setData(
            'stages',
            data.stages.filter((_, i) => i !== index),
        );
    }

    function stageError(index: number, field: keyof StageDraft): string | undefined {
        return errors[`stages.${index}.${field}` as keyof typeof errors] as string | undefined;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новое мероприятие" />

            <div className="flex w-full flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">Новое мероприятие</h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('plan.index')}>К реестру</Link>
                    </Button>
                </div>

                {credential && (
                    <div className="flex flex-col gap-2 rounded-lg border bg-card p-4 text-sm">
                        <p className="font-medium">
                            Мероприятие №{credential.measure_number} создано. Учётные данные показываются один раз —
                            сохраните их сейчас:
                        </p>
                        <p>
                            Логин: <span className="font-mono">{credential.login}</span>
                        </p>
                        <p>
                            Пароль: <span className="font-mono">{credential.password}</span>
                        </p>
                    </div>
                )}

                <form className="flex flex-col gap-8 rounded-lg border bg-card p-6" onSubmit={submit}>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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

                        <div className="grid gap-2 md:col-span-2 xl:col-span-2">
                            <Label htmlFor="title">Название</Label>
                            <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Направление</Label>
                            <Select value={data.direction_id || undefined} onValueChange={(value) => setData('direction_id', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Выберите направление" />
                                </SelectTrigger>
                                <SelectContent>
                                    {directions.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.number}. {d.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.direction_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Должность</Label>
                            <Select
                                value={data.responsible_id || NONE}
                                onValueChange={(value) => setData('responsible_id', value === NONE ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Не назначена" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Не назначена</SelectItem>
                                    {responsibles.map((responsible) => (
                                        <SelectItem key={responsible.id} value={String(responsible.id)}>
                                            {responsible.occupant
                                                ? `${responsible.name} — ${responsible.occupant}`
                                                : responsible.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.responsible_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deadline">Срок</Label>
                            <Input
                                id="deadline"
                                type="date"
                                value={data.deadline}
                                onChange={(e) => setData('deadline', e.target.value)}
                            />
                            <InputError message={errors.deadline} />
                        </div>
                    </div>

                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="text-base font-medium">Этапы</h2>
                                <p className="text-sm text-muted-foreground">Необязательно — можно добавить позже в реестре.</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={addStage}>
                                <Plus className="h-4 w-4" />
                                Добавить этап
                            </Button>
                        </div>

                        {data.stages.length === 0 && (
                            <p className="text-sm text-muted-foreground">Этапы пока не добавлены.</p>
                        )}

                        {data.stages.map((stage, index) => (
                            <div key={index} className="grid items-end gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_10rem_6rem_auto]">
                                <div className="grid gap-2">
                                    <Label htmlFor={`stage-title-${index}`}>Название этапа</Label>
                                    <Input
                                        id={`stage-title-${index}`}
                                        value={stage.title}
                                        onChange={(e) => updateStage(index, 'title', e.target.value)}
                                    />
                                    <InputError message={stageError(index, 'title')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`stage-date-${index}`}>Плановая дата</Label>
                                    <Input
                                        id={`stage-date-${index}`}
                                        type="date"
                                        value={stage.planned_date}
                                        onChange={(e) => updateStage(index, 'planned_date', e.target.value)}
                                    />
                                    <InputError message={stageError(index, 'planned_date')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`stage-weight-${index}`}>Вес, %</Label>
                                    <Input
                                        id={`stage-weight-${index}`}
                                        type="number"
                                        min={1}
                                        max={95}
                                        value={stage.weight}
                                        onChange={(e) => updateStage(index, 'weight', e.target.value)}
                                    />
                                    <p className="text-muted-foreground text-xs">Максимум 95%. 100% ставит администратор после приёмки.</p>
                                    <InputError message={stageError(index, 'weight')} />
                                </div>
                                <Button type="button" variant="ghost" size="icon" onClick={() => removeStage(index)} aria-label="Удалить этап">
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
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
