<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'timezone', 'daily_focus_goal_min',
    ];
    // role намеренно не в $fillable: меняется только явно (forceFill) в админке и сидере

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function categories(): HasMany { return $this->hasMany(Category::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }
    public function habits(): HasMany { return $this->hasMany(Habit::class); }
    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function dailyStats(): HasMany { return $this->hasMany(DailyStat::class); }
    public function recommendations(): HasMany { return $this->hasMany(Recommendation::class); }
}
