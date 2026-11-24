<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\Recommendation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Генерация рекомендаций по правилам (+ опционально AI-модуль).
 * Каждое правило имеет ключ, приоритет и «паузу» (cooldown): один и тот же совет
 * не создаётся повторно, пока не пройдёт cooldown дней.
 */
class RecommendationService
{
    /** Не больше столько новых советов за один запуск, чтобы не засыпать пользователя. */
    private const MAX_PER_RUN = 4;

    public function __construct(
        private StatsService $stats,
        private HabitService $habits,
        private AiAnalyzerClient $ai,
        private AiPayloadBuilder $aiPayload,
    ) {
    }

    /**
     * Генерирует не чаще раза в $hours часов (для автозапуска на страницах).
     * Ошибки генерации не должны ломать страницу.
     */
    public function generateIfStale(User $user, int $hours = 6): int
    {
        if (! Cache::add("recommendations:generated:{$user->id}", true, now()->addHours($hours))) {
            return 0;
        }

        try {
            return $this->generate($user);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Возвращает число созданных рекомендаций. */
    public function generate(User $user): int
    {
        $ctx = $this->context($user);

        $candidates = array_merge($this->ruleCandidates($ctx), $this->aiCandidates($user, $ctx));
        usort($candidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $created = 0;
        foreach ($candidates as $c) {
            if ($created >= self::MAX_PER_RUN) {
                break;
            }
            if ($this->recentlyCreated($user, $c['rule_key'], $c['cooldown'])) {
                continue;
            }

            Recommendation::create([
                'user_id'  => $user->id,
                'type'     => $c['type'],
                'text'     => $c['text'],
                'source'   => $c['source'] ?? 'rule',
                'rule_key' => $c['rule_key'],
                'is_read'  => false,
            ]);
            $created++;
        }

        return $created;
    }

    // ------------------------------------------------------------------ контекст

    private function context(User $user): array
    {
        $tz = $user->timezone ?: config('app.timezone');
        $today = now($tz)->startOfDay();
        $goalMin = $user->daily_focus_goal_min ?: AnalyticsService::DEFAULT_GOAL_MIN;

        // 14 дней по возрастанию (последний — сегодня); заодно обновляет daily_stats
        $rows = $this->stats->sync($user, $today->copy()->subDays(13), $today);

        $entries = TimeEntry::where('user_id', $user->id)
            ->where('started_at', '>=', $today->copy()->subDays(13)->utc())
            ->get();

        $habits = Habit::where('user_id', $user->id)->where('is_active', true)->get()
            ->map(fn (Habit $h) => ['habit' => $h, 'stats' => $this->habits->stats($h, $tz)]);

        return [
            'tz'          => $tz,
            'today'       => $today,
            'goalMin'     => $goalMin,
            'rows'        => $rows,
            'entries'     => $entries,
            'everTracked' => TimeEntry::where('user_id', $user->id)->exists(),
            'anyTasks'    => Task::where('user_id', $user->id)->exists(),
            'openTasks'   => Task::where('user_id', $user->id)->where('status', '!=', 'done')->get(),
            'habits'      => $habits,
            'anyHabits'   => Habit::where('user_id', $user->id)->exists(),
        ];
    }

    // ------------------------------------------------------------------ правила

    /** @return array<int, array<string, mixed>> */
    private function ruleCandidates(array $ctx): array
    {
        $out = [];
        $add = function (string $key, string $type, int $priority, int $cooldown, string $text) use (&$out) {
            $out[] = ['rule_key' => $key, 'type' => $type, 'priority' => $priority, 'cooldown' => $cooldown, 'text' => $text];
        };

        $rows = $ctx['rows'];
        $goalSec = $ctx['goalMin'] * 60;
        $today = $ctx['today'];
        $tz = $ctx['tz'];

        $completed = array_slice($rows, 0, 13, true);     // без сегодняшнего (неполного) дня
        $last3 = array_slice($completed, -3, 3, true);
        $total14 = array_sum(array_column($rows, 'focus_sec'));
        $focusOf = fn (array $slice) => array_sum(array_column($slice, 'focus_sec'));

        // 0. Новый пользователь
        if (! $ctx['everTracked'] && ! $ctx['anyTasks'] && ! $ctx['anyHabits']) {
            $add('welcome', 'planning', 100, 365,
                'Добро пожаловать! Начните с малого: добавьте одну задачу, запустите таймер на 25 минут и отметьте первую привычку. Через несколько дней здесь появятся персональные советы.');
        }

        // 1. Фокус ниже половины цели три дня подряд
        if (count($last3) === 3 && $total14 > 0
            && collect($last3)->every(fn ($r) => $r['focus_sec'] < $goalSec * 0.5)) {
            $add('low_focus', 'focus', 70, 3,
                'Три дня подряд фокус ниже половины вашей цели. Попробуйте начать с одной сессии по 25 минут (метод Pomodoro) — старт важнее объёма.');
        }

        // 2. Цель выполнена три дня подряд
        if (count($last3) === 3 && $goalSec > 0
            && collect($last3)->every(fn ($r) => $r['focus_sec'] >= $goalSec)) {
            $add('goal_streak', 'focus', 25, 7,
                'Цель по фокусу выполнена три дня подряд — отличный ритм! Не забывайте про отдых, чтобы удержать темп.');
        }

        // 3. Динамика недели к неделе
        $cur7 = $focusOf(array_slice($rows, -7, 7, true));
        $prev7 = $focusOf(array_slice($rows, 0, 7, true));
        if ($prev7 >= 3600) {
            if ($cur7 <= $prev7 * 0.7) {
                $drop = (int) round(($prev7 - $cur7) / $prev7 * 100);
                $add('trend_down', 'focus', 65, 7,
                    "Фокус за последние 7 дней снизился на {$drop}% по сравнению с предыдущей неделей. Выберите одну важную задачу и закрепите на неё время в календаре.");
            } elseif ($cur7 >= $prev7 * 1.25) {
                $up = (int) round(($cur7 - $prev7) / $prev7 * 100);
                $add('trend_up', 'focus', 25, 7,
                    "Фокус за неделю вырос на {$up}% — вы набираете обороты. Зафиксируйте, что помогло, и повторяйте.");
            }
        }

        // 4. Лучшее время и поздняя работа (по часу начала сессий)
        /** @var Collection $entries */
        $entries = $ctx['entries'];
        $hours = array_fill(0, 24, 0);
        foreach ($entries as $e) {
            $h = (int) $e->started_at->copy()->setTimezone($tz)->format('G');
            $hours[$h] += $this->seconds($e);
        }
        $totalSec = array_sum($hours);

        if ($totalSec >= 3 * 3600) {
            $best = array_search(max($hours), $hours, true);
            $add('best_hour', 'focus', 30, 14,
                sprintf('Ваш пик активности — с %02d:00 до %02d:00. Планируйте самые сложные задачи на это время, а рутину оставляйте на остальные часы.', $best, ($best + 1) % 24));
        }

        if ($totalSec >= 5 * 3600) {
            $late = 0;
            foreach ($hours as $h => $sec) {
                if ($h >= 22 || $h < 5) {
                    $late += $sec;
                }
            }
            if ($late / $totalSec >= 0.3) {
                $add('late_work', 'break', 35, 7,
                    'Заметная часть сессий приходится на поздний вечер или ночь. Попробуйте сдвинуть работу раньше — сон напрямую влияет на фокус на следующий день.');
            }
        }

        // 5. Слишком долгие сессии
        $longest = $entries
            ->filter(fn (TimeEntry $e) => $e->ended_at && $e->started_at->gte($today->copy()->subDays(2)->utc()))
            ->map(fn (TimeEntry $e) => $this->seconds($e))
            ->max();
        if ($longest && $longest >= 7200) {
            $add('long_sessions', 'break', 60, 3,
                'Недавно одна из сессий длилась '.AnalyticsService::humanDuration((int) $longest).' без остановки. Делайте перерыв 10–15 минут каждые 60–90 минут — так концентрация держится дольше.');
        }

        // 6. Давно не запускали таймер
        if ($ctx['everTracked'] && ! $entries->contains(fn (TimeEntry $e) => $e->started_at->gte($today->copy()->subDays(2)->utc()))) {
            $add('no_tracking', 'other', 45, 3,
                'Вы не записывали время последние три дня. Запустите таймер на первой же задаче — даже короткие записи делают аналитику точнее.');
        }

        // 7. Задачи
        $overdue = $ctx['openTasks']->filter(fn (Task $t) => $t->due_date && $t->due_date->toDateString() < $today->toDateString())->count();
        if ($overdue > 0) {
            $add('overdue_tasks', 'planning', 80, 3, $overdue >= 3
                ? "Просрочено задач: {$overdue}. Разберите список: закройте то, что уже неактуально, а крупные задачи разбейте на шаги по 30–60 минут."
                : "Есть просроченные задачи ({$overdue}). Перенесите срок или выделите на них ближайшую сессию фокуса.");
        }

        $high = $ctx['openTasks']->where('priority', 'high')->count();
        if ($high >= 5) {
            $add('too_many_high', 'planning', 40, 7,
                "Открытых задач с высоким приоритетом: {$high}. Когда важно всё, важного нет — выберите 1–3 главные на сегодня.");
        }

        // 8. Привычки
        $weeklyMilestones = [4, 8, 12, 26];
        $dailyMilestones = [7, 14, 30, 60, 100];
        foreach ($ctx['habits'] as $item) {
            /** @var Habit $habit */
            $habit = $item['habit'];
            $s = $item['stats'];
            $weekly = $habit->frequency === 'weekly';
            $current = $s['current_streak'];

            if (in_array($current, $weekly ? $weeklyMilestones : $dailyMilestones, true)) {
                $add("habit_streak_{$habit->id}_{$current}", 'habit', 20, 365,
                    "Серия по привычке «{$habit->title}»: {$current} {$s['unit']} подряд. Так формируется устойчивая привычка — продолжайте!");
            }

            if (! $weekly && $current === 0 && $s['best_streak'] >= 5) {
                $add("habit_broken_{$habit->id}", 'habit', 55, 7,
                    "Серия по привычке «{$habit->title}» прервалась (лучший результат — {$s['best_streak']} дн.). Одно пропущенное выполнение — не конец: отметьте её сегодня и начните новую серию.");
            }
        }

        $eligible = $ctx['habits']->filter(fn ($i) => $i['habit']->created_at
            && $i['habit']->created_at->copy()->startOfDay()->diffInDays($today, false) >= 7);
        if ($eligible->isNotEmpty() && $eligible->avg(fn ($i) => $i['stats']['rate']) < 40) {
            $add('habits_low_rate', 'habit', 50, 7,
                'Привычки выполняются реже, чем в 40% случаев. Оставьте 1–2 самые важные, привяжите их к одному и тому же времени дня и добавляйте новые постепенно.');
        }

        return $out;
    }

    // ------------------------------------------------------------------ AI

    private function aiCandidates(User $user, array $ctx): array
    {
        if (! $this->ai->enabled()) {
            return [];
        }

        // Модулю нужна длинная история (8 недель) и данные агента — собираются отдельно от правил
        return collect($this->ai->suggest($user, $this->aiPayload->build($user)))
            ->map(fn ($s) => [
                'rule_key' => 'ai_'.($s['key'] ?? substr(md5($s['text']), 0, 12)),
                'type'     => $s['type'],
                'priority' => 10,
                'cooldown' => 14,
                'text'     => $s['text'],
                'source'   => 'ai',
            ])
            ->all();
    }

    // ------------------------------------------------------------------ служебное

    private function recentlyCreated(User $user, string $ruleKey, int $cooldownDays): bool
    {
        return Recommendation::where('user_id', $user->id)
            ->where('rule_key', $ruleKey)
            ->where('created_at', '>=', now()->subDays($cooldownDays))
            ->exists();
    }

    private function seconds(TimeEntry $e): int
    {
        return $e->duration_sec ?? max(0, (int) $e->started_at->diffInSeconds(now()));
    }
}
