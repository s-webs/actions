<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** [[Функциональные требования#4.11 Аудит и история изменений]] — task-019. */
class Unit extends Model implements AuditableContract
{
    /** @use HasFactory<UnitFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
    ];

    public function responsibles(): HasMany
    {
        return $this->hasMany(Responsible::class);
    }

    public function coExecutors(): HasMany
    {
        return $this->hasMany(MeasureCoExecutor::class);
    }
}
