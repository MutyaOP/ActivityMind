<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\HabitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Almaty';

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
    }

    private function daily(User $user): Habit
    {
        return Habit::create(['user_id' => $user->id, 'title' => 'Зарядка']);
    }

    /** Отметки «N дней назад» (0 = сегодня). */
    private function logDays(Habit $habit, array $offsets): void
    {
        foreach ($offsets as $o) {
            HabitLog::create([
                'habit_id' => $habit->id,
                'log_date' => now(self::TZ)->subDays($o)->toDateString(),
                'is_done'  => true,
            ]);
        }
    }

    public function test_user_can_create_habit(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/habits', [
            'title' => 'Чтение', 'frequency' => 'daily', 'color' => '#ff0000', 'is_active' => 1,
        ])->assertRedirect('/habits');

        $this->assertDatabaseHas('habits', ['user_id' => $user->id, 'title' => 'Чтение', 'target_per_period' => 1]);
    }

    public function test_weekly_habit_keeps_target(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/habits', [
            'title' => 'Спортзал', 'frequency' => 'weekly', 'target_per_period' => 3, 'is_active' => 1,
        ]);

        $this->assertDatabaseHas('habits', ['title' => 'Спортзал', 'frequency' => 'weekly', 'target_per_period' => 3]);
    }

    public function test_invalid_color_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->post('/habits', ['title' => 'X', 'frequency' => 'daily', 'color' => 'red'])
            ->assertSessionHasErrors('color');
    }

    public function test_toggle_creates_and_removes_log(): void
    {
        $user = $this->user();
        $habit = $this->daily($user);

        $this->actingAs($user)->post("/habits/{$habit->id}/toggle");
        $this->assertSame(1, HabitLog::where('habit_id', $habit->id)->count());

        $this->actingAs($user)->post("/habits/{$habit->id}/toggle");
        $this->assertSame(0, HabitLog::where('habit_id', $habit->id)->count());
    }

    public function test_cannot_toggle_future_or_too_old_date(): void
    {
        $user = $this->user();
        $habit = $this->daily($user);

        $this->actingAs($user)->post("/habits/{$habit->id}/toggle", ['date' => now(self::TZ)->addDay()->toDateString()])
            ->assertStatus(422);
        $this->actingAs($user)->post("/habits/{$habit->id}/toggle", ['date' => now(self::TZ)->subDays(40)->toDateString()])
            ->assertStatus(422);
    }

    public function test_cannot_touch_foreign_habit(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $habit = $this->daily($owner);

        $this->actingAs($other)->post("/habits/{$habit->id}/toggle")->assertForbidden();
        $this->actingAs($other)->get("/habits/{$habit->id}/edit")->assertForbidden();
        $this->actingAs($other)->delete("/habits/{$habit->id}")->assertForbidden();
        $this->assertDatabaseHas('habits', ['id' => $habit->id]);
    }

    public function test_habits_page_opens(): void
    {
        $user = $this->user();
        $this->daily($user);

        $this->actingAs($user)->get('/habits')->assertOk()->assertSee('Зарядка');
    }

    public function test_daily_streak_counts_consecutive_days_including_today(): void
    {
        $habit = $this->daily($this->user());
        $this->logDays($habit, [0, 1, 2]);

        $s = app(HabitService::class)->stats($habit, self::TZ);

        $this->assertSame(3, $s['current_streak']);
        $this->assertSame(3, $s['best_streak']);
    }

    public function test_streak_survives_if_today_is_not_done_yet(): void
    {
        $habit = $this->daily($this->user());
        $this->logDays($habit, [1, 2, 3]);

        $this->assertSame(3, app(HabitService::class)->stats($habit, self::TZ)['current_streak']);
    }

    public function test_streak_resets_after_gap_but_best_is_kept(): void
    {
        $habit = $this->daily($this->user());
        $this->logDays($habit, [0, 1, 3, 4, 5]);

        $s = app(HabitService::class)->stats($habit, self::TZ);

        $this->assertSame(2, $s['current_streak']);
        $this->assertSame(3, $s['best_streak']);
    }

    public function test_streak_is_zero_after_missed_yesterday_and_today(): void
    {
        $habit = $this->daily($this->user());
        $this->logDays($habit, [2, 3]);

        $s = app(HabitService::class)->stats($habit, self::TZ);

        $this->assertSame(0, $s['current_streak']);
        $this->assertSame(2, $s['best_streak']);
    }

    public function test_weekly_streak_counts_weeks_with_target_met(): void
    {
        $user = $this->user();
        $habit = Habit::create(['user_id' => $user->id, 'title' => 'Спортзал', 'frequency' => 'weekly', 'target_per_period' => 2]);

        $thisWeek = now(self::TZ)->startOfWeek();
        $prevWeek = $thisWeek->copy()->subWeek();
        foreach ([$thisWeek, $thisWeek->copy()->addDay(), $prevWeek, $prevWeek->copy()->addDay()] as $d) {
            HabitLog::create(['habit_id' => $habit->id, 'log_date' => $d->toDateString(), 'is_done' => true]);
        }

        $s = app(HabitService::class)->stats($habit, self::TZ);

        $this->assertSame(2, $s['current_streak']);
        $this->assertSame('нед.', $s['unit']);
    }

    public function test_week_grid_has_seven_days_and_marks_today(): void
    {
        $habit = $this->daily($this->user());
        $this->logDays($habit, [0]);

        $week = app(HabitService::class)->stats($habit, self::TZ)['week'];

        $this->assertCount(7, $week);
        $this->assertTrue($week[6]['is_today']);
        $this->assertTrue($week[6]['done']);
        $this->assertFalse($week[5]['done']);
    }
}
