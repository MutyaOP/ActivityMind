<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Recommendation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /** Цель фокуса по умолчанию, если пользователь не задал свою (минут в день). */
    public const DEFAULT_GOAL_MIN = 240;

    /**
     * Все данные для главной страницы (dashboard).
     */
    public function dashboard(User $user): array
    {
        $tz = $user->timezone ?: config('app.timezone');
        $now = now($tz);
        $today = $now->copy()->startOfDay();
        $todayKey = $today->toDateString();
        $from = $today->copy()->subDays(6);
        $fromUtc = $from->copy()->utc();
        $goalMin = $user->daily_focus_goal_min ?: self::DEFAULT_GOAL_MIN;

        // --- сырые данные за 7 дней ---------------------------------------
        $entries = TimeEntry::with('category')
            ->where('user_id', $user->id)
            ->where('started_at', '>=', $fromUtc)
            ->get();

        $focusByDay = $entries
            ->groupBy(fn (TimeEntry $e) => $this->dayKey($e->started_at, $tz))
            ->map(fn (Collection $g) => $g->sum(fn (TimeEntry $e) => $this->seconds($e)));

        $tasksByDay = Task::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $fromUtc)
            ->get()
            ->groupBy(fn (Task $t) => $this->dayKey($t->completed_at, $tz))
            ->map(fn (Collection $g) => $g->count());

        $habitsTotal = Habit::where('user_id', $user->id)->where('is_active', true)->count();

        $habitsByDay = HabitLog::whereHas('habit', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->where('is_done', true)
            ->where('log_date', '>=', $from->toDateString())
            ->get()
            ->groupBy(fn (HabitLog $l) => $l->log_date->toDateString())
            ->map(fn (Collection $g) => $g->count());

        // --- по дням ------------------------------------------------------
        $labels = [];
        $focusHours = [];
        $scores = [];
        $todayStats = null;
        $weekFocusSec = 0;

        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();

            $focusSec = (int) ($focusByDay[$key] ?? 0);
            $tasksDone = (int) ($tasksByDay[$key] ?? 0);
            $habitsDone = (int) ($habitsByDay[$key] ?? 0);
            $score = $this->score($focusSec, $goalMin, $tasksDone, $habitsDone, $habitsTotal);

            $labels[] = $day->copy()->locale('ru')->isoFormat('dd D');
            $focusHours[] = round($focusSec / 3600, 2);
            $scores[] = $score;
            $weekFocusSec += $focusSec;

            if ($key === $todayKey) {
                $todayStats = [
                    'focus_sec'    => $focusSec,
                    'focus_label'  => self::humanDuration($focusSec),
                    'goal_min'     => $goalMin,
                    'goal_percent' => (int) min(100, round($focusSec / max(1, $goalMin * 60) * 100)),
                    'tasks_done'   => $tasksDone,
                    'habits_done'  => $habitsDone,
                    'habits_total' => $habitsTotal,
                    'score'        => $score,
                ];
            }
        }

        // --- распределение по категориям за неделю ------------------------
        $catLabels = [];
        $catHours = [];
        $catColors = [];
        foreach ($entries->groupBy(fn (TimeEntry $e) => $e->category_id ?? 0) as $group) {
            $first = $group->first();
            $catLabels[] = $first->category?->name ?? 'Без категории';
            $catColors[] = $first->category?->color ?? '#64748b';
            $catHours[] = round($group->sum(fn (TimeEntry $e) => $this->seconds($e)) / 3600, 2);
        }

        // --- списки -------------------------------------------------------
        $activeTasks = Task::with('category')
            ->where('user_id', $user->id)
            ->where('status', '!=', 'done')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->limit(5)
            ->get()
            ->each(fn (Task $t) => $t->setAttribute(
                'is_overdue',
                $t->due_date !== null && $t->due_date->toDateString() < $todayKey
            ));

        $habits = Habit::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['logs' => fn ($q) => $q->whereDate('log_date', $todayKey)])
            ->get()
            ->each(fn (Habit $h) => $h->setAttribute(
                'done_today',
                $h->logs->contains(fn (HabitLog $l) => $l->is_done)
            ));

        $runningEntry = TimeEntry::with('task')
            ->where('user_id', $user->id)
            ->running()
            ->latest('started_at')
            ->first();

        $recommendations = Recommendation::where('user_id', $user->id)
            ->where('is_read', false)
            ->latest('created_at')
            ->limit(3)
            ->get();

        return [
            'greeting'        => $this->greeting($now),
            'today'           => $todayStats,
            'week'            => [
                'labels'       => $labels,
                'focus'        => $focusHours,
                'scores'       => $scores,
                'focus_label'  => self::humanDuration($weekFocusSec),
                'avg_score'    => (int) round(array_sum($scores) / max(1, count($scores))),
            ],
            'categories'      => ['labels' => $catLabels, 'hours' => $catHours, 'colors' => $catColors],
            'activeTasks'     => $activeTasks,
            'habits'          => $habits,
            'runningEntry'    => $runningEntry,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Оценка продуктивности дня, 0–100.
     * Фокус — главный вклад; задачи и привычки дополняют.
     * Если у пользователя нет привычек, их вес переходит на фокус и задачи.
     */
    public function score(int $focusSec, int $goalMin, int $tasksDone, int $habitsDone, int $habitsTotal): int
    {
        $focus = min(1, $focusSec / max(1, $goalMin * 60));
        $tasks = min(1, $tasksDone / 3);

        if ($habitsTotal > 0) {
            $habits = min(1, $habitsDone / $habitsTotal);
            return (int) round($focus * 60 + $tasks * 20 + $habits * 20);
        }

        return (int) round($focus * 75 + $tasks * 25);
    }

    public static function humanDuration(int $sec): string
    {
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);

        return $h > 0 ? "{$h} ч {$m} мин" : "{$m} мин";
    }

    private function seconds(TimeEntry $e): int
    {
        // Таймер ещё идёт — считаем до текущего момента
        return $e->duration_sec ?? max(0, (int) $e->started_at->diffInSeconds(now()));
    }

    private function dayKey(Carbon $ts, string $tz): string
    {
        return $ts->copy()->setTimezone($tz)->toDateString();
    }

    private function greeting(Carbon $now): string
    {
        return match (true) {
            $now->hour < 5  => 'Доброй ночи',
            $now->hour < 12 => 'Доброе утро',
            $now->hour < 18 => 'Добрый день',
            default         => 'Добрый вечер',
        };
    }
}
