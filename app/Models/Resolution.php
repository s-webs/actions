<?php

namespace App\Models;

use Database\Factories\ResolutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resolution extends Model
{
    /** @use HasFactory<ResolutionFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'measure_stage_id',
        'author_id',
        'body',
    ];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MeasureStage::class, 'measure_stage_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
