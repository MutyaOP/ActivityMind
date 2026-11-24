<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_opens_for_user_without_data(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('today');
    }

    public function test_today_focus_is_summed_from_time_entries(): void
    {
        $user = User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);
        $start = now('Asia/Almaty')->startOfDay()->addHour()->utc();

        TimeEntry::create([
            'user_id' => $user->id, 'started_at' => $start,
            'ended_at' => $start->copy()->addHour(), 'duration_sec' => 3600,
        ]);

        $data = app(AnalyticsService::class)->dashboard($user);

        $this->assertSame(3600, $data['today']['focus_sec']);
        $this->assertSame('1 ч 0 мин', $data['today']['focus_label']);
    }

    public function test_other_users_data_is_not_counted(): void
    {
        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $start = now('Asia/Almaty')->startOfDay()->addHour()->utc();

        TimeEntry::create(['user_id' => $other->id, 'started_at' => $start, 'duration_sec' => 7200]);
        Task::create(['user_id' => $other->id, 'title' => 'Чужая задача']);

        $data = app(AnalyticsService::class)->dashboard($me);

        $this->assertSame(0, $data['today']['focus_sec']);
        $this->assertCount(0, $data['activeTasks']);
    }

    public function test_habits_progress_for_today(): void
    {
        $user = User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);
        $h1 = Habit::create(['user_id' => $user->id, 'title' => 'A']);
        Habit::create(['user_id' => $user->id, 'title' => 'B']);
        HabitLog::create(['habit_id' => $h1->id, 'log_date' => now('Asia/Almaty')->toDateString(), 'is_done' => true]);

        $today = app(AnalyticsService::class)->dashboard($user)['today'];

        $this->assertSame(1, $today['habits_done']);
        $this->assertSame(2, $today['habits_total']);
    }

    public function test_score_formula(): void
    {
        $s = app(AnalyticsService::class);

        $this->assertSame(0, $s->score(0, 240, 0, 0, 0));
        $this->assertSame(100, $s->score(240 * 60, 240, 3, 4, 4));   // всё выполнено
        $this->assertSame(100, $s->score(240 * 60, 240, 3, 0, 0));   // без привычек: вес перераспределён
        $this->assertSame(30, $s->score(120 * 60, 240, 0, 0, 3));    // половина цели по фокусу
    }
}
