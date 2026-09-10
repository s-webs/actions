<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory;

    protected $table = 'evidences';

    protected $fillable = [
        'measure_id',
        'measure_stage_id',
        'period_id',
        'type',
        'path_or_url',
        'title',
        'form',
        'uploaded_via',
        'uploaded_by_name',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MeasureStage::class, 'measure_stage_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Ссылка для открытия: внешний URL как есть, файл — через публичный диск (`/storage/...`).
     */
    public function publicUrl(): string
    {
        if ($this->type === EvidenceType::Link) {
            return $this->path_or_url;
        }

        return Storage::disk('public')->url($this->path_or_url);
    }
}
