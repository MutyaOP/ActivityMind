<?php

/**
 * Нақты стратегиялар — RecommendationService::ruleCandidates() ішіндегі ережелердің бөлінген нұсқасы.
 * Кілттер (rule_key) жүйеде бұрыннан бар кілттермен сәйкес келеді.
 */

namespace App\Recommendations\Rules;

use App\Recommendations\Advice;
use App\Recommendations\RecommendationContext;
use App\Recommendations\RecommendationRule;

/** Үш күн қатарынан фокус мақсаттың жартысынан аз. */
final class LowFocusRule implements RecommendationRule
{
    public function key(): string
    {
        return 'low_focus';
    }

    public function evaluate(RecommendationContext $ctx): ?Advice
    {
        $last3 = $ctx->lastCompletedDays(3);
        if (count($last3) < 3) {
            return null;
        }
        foreach ($last3 as $day) {
            if ($day['focus_sec'] >= $ctx->goalSec * 0.5) {
                return null;
            }
        }

        return new Advice($this->key(), 'focus',
            'Үш күн қатарынан фокус мақсаттың жартысынан төмен. 25 минуттық бір сессиядан бастаңыз (Pomodoro).',
            priority: 70, cooldownDays: 3);
    }
}

/** Мерзімі өткен тапсырмалар бар. */
final class OverdueTasksRule implements RecommendationRule
{
    public function key(): string
    {
        return 'overdue_tasks';
    }

    public function evaluate(RecommendationContext $ctx): ?Advice
    {
        $today = $ctx->today->toDateString();
        $overdue = array_filter($ctx->openTasks,
            fn (array $t) => $t['due_date'] !== null && $t['due_date'] < $today);

        if (count($overdue) === 0) {
            return null;
        }

        return new Advice($this->key(), 'planning',
            sprintf('%d тапсырманың мерзімі өтіп кетті. Біреуін бүгін жабыңыз немесе мерзімін жаңартыңыз.', count($overdue)),
            priority: 80, cooldownDays: 2);
    }
}

/** Үзіліссіз ұзақ сессиялар (≥ 2 сағ) жиі кездеседі. */
final class LongSessionsRule implements RecommendationRule
{
    private const LONG_SEC = 7200;

    public function key(): string
    {
        return 'long_sessions';
    }

    public function evaluate(RecommendationContext $ctx): ?Advice
    {
        $long = array_filter($ctx->sessionLengthsSec, fn (int $s) => $s >= self::LONG_SEC);
        if (count($long) < 3) {
            return null;
        }

        return new Advice($this->key(), 'rest',
            'Соңғы екі аптада 2 сағаттан ұзақ үзіліссіз сессиялар көп. Әр 50–90 минут сайын 10 минут демалыңыз.',
            priority: 50, cooldownDays: 7);
    }
}

/** Ең өнімді сағатты табады. */
final class BestHourRule implements RecommendationRule
{
    public function key(): string
    {
        return 'best_hour';
    }

    public function evaluate(RecommendationContext $ctx): ?Advice
    {
        $total = array_sum($ctx->focusByHour);
        if ($total < 5 * 3600) {
            return null; // деректер аз
        }
        $best = array_keys($ctx->focusByHour, max($ctx->focusByHour))[0];

        return new Advice($this->key(), 'focus',
            sprintf('Сіз %02d:00–%02d:00 аралығында ең өнімдісіз. Күрделі тапсырмаларды осы уақытқа жоспарлаңыз.', $best, ($best + 1) % 24),
            priority: 40, cooldownDays: 14);
    }
}
