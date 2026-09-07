<?php

namespace App\Models;

use App\Enums\PeriodState;
use Database\Factories\PeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    /** @use HasFactory<PeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'month',
        'state',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'state' => PeriodState::class,
            'closed_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function measureStates(): HasMany
    {
        return $this->hasMany(MeasurePeriodState::class);
    }

    public function stageUpdates(): HasMany
    {
        return $this->hasMany(StagePeriodUpdate::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    /**
     * Открытый период, в который сейчас идёт работа — не обязательно период текущего
     * календарного месяца. [[Бизнес-правила#Правило 5 · Блокировка закрытого периода]]:
     * после закрытия периода координатором (обычно 25 числа, до конца календарного
     * месяца) правки по учётным данным мероприятия «вносятся уже в следующий период» —
     * то есть остаток месяца до 1-го числа работа идёт уже в периоде *следующего*
     * месяца, хотя календарно текущий месяц ещё не закончился. Поэтому: берём самый
     * поздний период; если он закрыт — открываем/заводим период следующего месяца;
     * если периодов ещё нет вообще — заводим текущий календарный месяц. Возвращаемый
     * период гарантированно открыт — рабочее место мероприятия (task-007) поэтому
     * никогда не пишет в закрытый период, без отдельной проверки на его стороне.
     *
     * Ищем через `whereDate`/сравнение объектов, а не `firstOrCreate(['month' => ...])`
     * — `date`-каст хранит колонку как полную дату-время, и обычный
     * `where('month', 'Y-m-d')` не находит совпадение (та же ловушка, что в
     * `CalendarFocusSeeder` — task-004).
     */
    public static function current(): self
    {
        $latest = static::query()->orderByDesc('month')->first();

        if (! $latest) {
            return static::create(['month' => now()->startOfMonth()->toDateString(), 'state' => PeriodState::Open]);
        }

        if ($latest->state !== PeriodState::Closed) {
            return $latest;
        }

        $nextMonth = $latest->month->copy()->addMonthNoOverflow()->startOfMonth()->toDateString();

        return static::query()->whereDate('month', $nextMonth)->first()
            ?? static::create(['month' => $nextMonth, 'state' => PeriodState::Open]);
    }
}
