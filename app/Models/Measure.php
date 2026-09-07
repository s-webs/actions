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
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * [[Функциональные требования#4.11 Аудит и история изменений]] — по каждому изменению
 * фактических полей кто/когда/старое/новое значение (`owen-it/laravel-auditing`).
 */
class Measure extends Model implements AuditableContract
{
    /** @use HasFactory<MeasureFactory> */
    use Auditable, HasFactory, Notifiable;

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
     * Статус для отображения: последний зафиксированный факт периода, либо «не начато»
     * по умолчанию — плюс живая автопросрочка ([[Бизнес-правила#Правило 1 · Автопросрочка]]):
     * до появления ежедневной задачи планировщика (task-010) правило применяется здесь
     * же, при каждом чтении статуса, а не персистится. «Выполнено» просрочку не
     * перекрывает — при появлении task-010 персистентная пометка станет источником
     * истины, а этот метод продолжит работать как безопасный дубль на случай, если
     * задача не успела отработать день в день.
     */
    public function currentStatus(): MeasureStatus
    {
        $status = $this->latestPeriodState?->status ?? MeasureStatus::NotStarted;

        if ($status !== MeasureStatus::Done && $this->deadline?->isPast()) {
            return MeasureStatus::Overdue;
        }

        return $status;
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

    /**
     * Адресат уведомлений по мероприятию — [[Функциональные требования#4.10 Уведомления и планировщик]].
     * `contact_email` не заполняется автоматически нигде (не в xlsx, не в UI) — открытый
     * вопрос заказчику ([[Техническое задание#11. Допущения и открытые вопросы]]); пока
     * пусто, уведомление тихо не отправляется (не роняет рассылку остальным).
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->contact_email;
    }
}
