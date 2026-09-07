<?php

namespace App\Models;

use App\Enums\MeasureStatus;
use App\Enums\RiskLevel;
use Database\Factories\SnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snapshot extends Model
{
    /** @use HasFactory<SnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'period_id',
        'percent',
        'status',
        'risk_level',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'status' => MeasureStatus::class,
            'risk_level' => RiskLevel::class,
            'data' => 'array',
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
