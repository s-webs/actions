<?php

namespace App\Models;

use Database\Factories\ResponsibleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** [[Функциональные требования#4.11 Аудит и история изменений]] — task-019. */
class Responsible extends Model implements AuditableContract
{
    /** @use HasFactory<ResponsibleFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'unit_id',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }
}
