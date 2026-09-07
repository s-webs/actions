<?php

namespace App\Models;

use Database\Factories\MeasureCredentialFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Guard `measure`: неименной вход по логину/паролю мероприятия
 * ([[Роли и права#Доступ к мероприятию (неименной)]]). Один комплект на мероприятие —
 * аутентифицированный "пользователь" этого guard'а фактически представляет мероприятие,
 * а не человека; см. relation measure().
 */
class MeasureCredential extends Model implements AuditableContract, AuthenticatableContract
{
    /** @use HasFactory<MeasureCredentialFactory> */
    use Auditable, Authenticatable, HasFactory;

    protected $fillable = [
        'measure_id',
        'login',
        'password_hash',
        'rotated_at',
        'rotated_by',
        'expires_at',
    ];

    /** Хеш пароля в аудит не пишем даже как "изменившееся значение" — [[Функциональные требования#4.11]]. */
    protected $auditExclude = [
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'rotated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function rotatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by');
    }

    /**
     * `getAuthIdentifierName()` НЕ переопределён — остаётся стандартный `id` (первичный
     * ключ). Найденный по ходу task-019 нюанс: этот идентификатор используется Laravel
     * только для перепривязки сессии между запросами (`retrieveById` ищет по этой же
     * колонке — самосогласованно, что бы в ней ни было), а вовсе не для самого входа по
     * логину/паролю — тот берёт поля прямо из массива credentials в `Auth::attempt()`,
     * минуя `getAuthIdentifierName()`. Переопределение на `login` было лишним усложнением
     * (task-003) и вдобавок ломало атрибуцию аудита ([[owen-it/laravel-auditing]] берёт
     * `getAuthIdentifier()` как значение для `user_id`, ожидая первичный ключ, а не
     * произвольную строку) — исправлено здесь.
     */

    /**
     * Пароль мероприятия хранится в `password_hash`, а не в стандартной `password`.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * "Запомнить меня" не предусмотрено для учётных данных мероприятия
     * (см. ротацию пароля — [[Роли и права#Доступ к мероприятию (неименной)]]).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Не поддерживается.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
