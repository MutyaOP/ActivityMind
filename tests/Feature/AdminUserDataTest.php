<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user', 'name' => 'Айгерим']);
    }

    public function test_regular_user_cannot_open_user_data(): void
    {
        $other = User::factory()->create(['role' => 'user']);

        $this->actingAs($other)->get("/admin/users/{$this->user->id}")->assertForbidden();
    }

    public function test_admin_sees_user_tasks_and_apps_but_not_window_titles(): void
    {
        Task::create(['user_id' => $this->user->id, 'title' => 'Глава 2 курсовой', 'priority' => 'high', 'status' => 'todo']);
        $device = Device::create(['user_id' => $this->user->id, 'name' => 'Ноутбук']);
        ActivityLog::create([
            'user_id' => $this->user->id, 'device_id' => $device->id,
            'app_name' => 'code.exe', 'window_title' => 'secret-project.php — VS Code',
            'started_at' => now()->subMinutes(30), 'duration_sec' => 600, 'is_idle' => false,
        ]);

        $this->actingAs($this->admin)->get("/admin/users/{$this->user->id}")
            ->assertOk()
            ->assertSee('Айгерим')
            ->assertSee('Глава 2 курсовой')
            ->assertSee('VS Code')
            ->assertSee('Ноутбук')
            ->assertDontSee('secret-project.php');
    }

    public function test_admin_user_search(): void
    {
        User::factory()->create(['name' => 'Бекзат']);

        $this->actingAs($this->admin)->get('/admin/users?q=Айгер')
            ->assertOk()
            ->assertSee('Айгерим')
            ->assertDontSee('Бекзат');
    }

    public function test_future_date_is_clamped_to_today(): void
    {
        $this->actingAs($this->admin)
            ->get("/admin/users/{$this->user->id}?date=".now()->addDays(5)->toDateString())
            ->assertOk();

        $this->actingAs($this->admin)->get("/admin/users/{$this->user->id}?date=bad")->assertSessionHasErrors('date');
    }
}
