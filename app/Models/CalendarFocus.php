<?php

namespace App\Models;

use Database\Factories\CalendarFocusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarFocus extends Model
{
    /** @use HasFactory<CalendarFocusFactory> */
    use HasFactory;

    protected $table = 'calendar_focuses';

    protected $fillable = [
        'month',
        'focus_text',
        'review_body',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }
}
