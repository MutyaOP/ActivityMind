<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;

class HabitService
{
    /**
     * Отметить / снять отметку выполнения за дату (Y-m-d).
     * Возвращает true, если после вызова привычка отмечена.
     */
    public function toggle(Habit $habit, string $date): bool
    {
        $log = HabitLog::where('habit_id', $habit->id)->whereDate('log_date', $date)->first();

        if ($log) {
            $log->delete();
            return false;
        }

        HabitLog::create(['habit_id' => $habit->id, 'log_date' => $date, 'is_done' => true]);

        return true;
    }

    /**
     * Статистика привычки: серии, процент выполнения, последние 7 дней.
     */
    public function stats(Habit $habit, string $tz): array
    {
        $today = Carbon::now($tz)->startOfDay();

        $dates = HabitLog::where('habit_id', $habit->id)
            ->where('is_done', true)
            ->pluck('log_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->values();

        $set = array_flip($dates->all());

        [$current, $best] = $habit->frequency === 'weekly'
            ? $this->weeklyStreaks($dates, $habit->target_per_period, $today)
            : $this->dailyStreaks($dates, $set, $today);

        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $key = $day->toDateString();
            $week[] = [
                'date'     => $key,
                'label'    => $day->copy()->locale('ru')->isoFormat('dd'),
                'day'      => $day->day,
                'done'     => isset($set[$key]),
                'is_today' => $i === 0,
            ];
        }

        return [
            'current_streak' => $current,
            'best_streak'    => $best,
            'unit'           => $habit->frequency === 'weekly' ? 'нед.' : 'дн.',
            'rate'           => $this->rate($habit, $dates, $today, $tz),
            'week'           => $week,
        ];
    }

    /** @return array{0:int,1:int} [текущая серия, лучшая серия] в днях */
    private function dailyStreaks($dates, array $set, Carbon $today): array
    {
        // Текущая: если сегодня ещё не отмечено, серия считается от вчера
        $cursor = $today->copy();
        if (! isset($set[$cursor->toDateString()])) {
            $cursor->subDay();
        }
        $current = 0;
        while (isset($set[$cursor->toDateString()])) {
            $current++;
            $cursor->subDay();
        }

        // Лучшая
        $best = 0;
        $run = 0;
        $prev = null;
        foreach ($dates->sort()->values() as $d) {
            $run = ($prev !== null && Carbon::parse($prev)->addDay()->toDateString() === $d) ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $d;
        }

        return [$current, $best];
    }

    /** @return array{0:int,1:int} [текущая серия, лучшая серия] в неделях (пн–вс) */
    private function weeklyStreaks($dates, int $target, Carbon $today): array
    {
        $target = max(1, $target);

        $counts = $dates
            ->groupBy(fn ($d) => Carbon::parse($d)->startOfWeek()->toDateString())
            ->map(fn ($g) => $g->count());

        $met = fn (string $weekKey) => ($counts[$weekKey] ?? 0) >= $target;

        $cursor = $today->copy()->startOfWeek();
        if (! $met($cursor->toDateString())) {
            $cursor->subWeek();
        }
        $current = 0;
        while ($met($cursor->toDateString())) {
            $current++;
            $cursor->subWeek();
        }

        $best = 0;
        $run = 0;
        $prev = null;
        foreach ($counts->keys()->sort()->values() as $weekKey) {
            if (! $met($weekKey)) {
                $run = 0;
                $prev = null;
                continue;
            }
            $run = ($prev !== null && Carbon::parse($prev)->addWeek()->toDateString() === $weekKey) ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $weekKey;
        }

        return [$current, $best];
    }

    /** Процент выполнения за последние 30 дней (для weekly — за 28 дней). */
    private function rate(Habit $habit, $dates, Carbon $today, string $tz): int
    {
        $created = $habit->created_at
            ? $habit->created_at->copy()->setTimezone($tz)->startOfDay()
            : $today->copy();
        $daysSinceCreated = max(0, (int) $created->diffInDays($today, false));

        if ($habit->frequency === 'weekly') {
            $window = min(28, $daysSinceCreated + 1);
            $from = $today->copy()->subDays($window - 1)->toDateString();
            $done = $dates->filter(fn ($d) => $d >= $from)->count();
            $expected = max(1, $habit->target_per_period) * max(1, $window / 7);

            return (int) min(100, round($done / $expected * 100));
        }

        $window = min(30, $daysSinceCreated + 1);
        $from = $today->copy()->subDays($window - 1)->toDateString();
        $done = $dates->filter(fn ($d) => $d >= $from)->count();

        return (int) min(100, round($done / max(1, $window) * 100));
    }
}
