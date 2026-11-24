<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_user_can_create_task(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/tasks', [
            'title' => 'Диплом: глава 1', 'priority' => 'high', 'status' => 'todo',
        ])->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Диплом: глава 1', 'priority' => 'high']);
    }

    public function test_title_is_required(): void
    {
        $this->actingAs($this->user())
            ->post('/tasks', ['priority' => 'low', 'status' => 'todo'])
            ->assertSessionHasErrors('title');
    }

    public function test_creating_done_task_sets_completed_at(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/tasks', ['title' => 'Готово', 'priority' => 'low', 'status' => 'done']);

        $this->assertNotNull(Task::where('title', 'Готово')->first()->completed_at);
    }

    public function test_user_cannot_edit_or_delete_foreign_task(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Чужая']);

        $this->actingAs($other)->get("/tasks/{$task->id}/edit")->assertForbidden();
        $this->actingAs($other)->delete("/tasks/{$task->id}")->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_toggle_marks_done_and_back(): void
    {
        $user = $this->user();
        $task = Task::create(['user_id' => $user->id, 'title' => 'T']);

        $this->actingAs($user)->patch("/tasks/{$task->id}/toggle");
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $this->actingAs($user)->patch("/tasks/{$task->id}/toggle");
        $task->refresh();
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_list_is_filtered_by_status_and_only_own_tasks(): void
    {
        $user = $this->user();
        $other = $this->user();
        Task::create(['user_id' => $user->id, 'title' => 'Моя в работе', 'status' => 'in_progress']);
        Task::create(['user_id' => $user->id, 'title' => 'Моя новая', 'status' => 'todo']);
        Task::create(['user_id' => $other->id, 'title' => 'Чужая в работе', 'status' => 'in_progress']);

        $this->actingAs($user)->get('/tasks?status=in_progress')
            ->assertOk()
            ->assertSee('Моя в работе')
            ->assertDontSee('Моя новая')
            ->assertDontSee('Чужая в работе');
    }
}
