<?php

namespace App\Models;

use Database\Factories\MeasureCredentialFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guard `measure`: неименной вход по логину/паролю мероприятия
 * ([[Роли и права#Доступ к мероприятию (неименной)]]). Один комплект на мероприятие —
 * аутентифицированный "пользователь" этого guard'а фактически представляет мероприятие,
 * а не человека; см. relation measure().
 */
class MeasureCredential extends Model implements AuthenticatableContract
{
    /** @use HasFactory<MeasureCredentialFactory> */
    use Authenticatable, HasFactory;

    protected $fillable = [
        'measure_id',
        'login',
        'password_hash',
        'rotated_at',
        'rotated_by',
        'expires_at',
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
     * Логин мероприятия — не email/username в общей таблице, а колонка `login`.
     */
    public function getAuthIdentifierName(): string
    {
        return 'login';
    }

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
