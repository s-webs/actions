<?php

namespace App\Models;

use Database\Factories\MeasureCoExecutorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureCoExecutor extends Model
{
    /** @use HasFactory<MeasureCoExecutorFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'name',
        'unit_id',
    ];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
