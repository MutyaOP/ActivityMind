<?php

/**
 * Strategy үлгісінің пайдасы: әр ережені деректер қорынсыз, оқшау тестілеуге болады.
 * (PHPUnit үлгісі; жобаға көшірілгенде tests/Unit/ ішінде іске қосылады.)
 */

use App\Models\User;
use App\Recommendations\RecommendationContext;
use App\Recommendations\Rules\LowFocusRule;
use App\Recommendations\Rules\OverdueTasksRule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    private function ctx(array $days, array $tasks = []): RecommendationContext
    {
        return new RecommendationContext(new User(), CarbonImmutable::parse('2026-11-24'), 4 * 3600,
            $days, $tasks, array_fill(0, 24, 0));
    }

    public function test_low_focus_fires_after_three_weak_days(): void
    {
        $days = ['21' => ['focus_sec' => 600, 'tasks_done' => 0], '22' => ['focus_sec' => 900, 'tasks_done' => 0],
                 '23' => ['focus_sec' => 300, 'tasks_done' => 0], '24' => ['focus_sec' => 0, 'tasks_done' => 0]];

        $this->assertSame('low_focus', (new LowFocusRule())->evaluate($this->ctx($days))?->ruleKey);
    }

    public function test_low_focus_is_silent_when_goal_met(): void
    {
        $day = ['focus_sec' => 5 * 3600, 'tasks_done' => 2];

        $this->assertNull((new LowFocusRule())->evaluate($this->ctx(['21' => $day, '22' => $day, '23' => $day, '24' => $day])));
    }

    public function test_overdue_counts_only_past_due_dates(): void
    {
        $tasks = [['title' => 'A', 'due_date' => '2026-11-20', 'priority' => 'high'],
                  ['title' => 'B', 'due_date' => '2026-11-30', 'priority' => 'low'],
                  ['title' => 'C', 'due_date' => null, 'priority' => 'medium']];

        $advice = (new OverdueTasksRule())->evaluate($this->ctx([], $tasks));
        $this->assertStringStartsWith('1 ', $advice->text);
    }
}
