<?php

namespace Tests\Feature;

use App\Models\DailyStat;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Almaty';

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
    }

    private function seedToday(User $user): void
    {
        $start = now(self::TZ)->startOfDay()->addHour()->utc();

        TimeEntry::create([
            'user_id' => $user->id, 'started_at' => $start,
            'ended_at' => $start->copy()->addHour(), 'duration_sec' => 3600,
        ]);
        Task::create([
            'user_id' => $user->id, 'title' => 'Готово', 'status' => 'done', 'completed_at' => $start,
        ]);
    }

    public function test_sync_writes_daily_stats(): void
    {
        $user = $this->user();
        $this->seedToday($user);
        $today = now(self::TZ)->startOfDay();

        app(StatsService::class)->sync($user, $today->copy()->subDays(2), $today);

        $this->assertSame(3, DailyStat::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('daily_stats', [
            'user_id' => $user->id, 'stat_date' => $today->toDateString(),
            'focus_sec' => 3600, 'tasks_done' => 1,
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        $user = $this->user();
        $this->seedToday($user);
        $today = now(self::TZ)->startOfDay();
        $svc = app(StatsService::class);

        $svc->sync($user, $today->copy()->subDays(2), $today);
        $svc->sync($user, $today->copy()->subDays(2), $today);

        $this->assertSame(3, DailyStat::where('user_id', $user->id)->count());
    }

    public function test_sync_does_not_mix_users(): void
    {
        $me = $this->user();
        $other = $this->user();
        $this->seedToday($other);
        $today = now(self::TZ)->startOfDay();

        app(StatsService::class)->sync($me, $today->copy(), $today);

        $this->assertDatabaseHas('daily_stats', ['user_id' => $me->id, 'focus_sec' => 0, 'tasks_done' => 0]);
    }

    public function test_analytics_page_opens_for_all_periods(): void
    {
        $user = $this->user();
        $this->seedToday($user);

        foreach ([7, 30, 90] as $days) {
            $this->actingAs($user)->get("/analytics?days={$days}")->assertOk();
        }
    }

    public function test_analytics_default_period_works(): void
    {
        $this->actingAs($this->user())->get('/analytics')->assertOk();
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs($this->user())->get('/analytics?days=5')->assertSessionHasErrors('days');
    }

    public function test_analytics_summary_uses_only_own_data(): void
    {
        $me = $this->user();
        $other = $this->user();
        $this->seedToday($other);

        $data = app(StatsService::class)->analytics($me, 7);

        $this->assertSame(0, $data['summary']['focus_sec']);
        $this->assertSame(0, $data['summary']['tasks_done']);
    }

    public function test_analytics_summary_counts_focus_and_hour(): void
    {
        $user = $this->user();
        $this->seedToday($user);

        $data = app(StatsService::class)->analytics($user, 7);

        $this->assertSame(3600, $data['summary']['focus_sec']);
        $this->assertSame(1, $data['summary']['tasks_done']);
        $this->assertEquals(1.0, $data['hours'][1]); // сессия началась в 01:00 по времени пользователя
        $this->assertCount(7, $data['daily']['labels']);
    }

    public function test_recalc_command_fills_stats_for_all_users(): void
    {
        $a = $this->user();
        $b = $this->user();

        $this->artisan('activitymind:recalc-stats', ['--days' => 3])->assertSuccessful();

        $this->assertSame(3, DailyStat::where('user_id', $a->id)->count());
        $this->assertSame(3, DailyStat::where('user_id', $b->id)->count());
    }
}
