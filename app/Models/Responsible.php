<?php

namespace App\Models;

use Database\Factories\ResponsibleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** [[Функциональные требования#4.11 Аудит и история изменений]] — task-019. */
class Responsible extends Model implements AuditableContract
{
    /** @use HasFactory<ResponsibleFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'unit_id',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }

    /** Должность, при занятой — «должность · ФИО». */
    public function labeledName(): string
    {
        $occupant = $this->user?->name;

        return $occupant ? "{$this->name} · {$occupant}" : $this->name;
    }

    /**
     * @param  list<int|string>  $measureIds
     */
    public function syncMeasures(array $measureIds): void
    {
        $ids = collect($measureIds)->map(fn ($id) => (int) $id)->unique()->values();

        Measure::query()
            ->where('responsible_id', $this->id)
            ->whereNotIn('id', $ids->isEmpty() ? [0] : $ids->all())
            ->update(['responsible_id' => null]);

        if ($ids->isNotEmpty()) {
            Measure::query()->whereIn('id', $ids->all())->update(['responsible_id' => $this->id]);
        }
    }
}
