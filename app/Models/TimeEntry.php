<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = [
        'user_id', 'task_id', 'category_id',
        'started_at', 'ended_at', 'duration_sec', 'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function scopeRunning($query) { return $query->whereNull('ended_at'); }
}
