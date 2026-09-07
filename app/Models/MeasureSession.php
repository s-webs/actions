<?php

namespace App\Models;

use Database\Factories\MeasureSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureSession extends Model
{
    /** @use HasFactory<MeasureSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'session_id',
        'ip',
        'user_agent',
        'started_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
