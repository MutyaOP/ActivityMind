<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Что человек делал за компьютером в конкретный день — по логам Desktop Agent:
 * время по программам и категориям, лента дня и (по запросу) вкладки браузера от 5 минут.
 */
class ActivityDayService
{
    /** Понятные названия для частых программ (ключ — имя процесса в нижнем регистре, без .exe). */
    private const APP_NAMES = [
        'chrome' => 'Google Chrome', 'msedge' => 'Microsoft Edge', 'firefox' => 'Firefox', 'opera' => 'Opera',
        'browser' => 'Яндекс Браузер', 'code' => 'VS Code', 'phpstorm64' => 'PhpStorm', 'pycharm64' => 'PyCharm',
        'windowsterminal' => 'Терминал', 'cmd' => 'Командная строка', 'powershell' => 'PowerShell',
        'winword' => 'Word', 'excel' => 'Excel', 'powerpnt' => 'PowerPoint', 'outlook' => 'Outlook',
        'teams' => 'Teams', 'ms-teams' => 'Teams', 'telegram' => 'Telegram', 'discord' => 'Discord',
        'steam' => 'Steam', 'spotify' => 'Spotify', 'obsidian' => 'Obsidian', 'anki' => 'Anki',
        'explorer' => 'Проводник', 'notepad' => 'Блокнот', 'notepad++' => 'Notepad++', 'zoom' => 'Zoom',
    ];

    private const NO_CATEGORY_COLOR = '#64748b';

    public function __construct(private readonly AppCategorizer $categorizer) {}

    public static function appKey(string $app): string
    {
        return preg_replace('/\.exe$/i', '', mb_strtolower(trim($app)));
    }

    public static function appLabel(string $app): string
    {
        $key = self::appKey($app);

        return self::APP_NAMES[$key] ?? preg_replace('/\.exe$/i', '', trim($app));
    }

    public function day(User $user, CarbonImmutable $date, bool $withTabs = false): array
    {
        $tz = $user->timezone ?: config('app.timezone');
        $start = $date->setTimezone($tz)->startOfDay();

        $logs = ActivityLog::with('category:id,name,color,type')
            ->where('user_id', $user->id)
            ->where('started_at', '>=', $start->utc())
            ->where('started_at', '<', $start->addDay()->utc())
            ->orderBy('started_at')
            ->get();

        $active = $logs->where('is_idle', false)->values();
        $activeSec = (int) $active->sum('duration_sec');

        return [
            'active_sec'     => $activeSec,
            'active_label'   => AnalyticsService::humanDuration($activeSec),
            'idle_label'     => AnalyticsService::humanDuration((int) $logs->where('is_idle', true)->sum('duration_sec')),
            'productive_pct' => $activeSec ? (int) round($this->sumByType($active, ['work', 'study']) / $activeSec * 100) : null,
            'rest_pct'       => $activeSec ? (int) round($this->sumByType($active, ['rest']) / $activeSec * 100) : null,
            'switches'       => $this->switches($active),
            'first_at'       => $active->first()?->started_at->setTimezone($tz)->format('H:i'),
            'last_at'        => $active->last()?->started_at->copy()->addSeconds($active->last()->duration_sec)->setTimezone($tz)->format('H:i'),
            'apps'           => $this->apps($active, $activeSec),
            'categories'     => $this->categories($active, $activeSec),
            'tabs'           => $withTabs ? $this->tabs($active) : [],
            'timeline'       => $this->timeline($active, $tz),
        ];
    }

    /**
     * Активное время по дням за последние $days дней (для списка «Другие дни»).
     *
     * @return array<string,int> дата → секунды
     */
    public function recentDays(User $user, int $days = 14): array
    {
        $tz = $user->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();

        $totals = ActivityLog::where('user_id', $user->id)
            ->where('is_idle', false)
            ->where('started_at', '>=', $today->subDays($days - 1)->utc())
            ->get(['started_at', 'duration_sec'])
            ->groupBy(fn (ActivityLog $a) => $a->started_at->setTimezone($tz)->toDateString())
            ->map(fn (Collection $g) => (int) $g->sum('duration_sec'));

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $today->subDays($i)->toDateString();
            $out[$d] = $totals[$d] ?? 0;
        }

