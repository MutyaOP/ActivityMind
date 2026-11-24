<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_on_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_new_registration_gets_user_role(): void
    {
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'user']);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $user->fill(['role' => 'admin'])->save();

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_registration_ignores_role_field(): void
    {
        $this->post('/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'hacker@example.com', 'role' => 'user']);
    }

    public function test_admin_can_change_other_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}", ['role' => 'admin'])->assertRedirect();

        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_demo_data_fresh_replaces_previous_data(): void
    {
        $user = User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);

        $this->artisan('activitymind:demo-data', ['email' => $user->email, '--days' => 3])->assertSuccessful();
        $this->artisan('activitymind:demo-data', ['email' => $user->email, '--days' => 3, '--fresh' => true])->assertSuccessful();

        $this->assertSame(4, $user->habits()->count());
        $this->assertSame(1, $user->tasks()->where('title', 'Оформить README')->count());
        $this->assertSame(1, \App\Models\Device::where('user_id', $user->id)->count());
    }
}
