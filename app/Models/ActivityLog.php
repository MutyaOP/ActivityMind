<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'category_id',
        'app_name', 'window_title', 'started_at', 'duration_sec', 'is_idle',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'is_idle' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
