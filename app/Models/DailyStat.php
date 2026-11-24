<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStat extends Model
{
    protected $fillable = [
        'user_id', 'stat_date', 'focus_sec', 'tracked_sec',
        'tasks_done', 'habits_done', 'habits_total', 'productivity_score',
    ];

    protected $casts = ['stat_date' => 'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
