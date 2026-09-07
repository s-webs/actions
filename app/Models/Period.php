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
     * Открытый период текущего календарного месяца — заводится по требованию (пока нет
     * планировщика task-018, который будет открывать периоды заранее). Ищем через
     * `whereDate`, а не `firstOrCreate(['month' => ...])` — `date`-каст хранит колонку
     * как полную дату-время, и обычный `where('month', 'Y-m-d')` не находит совпадение
     * (та же ловушка, что в `CalendarFocusSeeder` — task-004).
     */
    public static function current(): self
    {
        $month = now()->startOfMonth()->toDateString();

        return static::query()->whereDate('month', $month)->first()
            ?? static::create(['month' => $month, 'state' => PeriodState::Open]);
    }
}
