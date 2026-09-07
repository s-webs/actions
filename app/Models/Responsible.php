<?php

namespace App\Models;

use Database\Factories\ResponsibleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Responsible extends Model
{
    /** @use HasFactory<ResponsibleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'unit_id',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }
}
