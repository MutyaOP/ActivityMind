<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DailyStat;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Аналитика: расчёт и сохранение дневных агрегатов (daily_stats)
 * и подготовка данных для страницы «Аналитика».
 */
class StatsService
{
    private const WEEKDAYS_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    private const WEEKDAYS_FULL = ['понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];

    public function __construct(private AnalyticsService $analytics)
    {
    }

    /**
     * Считает метрики по дням в диапазоне [from; to] (включительно, границы — начало дня в поясе пользователя).
     *
     * @return array<string, array<string, int>> ключ — дата Y-m-d
     */
    public function computeRange(User $user, Carbon $from, Carbon $to): array
    {
        $tz = $this->tz($user);
        $fromUtc = $from->copy()->utc();
        $toUtc = $to->copy()->addDay()->utc(); // правая граница исключается
        $goalMin = $user->daily_focus_goal_min ?: AnalyticsService::DEFAULT_GOAL_MIN;

        $focus = TimeEntry::where('user_id', $user->id)
            ->where('started_at', '>=', $fromUtc)->where('started_at', '<', $toUtc)
            ->get()
            ->groupBy(fn (TimeEntry $e) => $this->dayKey($e->started_at, $tz))
            ->map(fn (Collection $g) => $g->sum(fn (TimeEntry $e) => $this->seconds($e)));

        $tasks = Task::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $fromUtc)->where('completed_at', '<', $toUtc)
            ->get()
            ->groupBy(fn (Task $t) => $this->dayKey($t->completed_at, $tz))
            ->map(fn (Collection $g) => $g->count());

        $habitsTotal = Habit::where('user_id', $user->id)->where('is_active', true)->count();

        $habitsDone = HabitLog::whereHas('habit', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->where('is_done', true)
            ->whereDate('log_date', '>=', $from->toDateString())
            ->whereDate('log_date', '<=', $to->toDateString())
            ->get()
            ->groupBy(fn (HabitLog $l) => $l->log_date->toDateString())
            ->map(fn (Collection $g) => $g->count());

        $tracked = ActivityLog::where('user_id', $user->id)
            ->where('is_idle', false)
            ->where('started_at', '>=', $fromUtc)->where('started_at', '<', $toUtc)
            ->get()
            ->groupBy(fn (ActivityLog $a) => $this->dayKey($a->started_at, $tz))
            ->map(fn (Collection $g) => $g->sum('duration_sec'));

        $rows = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $focusSec = (int) ($focus[$key] ?? 0);
            $tasksDone = (int) ($tasks[$key] ?? 0);
            $done = (int) ($habitsDone[$key] ?? 0);

            $rows[$key] = [
                'focus_sec'    => $focusSec,
                'tracked_sec'  => (int) ($tracked[$key] ?? 0),
                'tasks_done'   => $tasksDone,
                'habits_done'  => $done,
                'habits_total' => $habitsTotal,
                'score'        => $this->analytics->score($focusSec, $goalMin, $tasksDone, $done, $habitsTotal),
            ];
        }

        return $rows;
    }

