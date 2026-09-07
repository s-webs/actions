<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Этап 2 — заглушка модели, см. [[Функциональные требования#4.8]].
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'held_at',
        'type',
        'agenda',
        'protocol_url',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'date',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