        return $out;
    }

    // ------------------------------------------------------------------

    private function sumByType(Collection $logs, array $types): int
    {
        return (int) $logs->filter(fn (ActivityLog $l) => in_array($l->category?->type, $types, true))->sum('duration_sec');
    }

    private function switches(Collection $logs): int
    {
        $count = 0;
        $prev = null;
        foreach ($logs as $log) {
            $key = self::appKey($log->app_name);
            if ($prev !== null && $key !== $prev) {
                $count++;
            }
            $prev = $key;
        }

        return $count;
    }

    private function apps(Collection $logs, int $total): array
    {
        return $logs->groupBy(fn (ActivityLog $l) => self::appKey($l->app_name))
            ->map(function (Collection $group, string $key) use ($total) {
                $sec = (int) $group->sum('duration_sec');
                // категория программы — та, в которой она провела больше всего времени
                $main = $group->groupBy('category_id')->sortByDesc(fn (Collection $g) => $g->sum('duration_sec'))->first()->first();

                return [
                    'name'       => self::appLabel($group->first()->app_name),
                    'category'   => $main->category?->name ?? 'Без категории',
                    'color'      => $main->category?->color ?? self::NO_CATEGORY_COLOR,
                    'sec'        => $sec,
                    'label'      => AnalyticsService::humanDuration($sec),
                    'share'      => $total ? round($sec / $total * 100, 1) : 0,
                    'sessions'   => $group->count(),
                    'is_browser' => $this->categorizer->isBrowser($key),
                ];
            })
            ->sortByDesc('sec')
            ->values()
            ->all();
    }

    private function categories(Collection $logs, int $total): array
    {
        return $logs->groupBy(fn (ActivityLog $l) => $l->category_id ?? 0)
            ->map(function (Collection $group) use ($total) {
                $sec = (int) $group->sum('duration_sec');
                $cat = $group->first()->category;

                return [
                    'name'  => $cat?->name ?? 'Без категории',
                    'color' => $cat?->color ?? self::NO_CATEGORY_COLOR,
                    'sec'   => $sec,
                    'label' => AnalyticsService::humanDuration($sec),
                    'share' => $total ? round($sec / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('sec')
            ->values()
            ->all();
    }

    /** Вкладки браузера, на которых за день провели в сумме не меньше 5 минут. */
    private function tabs(Collection $logs): array
    {
        return $logs->filter(fn (ActivityLog $l) => $l->window_title !== null && $this->categorizer->isBrowser($l->app_name))
            ->groupBy(fn (ActivityLog $l) => mb_strtolower($l->window_title))
            ->map(function (Collection $group) {
                $sec = (int) $group->sum('duration_sec');
                $first = $group->first();

                return [
                    'title'    => $first->window_title,
                    'browser'  => self::appLabel($first->app_name),
                    'category' => $first->category?->name ?? 'Прочее',
                    'color'    => $first->category?->color ?? self::NO_CATEGORY_COLOR,
                    'sec'      => $sec,
                    'label'    => AnalyticsService::humanDuration($sec),
                ];
            })
            ->filter(fn (array $tab) => $tab['sec'] >= AppCategorizer::MIN_TAB_SEC)
            ->sortByDesc('sec')
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * Лента дня: отрезки активности, окрашенные по категории. Соседние куски одной категории
     * с разрывом меньше 2 минут склеиваются, чтобы лента читалась.
     */
    private function timeline(Collection $logs, string $tz): array
    {
        if ($logs->isEmpty()) {
            return ['from' => 0, 'to' => 0, 'segments' => []];
        }

        $merged = [];
        foreach ($logs as $log) {
            $s = $log->started_at->setTimezone($tz);
            $from = $s->hour * 60 + $s->minute + $s->second / 60;
            $to = min(24 * 60, $from + $log->duration_sec / 60);
            $cat = $log->category;
            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['cat'] === ($cat?->id ?? 0) && $from - $merged[$last]['to'] < 2) {
                $merged[$last]['to'] = max($merged[$last]['to'], $to);
                $merged[$last]['apps'][self::appLabel($log->app_name)] = true;
                continue;
            }
            $merged[] = [
                'cat' => $cat?->id ?? 0, 'name' => $cat?->name ?? 'Без категории',
                'color' => $cat?->color ?? self::NO_CATEGORY_COLOR,
                'from' => $from, 'to' => $to, 'apps' => [self::appLabel($log->app_name) => true],
            ];
        }

        $fromHour = (int) floor($merged[0]['from'] / 60);
        $toHour = (int) min(24, ceil(max(array_column($merged, 'to')) / 60));
        $toHour = max($toHour, $fromHour + 1);
        $span = ($toHour - $fromHour) * 60;

        $segments = array_map(fn (array $m) => [
            'left'  => round(($m['from'] - $fromHour * 60) / $span * 100, 3),
            'width' => round(max(0.15, ($m['to'] - $m['from']) / $span * 100), 3),
            'color' => $m['color'],
            'hint'  => sprintf('%s–%s · %s · %s', $this->hm($m['from']), $this->hm($m['to']), $m['name'], implode(', ', array_keys($m['apps']))),
        ], $merged);

        return ['from' => $fromHour, 'to' => $toHour, 'segments' => $segments];
    }

    private function hm(float $minutes): string
    {
        $m = (int) round($minutes);

        return sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
    }
}
