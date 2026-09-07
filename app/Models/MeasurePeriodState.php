<?php

namespace App\Models;

use App\Enums\MeasureStatus;
use Database\Factories\MeasurePeriodStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurePeriodState extends Model
{
    /** @use HasFactory<MeasurePeriodStateFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'period_id',
        'status',
        'risk_text',
        'needs_decision',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeasureStatus::class,
            'needs_decision' => 'boolean',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