    /**
     * Пересчитывает и сохраняет daily_stats за диапазон (идемпотентно: upsert по user_id + stat_date).
     */
    public function sync(User $user, Carbon $from, Carbon $to): array
    {
        $rows = $this->computeRange($user, $from, $to);
        $now = now();
        $payload = [];

        foreach ($rows as $date => $r) {
            $payload[] = [
                'user_id'            => $user->id,
                'stat_date'          => $date,
                'focus_sec'          => $r['focus_sec'],
                'tracked_sec'        => $r['tracked_sec'],
                'tasks_done'         => $r['tasks_done'],
                'habits_done'        => $r['habits_done'],
                'habits_total'       => $r['habits_total'],
                'productivity_score' => $r['score'],
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if ($payload) {
            DailyStat::upsert(
                $payload,
                ['user_id', 'stat_date'],
                ['focus_sec', 'tracked_sec', 'tasks_done', 'habits_done', 'habits_total', 'productivity_score', 'updated_at']
            );
        }

        return $rows;
    }

    /**
     * Данные страницы «Аналитика» за последние $days дней (включая сегодня).
     */
    public function analytics(User $user, int $days): array
    {
        $tz = $this->tz($user);
        $today = now($tz)->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $prevFrom = $from->copy()->subDays($days);
        $goalMin = $user->daily_focus_goal_min ?: AnalyticsService::DEFAULT_GOAL_MIN;

        // Один пересчёт охватывает текущий и предыдущий период
        $this->sync($user, $prevFrom, $today);

        $stats = DailyStat::where('user_id', $user->id)
            ->whereBetween('stat_date', [$prevFrom->toDateString(), $today->toDateString()])
            ->get()
            ->keyBy(fn (DailyStat $s) => $s->stat_date->toDateString());

        // --- ряд по дням ---
        $labels = $focus = $scores = $tasks = [];
        $focusTotal = 0;
        $tasksTotal = 0;
        $scoreSum = 0;
        $activeDays = 0;
        $goalDays = 0;
        $bestDay = null;
        $weekdaySum = array_fill(0, 7, 0);
        $weekdayCnt = array_fill(0, 7, 0);
        $habitRates = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $s = $stats[$day->toDateString()] ?? null;

            $f = (int) ($s?->focus_sec ?? 0);
            $labels[] = $day->format('d.m');
            $focus[] = round($f / 3600, 2);
            $scores[] = (int) ($s?->productivity_score ?? 0);
            $tasks[] = (int) ($s?->tasks_done ?? 0);

            $focusTotal += $f;
            $tasksTotal += (int) ($s?->tasks_done ?? 0);
            $scoreSum += (int) ($s?->productivity_score ?? 0);
            if ($f > 0) {
                $activeDays++;
            }
            if ($f >= $goalMin * 60) {
                $goalDays++;
            }
            if ($bestDay === null || $f > $bestDay['sec']) {
                $bestDay = ['sec' => $f, 'label' => $day->format('d.m')];
            }

            $wd = $day->dayOfWeekIso - 1;
            $weekdaySum[$wd] += $f;
            $weekdayCnt[$wd]++;

            if ($s && $s->habits_total > 0) {
                $habitRates[] = $s->habits_done / $s->habits_total * 100;
            }
        }

        // --- предыдущий период для тренда ---
        $prevTotal = 0;
        for ($i = 0; $i < $days; $i++) {
            $prevTotal += (int) ($stats[$prevFrom->copy()->addDays($i)->toDateString()]?->focus_sec ?? 0);
        }
        $trend = $prevTotal > 0 ? (int) round(($focusTotal - $prevTotal) / $prevTotal * 100) : null;

        // --- сессии периода: часы, категории ---
        $entries = TimeEntry::with('category')
            ->where('user_id', $user->id)
            ->where('started_at', '>=', $from->copy()->utc())
            ->where('started_at', '<', $today->copy()->addDay()->utc())
            ->get();

        $hours = array_fill(0, 24, 0.0);
        foreach ($entries as $e) {
            $h = (int) $e->started_at->copy()->setTimezone($tz)->format('G');
            $hours[$h] += $this->seconds($e) / 3600;
        }
        $hours = array_map(fn ($v) => round($v, 2), $hours);

        $catLabels = $catHours = $catColors = [];
        foreach ($entries->groupBy(fn (TimeEntry $e) => $e->category_id ?? 0) as $group) {
            $first = $group->first();
            $catLabels[] = __($first->category?->name ?? 'Без категории');
            $catColors[] = $first->category?->color ?? '#64748b';
            $catHours[] = round($group->sum(fn (TimeEntry $e) => $this->seconds($e)) / 3600, 2);
        }

        // --- приложения (Desktop Agent) ---
        $apps = ActivityLog::where('user_id', $user->id)
            ->where('is_idle', false)
            ->where('started_at', '>=', $from->copy()->utc())
            ->where('started_at', '<', $today->copy()->addDay()->utc())
            ->get()
            ->groupBy('app_name')
            ->map(fn (Collection $g) => round($g->sum('duration_sec') / 3600, 2))
            ->sortDesc()
            ->take(8);

        // --- по дням недели (среднее) ---
        $weekdayAvg = [];
        foreach (range(0, 6) as $wd) {
            $weekdayAvg[] = $weekdayCnt[$wd] ? round($weekdaySum[$wd] / $weekdayCnt[$wd] / 3600, 2) : 0;
        }

        return [
            'days' => $days,
            'summary' => [
                'focus_sec'       => $focusTotal,
                'focus_label'     => AnalyticsService::humanDuration($focusTotal),
                'avg_focus_label' => AnalyticsService::humanDuration($activeDays ? intdiv($focusTotal, $activeDays) : 0),
                'active_days'     => $activeDays,
                'tasks_done'      => $tasksTotal,
                'avg_score'       => (int) round($scoreSum / max(1, $days)),
                'habit_rate'      => $habitRates ? (int) round(array_sum($habitRates) / count($habitRates)) : null,
                'goal_days'       => $goalDays,
                'goal_min'        => $goalMin,
                'trend'           => $trend,
                'best_day'        => $bestDay && $bestDay['sec'] > 0
                    ? ['label' => $bestDay['label'], 'focus_label' => AnalyticsService::humanDuration($bestDay['sec'])]
                    : null,
            ],
            'daily'      => ['labels' => $labels, 'focus' => $focus, 'scores' => $scores, 'tasks' => $tasks],
            'hours'      => $hours,
            'weekdays'   => ['labels' => array_map(fn ($d) => __($d), self::WEEKDAYS_SHORT), 'avg' => $weekdayAvg],
            'categories' => ['labels' => $catLabels, 'hours' => $catHours, 'colors' => $catColors],
            'apps'       => ['labels' => $apps->keys()->values()->all(), 'hours' => $apps->values()->all()],
            'insights'   => $this->insights($focusTotal, $hours, $weekdayAvg, $trend, $goalDays, $days, $goalMin),
        ];
    }

    private function insights(int $focusTotal, array $hours, array $weekdayAvg, ?int $trend, int $goalDays, int $days, int $goalMin): array
    {
        if ($focusTotal === 0) {
            return [__('Пока недостаточно данных: запустите таймер на странице «Таймер», и здесь появятся выводы.')];
        }

        $out = [];

        $bestHour = array_search(max($hours), $hours, true);
        $out[] = __('Чаще всего вы работаете с :from до :to (по времени начала сессий).', ['from' => sprintf('%02d:00', $bestHour), 'to' => sprintf('%02d:00', ($bestHour + 1) % 24)]);

        $bestWd = array_search(max($weekdayAvg), $weekdayAvg, true);
        if (max($weekdayAvg) > 0) {
            $out[] = __('Самый продуктивный день недели — :day: в среднем :time фокуса.', [
                'day' => __(self::WEEKDAYS_FULL[$bestWd]), 'time' => AnalyticsService::humanDuration((int) round($weekdayAvg[$bestWd] * 3600)),
            ]);
        }

        if ($trend !== null) {
            $out[] = $trend >= 0
                ? __('Фокус вырос на :pct% по сравнению с предыдущими :days днями.', ['pct' => $trend, 'days' => $days])
                : __('Фокус снизился на :pct% по сравнению с предыдущими :days днями.', ['pct' => abs($trend), 'days' => $days]);
        }

        $out[] = __('Цель по фокусу (:min мин) выполнена в :n из :days дней.', ['min' => $goalMin, 'n' => $goalDays, 'days' => $days]);

        return $out;
    }

    private function tz(User $user): string
    {
        return $user->timezone ?: config('app.timezone');
    }

    private function seconds(TimeEntry $e): int
    {
        return $e->duration_sec ?? max(0, (int) $e->started_at->diffInSeconds(now()));
    }

    private function dayKey(Carbon $ts, string $tz): string
    {
        return $ts->copy()->setTimezone($tz)->toDateString();
    }
}
