<?php

namespace App\Models;

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
}
