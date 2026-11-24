<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Recommendation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Almaty';

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
    }

    private function service(): RecommendationService
    {
        return app(RecommendationService::class);
    }

    private function keys(User $user): array
    {
        return Recommendation::where('user_id', $user->id)->pluck('rule_key')->all();
    }

    private function entry(User $user, int $daysAgo, int $seconds, int $hour = 10): TimeEntry
    {
        $start = now(self::TZ)->subDays($daysAgo)->startOfDay()->addHours($hour)->utc();

        return TimeEntry::create([
            'user_id' => $user->id, 'started_at' => $start,
            'ended_at' => $start->copy()->addSeconds($seconds), 'duration_sec' => $seconds,
        ]);
    }

    // ---------- страница и доступ ----------

    public function test_guest_is_redirected(): void
    {
        $this->get('/recommendations')->assertRedirect('/login');
    }

    public function test_page_opens_and_autogenerates_welcome_for_new_user(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/recommendations')->assertOk()->assertSee('Добро пожаловать');

        $this->assertContains('welcome', $this->keys($user));
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $this->actingAs($this->user())->get('/recommendations?type=bad')->assertSessionHasErrors('type');
    }

    public function test_user_sees_only_own_recommendations(): void
    {
        $me = $this->user();
        $other = $this->user();
        Recommendation::create(['user_id' => $me->id, 'type' => 'other', 'text' => 'Мой совет', 'rule_key' => 'x1']);
        Recommendation::create(['user_id' => $other->id, 'type' => 'other', 'text' => 'Чужой совет', 'rule_key' => 'x2']);

        $this->actingAs($me)->get('/recommendations')->assertOk()->assertSee('Мой совет')->assertDontSee('Чужой совет');
    }

    public function test_cannot_read_or_delete_foreign_recommendation(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $rec = Recommendation::create(['user_id' => $owner->id, 'type' => 'other', 'text' => 'T', 'rule_key' => 'k']);

        $this->actingAs($other)->patch("/recommendations/{$rec->id}/read")->assertForbidden();
        $this->actingAs($other)->delete("/recommendations/{$rec->id}")->assertForbidden();
        $this->assertDatabaseHas('recommendations', ['id' => $rec->id, 'is_read' => false]);
    }

    public function test_mark_read_read_all_and_delete(): void
    {
        $user = $this->user();
        $a = Recommendation::create(['user_id' => $user->id, 'type' => 'other', 'text' => 'A', 'rule_key' => 'a']);
        $b = Recommendation::create(['user_id' => $user->id, 'type' => 'other', 'text' => 'B', 'rule_key' => 'b']);

        $this->actingAs($user)->patch("/recommendations/{$a->id}/read");
        $this->assertTrue($a->fresh()->is_read);
        $this->assertFalse($b->fresh()->is_read);

        $this->actingAs($user)->post('/recommendations/read-all');
        $this->assertTrue($b->fresh()->is_read);

        $this->actingAs($user)->delete("/recommendations/{$a->id}");
        $this->assertDatabaseMissing('recommendations', ['id' => $a->id]);
    }

    public function test_generate_button_creates_recommendations(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/recommendations/generate')->assertRedirect();

        $this->assertNotEmpty($this->keys($user));
    }

    // ---------- правила ----------

    public function test_welcome_only_for_empty_account(): void
    {
        $user = $this->user();
        Task::create(['user_id' => $user->id, 'title' => 'T']);

        $this->service()->generate($user);

        $this->assertNotContains('welcome', $this->keys($user));
    }

    public function test_overdue_tasks_rule(): void
    {
        $user = $this->user();
        Task::create([
            'user_id' => $user->id, 'title' => 'Просрочена', 'status' => 'todo',
            'due_date' => now(self::TZ)->subDays(2)->toDateString(),
        ]);

        $this->service()->generate($user);

        $this->assertContains('overdue_tasks', $this->keys($user));
    }

    public function test_done_overdue_task_does_not_trigger_rule(): void
    {
        $user = $this->user();
        Task::create([
            'user_id' => $user->id, 'title' => 'Готова', 'status' => 'done',
            'due_date' => now(self::TZ)->subDays(2)->toDateString(), 'completed_at' => now(),
        ]);

        $this->service()->generate($user);

        $this->assertNotContains('overdue_tasks', $this->keys($user));
    }

    public function test_long_session_rule(): void
    {
        $user = $this->user();
        $this->entry($user, 1, 9000); // 150 минут

        $this->service()->generate($user);

        $this->assertContains('long_sessions', $this->keys($user));
    }

    public function test_low_focus_and_no_tracking_rules(): void
    {
        $user = $this->user();
        $this->entry($user, 6, 3600); // история есть, но последние дни пустые

        $this->service()->generate($user);

        $keys = $this->keys($user);
        $this->assertContains('low_focus', $keys);
        $this->assertContains('no_tracking', $keys);
    }

    public function test_habit_milestone_rule(): void
    {
        $user = $this->user();
        $habit = Habit::create(['user_id' => $user->id, 'title' => 'Зарядка']);
        foreach (range(0, 6) as $o) {
            HabitLog::create(['habit_id' => $habit->id, 'log_date' => now(self::TZ)->subDays($o)->toDateString(), 'is_done' => true]);
        }

        $this->service()->generate($user);

        $this->assertContains("habit_streak_{$habit->id}_7", $this->keys($user));
    }

    // ---------- повторы и ограничения ----------

    public function test_same_rule_is_not_repeated_within_cooldown(): void
    {
        $user = $this->user();
        Task::create(['user_id' => $user->id, 'title' => 'T', 'due_date' => now(self::TZ)->subDay()->toDateString()]);

        $first = $this->service()->generate($user);
        $second = $this->service()->generate($user);

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, Recommendation::where('user_id', $user->id)->where('rule_key', 'overdue_tasks')->count());
    }

    public function test_rule_can_fire_again_after_cooldown(): void
    {
        $user = $this->user();
        Task::create(['user_id' => $user->id, 'title' => 'T', 'due_date' => now(self::TZ)->subDay()->toDateString()]);

        $this->service()->generate($user);
        Recommendation::where('user_id', $user->id)->update(['created_at' => now()->subDays(10)]);
        $this->service()->generate($user);

        $this->assertSame(2, Recommendation::where('user_id', $user->id)->where('rule_key', 'overdue_tasks')->count());
    }

    public function test_generate_if_stale_runs_once_per_window(): void
    {
        $user = $this->user();

        $first = $this->service()->generateIfStale($user);
        $second = $this->service()->generateIfStale($user);

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
    }

    public function test_dashboard_still_opens_with_autogeneration(): void
    {
        $this->actingAs($this->user())->get('/dashboard')->assertOk();
    }

    // ---------- AI ----------

    public function test_ai_client_is_disabled_without_url(): void
    {
        config(['services.ai_analyzer.url' => null]);
        Http::fake();

        $this->service()->generate($this->user());

        Http::assertNothingSent();
    }

    public function test_ai_suggestions_are_saved_with_source_ai(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([['type' => 'focus', 'text' => 'AI: работайте блоками по 50 минут']], 200)]);
        $user = $this->user();

        $this->service()->generate($user);

        $this->assertDatabaseHas('recommendations', [
            'user_id' => $user->id, 'source' => 'ai', 'type' => 'focus', 'text' => 'AI: работайте блоками по 50 минут',
        ]);
    }

    public function test_ai_failure_does_not_break_generation(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response('error', 500)]);
        $user = $this->user();

        $created = $this->service()->generate($user);

        $this->assertGreaterThan(0, $created); // правила всё равно отработали
        $this->assertDatabaseMissing('recommendations', ['user_id' => $user->id, 'source' => 'ai']);
    }

    // ---------- команда ----------

    public function test_command_generates_for_all_users(): void
    {
        $a = $this->user();
        $b = $this->user();

        $this->artisan('activitymind:generate-recommendations')->assertSuccessful();

        $this->assertNotEmpty($this->keys($a));
        $this->assertNotEmpty($this->keys($b));
    }
}
