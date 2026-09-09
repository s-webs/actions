<?php

namespace App\Services;

use App\Models\Measure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Генерация и ротация учётных данных мероприятия —
 * [[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]]. Пароль
 * хранится дважды: `password_hash` (bcrypt, необратимо) — источник для самой
 * аутентификации (`Auth::attempt`), и `password` (обратимо зашифрован через `encrypted`
 * cast на модели) — чтобы администратор мог посмотреть/скопировать пароль в любой
 * момент, а не только один раз при генерации (решение заказчика, см.
 * `credentials/index.tsx`). `login_token` — случайная строка для прямой ссылки входа
 * (обходит форму логин+пароль); ротируется вместе с паролем, чтобы «Перегенерировать»
 * гарантированно отзывал и старую ссылку тоже.
 */
class MeasureCredentialGenerator
{
    /**
     * @return array{login: string, password: string}
     */
    public function generate(Measure $measure, ?User $rotatedBy = null): array
    {
        $password = $this->readablePassword();
        $login = sprintf('M-%02d', $measure->number);

        $measure->credential()->updateOrCreate([], [
            'login' => $login,
            'password_hash' => Hash::make($password),
            'password' => $password,
            'login_token' => Str::random(40),
            'rotated_at' => now(),
            'rotated_by' => $rotatedBy?->id,
        ]);

        $this->terminateSessions($measure);

        return ['login' => $login, 'password' => $password];
    }

    /**
     * Завершает активные сессии мероприятия (принудительно, или как часть ротации
     * пароля). Удаляет только строку из служебной таблицы Laravel-сессий по
     * `session_id` — так браузер на следующем запросе окажется неаутентифицирован;
     * аудиторский журнал `measure_sessions` не трогаем, он должен пережить закрытие
     * сессии ([[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]] —
     * «журнал сессий... вход, IP, устройство, время»).
     */
    public function terminateSessions(Measure $measure): void
    {
        $sessionIds = $measure->sessions()->pluck('session_id');

        if ($sessionIds->isNotEmpty()) {
            DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }
    }

    /**
     * Без визуально спутываемых символов (0/O, 1/l/I).
     */
    private function readablePassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

        return collect(range(1, $length))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }
}
