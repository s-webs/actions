import { Head } from '@inertiajs/react';

/**
 * Заглушка целевой страницы после входа по guard `measure`. Полное рабочее место
 * мероприятия (этапы, факты, документы, подача на проверку) — task-007,
 * см. [[Функциональные требования#4.7 Рабочее место мероприятия]].
 */
export default function WorkspacePlaceholder() {
    return (
        <>
            <Head title="Рабочее место мероприятия" />
            <div className="flex min-h-screen items-center justify-center p-6 text-center">
                <div>
                    <h1 className="text-xl font-medium">Рабочее место мероприятия</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Вход выполнен. Экран этапов, фактов и подачи на проверку появится в task-007.
                    </p>
                </div>
            </div>
        </>
    );
}
