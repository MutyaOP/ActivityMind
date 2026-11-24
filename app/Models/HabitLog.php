<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitLog extends Model
{
    protected $fillable = ['habit_id', 'log_date', 'is_done'];

    protected $casts = [
        'log_date' => 'date',
        'is_done' => 'boolean',
    ];

    public function habit(): BelongsTo { return $this->belongsTo(Habit::class); }
}
