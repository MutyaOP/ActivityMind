<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StatsService;
use Illuminate\Console\Command;

class RecalculateDailyStats extends Command
{
    protected $signature = 'activitymind:recalc-stats {--days=30 : За сколько последних дней пересчитать} {--user= : ID одного пользователя}';

    protected $description = 'Пересчитывает дневные агрегаты (daily_stats) для всех пользователей или одного';

    public function handle(StatsService $stats): int
    {
        $days = max(1, (int) $this->option('days'));
        $count = 0;

        User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->each(function (User $user) use ($stats, $days, &$count) {
                $tz = $user->timezone ?: config('app.timezone');
                $to = now($tz)->startOfDay();
                $stats->sync($user, $to->copy()->subDays($days - 1), $to);
                $count++;
            });

        $this->info("Пересчитано пользователей: {$count}, дней: {$days}.");

        return self::SUCCESS;
    }
}
