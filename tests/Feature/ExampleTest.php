<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_is_redirected_to_dashboard_from_home(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_admin_is_redirected_to_admin_panel_from_home(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/')->assertRedirect('/admin');
    }
}