<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_start_creates_running_entry(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/timer/start')->assertRedirect('/timer');

        $this->assertSame(1, TimeEntry::where('user_id', $user->id)->running()->count());
    }

    public function test_starting_second_timer_stops_the_first(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/timer/start');
        $this->actingAs($user)->post('/timer/start');

        $this->assertSame(1, TimeEntry::where('user_id', $user->id)->running()->count());
        $this->assertSame(2, TimeEntry::where('user_id', $user->id)->count());
    }

    public function test_stop_sets_end_and_duration(): void
    {
        $user = $this->user();
        $entry = TimeEntry::create(['user_id' => $user->id, 'started_at' => now()->subMinutes(5)]);

        $this->actingAs($user)->post('/timer/stop');
        $entry->refresh();

        $this->assertNotNull($entry->ended_at);
        $this->assertGreaterThanOrEqual(300, $entry->duration_sec);
        $this->assertLessThan(320, $entry->duration_sec);
    }

    public function test_start_with_task_moves_todo_to_in_progress(): void
    {
        $user = $this->user();
        $task = Task::create(['user_id' => $user->id, 'title' => 'T', 'status' => 'todo']);

        $this->actingAs($user)->post('/timer/start', ['task_id' => $task->id]);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('time_entries', ['user_id' => $user->id, 'task_id' => $task->id]);
    }

    public function test_cannot_start_timer_on_foreign_task(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Чужая']);

        $this->actingAs($other)->post('/timer/start', ['task_id' => $task->id])
            ->assertSessionHasErrors('task_id');
    }

    public function test_manual_entry_requires_end_after_start(): void
    {
        $this->actingAs($this->user())->post('/timer/manual', [
            'started_at' => '2026-11-14T12:00', 'ended_at' => '2026-11-14T11:00',
        ])->assertSessionHasErrors('ended_at');
    }

    public function test_manual_entry_is_saved_with_duration(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/timer/manual', [
            'started_at' => '2026-11-14T10:00', 'ended_at' => '2026-11-14T11:30',
        ])->assertRedirect('/timer');

        $this->assertDatabaseHas('time_entries', ['user_id' => $user->id, 'duration_sec' => 5400]);
    }

    public function test_cannot_delete_foreign_entry(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $entry = TimeEntry::create([
            'user_id' => $owner->id, 'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_sec' => 3600,
        ]);

        $this->actingAs($other)->delete("/timer/{$entry->id}")->assertForbidden();
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id]);
    }
}
