<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'title', 'description',
        'priority', 'status', 'due_date', 'estimated_min', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }

    public function scopeOwnedBy($query, int $userId) { return $query->where('user_id', $userId); }
}
