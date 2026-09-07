<?php

namespace App\Models;

use Database\Factories\MeasureCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureCredential extends Model
{
    /** @use HasFactory<MeasureCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'measure_id',
        'login',
        'password_hash',
        'rotated_at',
        'rotated_by',
        'expires_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'rotated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function rotatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by');
    }
}
