<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Этап 2 — заглушка модели, см. [[Функциональные требования#4.8]].
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'measure_id',
        'body',
        'assignee',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => AssignmentStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
