<?php

namespace App\Models;

use Database\Factories\DirectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** [[Функциональные требования#4.11 Аудит и история изменений]] — task-019. */
class Direction extends Model implements AuditableContract
{
    /** @use HasFactory<DirectionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'number',
        'name',
        'in_summary',
    ];

    protected function casts(): array
    {
        return [
            'in_summary' => 'boolean',
        ];
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }
}
