<?php

namespace App\Models;

use App\Enums\ReviewState;
use App\Enums\SubmittedVia;
use Database\Factories\StagePeriodUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagePeriodUpdate extends Model
{
    /** @use HasFactory<StagePeriodUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_stage_id',
        'period_id',
        'done_text',
        'next_step',
        'next_step_date',
        'review_state',
        'approved_percent',
        'approved_by',
        'approved_at',
        'review_comment',
        'submitted_via',
        'submitted_by_name',
        'submitted_session_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'next_step_date' => 'date',
            'review_state' => ReviewState::class,
            'submitted_via' => SubmittedVia::class,
            'approved_percent' => 'integer',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MeasureStage::class, 'measure_stage_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
