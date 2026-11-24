<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

/**
 * Собирает данные для AI-модуля: только числа, без имён, email, названий задач и окон.
 *
 *   days  — {дата: метрики} за последние DAYS дней (последний — сегодня, модуль его отбрасывает).
 *           К дневным агрегатам добавлены данные Desktop Agent:
 *             productive_sec — время в программах категорий «работа»/«учёба»,
 *             rest_sec       — время в программах категории «отдых»,
 *             switches       — сколько раз за день сменилась активная программа.
 *   hours — 24 числа: часы фокуса (таймер) по часам суток за тот же период.
 */
class AiPayloadBuilder
{
    /** 8 недель: каждый день недели встречается 8 раз — достаточно для группировок и кластеризации. */
    public const DAYS = 56;

    private const PRODUCTIVE_TYPES = ['work', 'study'];

    public function __construct(private StatsService $stats)
    {
    }

    /** @return array{days: array<string, array<string, int>>, hours: array<int, float>} */
    public function build(User $user): array
    {
        $tz = $user->timezone ?: config('app.timezone');
        $today = now($tz)->startOfDay();
        $from = $today->copy()->subDays(self::DAYS - 1);

        $days = $this->stats->computeRange($user, $from, $today);

        foreach ($this->agentMetrics($user, $from, $today, $tz) as $date => $m) {
            if (isset($days[$date])) {
                $days[$date] += $m;
            }
        }
        foreach ($days as $date => $row) {
            $days[$date] += ['productive_sec' => 0, 'rest_sec' => 0, 'switches' => 0];
        }

        return ['days' => $days, 'hours' => $this->hours($user, $from, $today, $tz)];
    }

    /** @return array<string, array{productive_sec:int, rest_sec:int, switches:int}> */
    private function agentMetrics(User $user, Carbon $from, Carbon $to, string $tz): array
    {
        $logs = ActivityLog::with('category:id,type')
            ->where('user_id', $user->id)
            ->where('is_idle', false)
            ->where('started_at', '>=', $from->copy()->utc())
            ->where('started_at', '<', $to->copy()->addDay()->utc())
            ->orderBy('started_at')
            ->get(['id', 'category_id', 'app_name', 'started_at', 'duration_sec']);

        $out = [];
        $prev = [];
        foreach ($logs as $log) {
            $date = $log->started_at->copy()->setTimezone($tz)->toDateString();
            $out[$date] ??= ['productive_sec' => 0, 'rest_sec' => 0, 'switches' => 0];

            $type = $log->category?->type;
            if (in_array($type, self::PRODUCTIVE_TYPES, true)) {
                $out[$date]['productive_sec'] += $log->duration_sec;
            } elseif ($type === 'rest') {
                $out[$date]['rest_sec'] += $log->duration_sec;
            }

            // Агент режет длинную работу в одной программе на куски — это не переключение
            $app = mb_strtolower($log->app_name);
            if (isset($prev[$date]) && $prev[$date] !== $app) {
                $out[$date]['switches']++;
            }
            $prev[$date] = $app;
        }

        return $out;
    }

    /** Часы фокуса по часам суток; запись, идущая через границу часа, делится между часами. */
    private function hours(User $user, Carbon $from, Carbon $to, string $tz): array
    {
        $hours = array_fill(0, 24, 0.0);

        $entries = TimeEntry::where('user_id', $user->id)
            ->where('started_at', '>=', $from->copy()->utc())
            ->where('started_at', '<', $to->copy()->addDay()->utc())
            ->get();

        foreach ($entries as $e) {
            $start = $e->started_at->copy()->setTimezone($tz);
            $end = $e->ended_at ? $e->ended_at->copy()->setTimezone($tz) : now($tz);

            for ($cur = $start->copy(); $cur->lt($end);) {
                $next = $cur->copy()->startOfHour()->addHour();
                $sliceEnd = $next->lt($end) ? $next : $end;
                $hours[(int) $cur->format('G')] += $cur->diffInSeconds($sliceEnd) / 3600;
                $cur = $sliceEnd;
            }
        }

        return array_map(fn ($h) => round($h, 2), $hours);
    }
}
