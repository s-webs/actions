<?php

namespace App\Models;

use Database\Factories\MeasureStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** [[Функциональные требования#4.11 Аудит и история изменений]] — task-019. */
class MeasureStage extends Model implements AuditableContract
{
    /** @use HasFactory<MeasureStageFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'measure_id',
        'order',
        'title',
        'planned_date',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'weight' => 'integer',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function periodUpdates(): HasMany
    {
        return $this->hasMany(StagePeriodUpdate::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
