<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'text', 'source', 'rule_key', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
