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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public const RISK_WINDOW_DAYS = 15;

    public const HIGH_RISK_DAYS = 7;

    protected $fillable = [
        'number',
        'direction_id',
        'title',
        'responsible_id',
        'deadline',
        'interim_monitoring_text',
        'completion_form',
        'reviewed_by',
        'risk_level',
        'percent',
        'proctor_comment',
        'contact_email',
        'stages_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'risk_level' => RiskLevel::class,
            'percent' => 'integer',
            'stages_confirmed_at' => 'datetime',
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

    public function stagesConfirmed(): bool
    {
        return $this->stages_confirmed_at !== null;
    }

    /**
     * Последовательное заполнение (task-020,
     * [[Заполнение и утверждение#Последовательное заполнение этапов]]) — первый по
     * порядку этап, у которого ещё нет ни одного *утверждённого* отчёта. `null`,
     * если этапов нет вообще или все уже утверждены (мероприятие пройдено целиком).
     * Не персистится — вычисляется при каждом обращении, чтобы утверждение
     * администратором любого этапа автоматически «открывало» следующий без отдельного
     * шага синхронизации.
     */
    public function currentStage(): ?MeasureStage
    {
        return $this->stages->first(
            fn (MeasureStage $stage) => ! $stage->periodUpdates()->where('review_state', ReviewState::Approved)->exists()
        );
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
     * Статус для отображения. «Выполнено» — только при 100%. Просрочка и риск
     * считаются по дедлайну и плановой дате текущего этапа: риск с 15 дней,
     * высокий с 7, просрочка — если дата уже прошла. Исполнитель задаёт только
     * «не начато» / «в работе».
     */
    public function currentStatus(): MeasureStatus
    {
        if ($this->percent === 100) {
            return MeasureStatus::Done;
        }

        $days = $this->daysUntilControl();

        if ($days !== null && $days < 0) {
            return MeasureStatus::Overdue;
        }

        if ($days !== null && $days <= self::RISK_WINDOW_DAYS) {
            return MeasureStatus::AtRisk;
        }

        $status = $this->latestPeriodState?->status ?? MeasureStatus::NotStarted;

        return in_array($status, [MeasureStatus::NotStarted, MeasureStatus::InProgress], true)
            ? $status
            : MeasureStatus::NotStarted;
    }

    /**
     * Вычисленный риск: ≤ 7 дней — высокий, ≤ 15 — средний, просрочка — высокий.
     */
    public function currentRiskLevel(): ?RiskLevel
    {
        if ($this->percent === 100) {
            return null;
        }

        $days = $this->daysUntilControl();

        if ($days === null) {
            return null;
        }

        if ($days < 0 || $days <= self::HIGH_RISK_DAYS) {
            return RiskLevel::High;
        }

        if ($days <= self::RISK_WINDOW_DAYS) {
            return RiskLevel::Medium;
        }

        return null;
    }

    /**
     * Дней до ближайшей даты контроля (дедлайн или плановая дата текущего этапа).
     * Отрицательное — дата уже прошла.
     */
    public function daysUntilControl(): ?int
    {
        $today = now()->startOfDay();

        $offsets = $this->controlDates()
            ->map(fn (Carbon $date) => (int) $today->diffInDays($date->copy()->startOfDay(), false));

        if ($offsets->isEmpty()) {
            return null;
        }

        $past = $offsets->filter(fn (int $days) => $days < 0);
        if ($past->isNotEmpty()) {
            return $past->max();
        }

        return $offsets->min();
    }

    /**
     * @return Collection<int, Carbon>
     */
    public function controlDates(): Collection
    {
        return collect([$this->deadline, $this->currentStage()?->planned_date])
            ->filter()
            ->values();
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
     * Общий `%` мероприятия — процент последнего утверждённого этапа (тот, что
     * написан на этапе и который админ подтвердил). Взвешенной суммы нет: два
     * варианта `%` не ведём. 100% появляется только через «Принять работу».
     */
    public function recalculatePercent(): int
    {
        $lastApproved = StagePeriodUpdate::query()
            ->whereIn('measure_stage_id', $this->stages()->pluck('id'))
            ->where('review_state', ReviewState::Approved)
            ->whereNotNull('approved_percent')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();

        $percent = $lastApproved->approved_percent ?? 0;
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
