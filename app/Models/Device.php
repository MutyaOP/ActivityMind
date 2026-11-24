<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $fillable = ['user_id', 'name', 'platform', 'last_sync_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'is_paused' => 'boolean',
        'current_since' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Выпускает новый токен (старый перестаёт работать). Открытый токен возвращается один раз
     * и в БД не сохраняется — только его хэш.
     */
    public function rotateToken(): string
    {
        $plain = 'amd_'.Str::random(40);
        $this->forceFill(['token_hash' => self::hashToken($plain)])->save();

        return $plain;
    }

    /** Программа, открытая сейчас, если агент на связи и не на паузе. */
    public function currentApp(): ?string
    {
        return $this->isOnline(5) && ! $this->is_paused ? $this->current_app : null;
    }

    public function isOnline(int $minutes = 10): bool
    {
        return $this->last_sync_at !== null && $this->last_sync_at->gte(now()->subMinutes($minutes));
    }
}
