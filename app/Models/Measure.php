<?php

namespace App\Models;

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use Database\Factories\MeasureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Measure extends Model
{
    /** @use HasFactory<MeasureFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'direction_id',
        'title',
        'responsible_id',
        'deadline',
        'interim_monitoring_text',
        'control_date',
        'completion_form',
        'reviewed_by',
        'risk_level',
        'percent',
        'proctor_comment',
        'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'control_date' => 'date',
            'risk_level' => RiskLevel::class,
            'percent' => 'integer',
        ];
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Responsible::class);
    }

    public function coExecutors(): HasMany
    {
        return $this->hasMany(MeasureCoExecutor::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(MeasureStage::class)->orderBy('order');
    }

    public function periodStates(): HasMany
    {
        return $this->hasMany(MeasurePeriodState::class);
    }

    /**
     * Состояние за последний (по номеру периода) отчётный период — то, что показывается
     * как «текущий статус» в реестре ([[Функциональные требования#4.1 Модуль «План» — реестр мероприятий]]).
     * `status` намеренно не хранится на `measures` — это факт периода, см. task-002.
     */
    public function latestPeriodState(): HasOne
    {
        return $this->hasOne(MeasurePeriodState::class)->ofMany('period_id', 'max');
    }

    /**
     * Статус для отображения: последний зафиксированный факт периода, либо
     * «не начато» по умолчанию, если ни один период ещё не заведён/не заполнен.
     */
    public function currentStatus(): MeasureStatus
    {
        return $this->latestPeriodState?->status ?? MeasureStatus::NotStarted;
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(MeasureCredential::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MeasureSession::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Общий `%` = Σ(вес этапа × последний утверждённый `%` этапа) / Σ весов —
     * [[Бизнес-правила#Правило 2б · Общий `%` мероприятия — взвешенная сумма]]. Для
     * каждого этапа берётся его последнее *утверждённое* значение (не последний период
     * вообще — этап в `rework` сохраняет прежний утверждённый `%` до новой проверки).
     * Пересчитывается кодом при каждом решении проректора (task-008), не хранится как
     * SQL-агрегат.
     */
    public function recalculatePercent(): int
    {
        $stages = $this->stages;
        $totalWeight = $stages->sum('weight');

        if ($totalWeight === 0) {
            $this->update(['percent' => 0]);

            return 0;
        }

        $weightedSum = $stages->sum(function (MeasureStage $stage) {
            $lastApproved = $stage->periodUpdates()
                ->where('review_state', ReviewState::Approved)
                ->orderByDesc('approved_at')
                ->first();

            return $stage->weight * ($lastApproved->approved_percent ?? 0);
        });

        $percent = (int) round($weightedSum / $totalWeight);
        $this->update(['percent' => $percent]);

        return $percent;
    }
}
