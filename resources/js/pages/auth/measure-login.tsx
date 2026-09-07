import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface MeasureLoginForm {
    login: string;
    password: string;
}

interface MeasureLoginProps {
    status?: string;
}

export default function MeasureLogin({ status }: MeasureLoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<MeasureLoginForm>({
        login: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('measure.login.store'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Вход в мероприятие" description="Логин и пароль выданы координатором или проректором">
            <Head title="Вход в мероприятие" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="login">Логин мероприятия</Label>
                        <Input
                            id="login"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            placeholder="M-01"
                            value={data.login}
                            onChange={(e) => setData('login', e.target.value)}
                        />
                        <InputError message={errors.login} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Пароль</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <Button type="submit" className="mt-4 w-full" tabIndex={3} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Войти
                    </Button>
                </div>
            </form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
