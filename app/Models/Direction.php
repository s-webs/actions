<?php

namespace App\Models;

use Database\Factories\DirectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    /** @use HasFactory<DirectionFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'in_summary',
    ];

    protected function casts(): array
    {
        return [
            'in_summary' => 'boolean',
        ];
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }
}
