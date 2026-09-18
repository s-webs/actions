<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Guard `web` — именные учётки: `developer` / `administrator` / `observer` /
 * `responsible`. `developer` проходит любую Policy-проверку без исключений —
 * см. `Gate::before` в `AppServiceProvider`. Исполнители сюда не входят.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function responsibleProfile(): HasOne
    {
        return $this->hasOne(Responsible::class);
    }

    /**
     * Видит все мероприятия, а не только свои. Роль `responsible` без
     * administrator/developer/observer ограничена своим справочником.
     */
    public function canAccessAllMeasures(): bool
    {
        return ! $this->hasRole('responsible')
            || $this->hasAnyRole(['developer', 'administrator', 'observer']);
    }

    public function ownsMeasure(Measure $measure): bool
    {
        $profileId = $this->responsibleProfile?->id;

        return $profileId !== null && (int) $measure->responsible_id === (int) $profileId;
    }
}
