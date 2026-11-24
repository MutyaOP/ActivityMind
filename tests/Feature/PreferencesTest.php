<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_login(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_user_can_save_theme_and_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Тема оформления');

        $this->actingAs($user)->patch('/settings', ['theme' => 'light', 'locale' => 'kk'])
            ->assertRedirect()
            ->assertCookie('theme', 'light')
            ->assertCookie('locale', 'kk');

        $user->refresh();
        $this->assertSame('light', $user->theme);
        $this->assertSame('kk', $user->locale);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings', ['theme' => 'neon', 'locale' => 'de'])
            ->assertSessionHasErrors(['theme', 'locale']);
    }

    public function test_pages_render_in_selected_language_and_theme(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['locale' => 'kk', 'theme' => 'light'])->save();
        $this->actingAs($user)->get('/tasks')
            ->assertOk()
            ->assertSee('lang="kk"', false)
            ->assertSee('data-theme="light"', false)
            ->assertSee('Тапсырмалар')
            ->assertSee('Жаңа тапсырма');

        $user->forceFill(['locale' => 'en', 'theme' => 'dark'])->save();
        $this->actingAs($user)->get('/tasks')
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('New task')
            ->assertDontSee('Новая задача');

        $user->forceFill(['locale' => 'ru'])->save();
        $this->actingAs($user)->get('/tasks')->assertOk()->assertSee('Новая задача');
    }

    public function test_guest_can_switch_language_on_login_page(): void
    {
        $this->from('/login')->get('/locale/en')
            ->assertRedirect('/login')
            ->assertCookie('locale', 'en');

        $this->withCookie('locale', 'en')->get('/login')
            ->assertOk()
            ->assertSee('lang="en"', false);

        $this->get('/locale/de')->assertNotFound();
    }

    public function test_logged_in_language_switch_is_saved_to_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/locale/kk')->assertRedirect();
        $this->assertSame('kk', $user->fresh()->locale);
    }

    public function test_validation_messages_are_translated(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'en'])->save();

        $this->actingAs($user)->post('/tasks', ['title' => ''])
            ->assertSessionHasErrors(['title' => 'The title field is required.']);
    }
}
