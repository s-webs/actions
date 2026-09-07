<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function responsibles(): HasMany
    {
        return $this->hasMany(Responsible::class);
    }

    public function coExecutors(): HasMany
    {
        return $this->hasMany(MeasureCoExecutor::class);
    }
}
