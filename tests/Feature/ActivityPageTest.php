<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityPageTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Almaty';

    private User $user;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->user = User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
        $this->device = Device::create(['user_id' => $this->user->id, 'name' => 'Ноутбук']);
    }

    private function log(string $app, string $time, int $sec, ?string $category = null, ?string $title = null, int $daysAgo = 0, bool $idle = false, ?User $user = null): void
    {
        $user ??= $this->user;
        ActivityLog::create([
            'user_id' => $user->id, 'device_id' => $this->device->id,
            'category_id' => $category ? Category::where('name', $category)->value('id') : null,
            'app_name' => $app, 'window_title' => $title,
            'started_at' => now(self::TZ)->subDays($daysAgo)->setTimeFromTimeString($time)->utc(),
            'duration_sec' => $sec, 'is_idle' => $idle,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/activity')->assertRedirect('/login');
    }

    public function test_current_session_shows_apps_and_long_browser_tabs(): void
    {
        $this->log('Code', '00:01', 3600, 'Программирование');
        $this->log('chrome', '01:01', 900, 'Отдых', 'Lo-fi - YouTube');
        $this->log('chrome', '01:20', 120, 'Отдых', 'Короткая вкладка');
        $this->log('Простой', '01:30', 600, idle: true);

        $this->actingAs($this->user)->get('/activity')
            ->assertOk()
            ->assertSee('Текущий сеанс')
            ->assertSee('VS Code')
            ->assertSee('Google Chrome')
            ->assertSee('1 ч 17 мин')          // 60 + 15 + 2 мин активного времени, простой не считается
            ->assertSee('Lo-fi - YouTube')
            ->assertDontSee('Короткая вкладка'); // меньше 5 минут
    }

    public function test_past_day_lists_apps_without_browser_tabs(): void
    {
        $this->log('WINWORD', '10:00', 1800, 'Работа', daysAgo: 2);
        $this->log('chrome', '11:00', 900, 'Учёба', 'Google Classroom', daysAgo: 2);

        $date = now(self::TZ)->subDays(2)->toDateString();
        $this->actingAs($this->user)->get('/activity?date='.$date)
            ->assertOk()
            ->assertSee('Word')
            ->assertSee('Google Chrome')
            ->assertDontSee('Google Classroom')
            ->assertSee('браузер показан общим временем');
    }

    public function test_shows_only_own_activity(): void
    {
        $other = User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
        $this->log('SecretApp', '00:01', 600, user: $other);

        $this->actingAs($this->user)->get('/activity')->assertOk()->assertDontSee('SecretApp');
    }

    public function test_device_state_is_shown(): void
    {
        $this->device->forceFill([
            'last_sync_at' => now(), 'is_paused' => false,
            'current_app' => 'Code', 'current_since' => now()->subMinutes(25),
        ])->save();

        $this->actingAs($this->user)->get('/activity')->assertSee('открыто VS Code')->assertSee('25 мин');

        $this->device->forceFill(['is_paused' => true, 'current_app' => null])->save();
        $this->actingAs($this->user)->get('/activity')->assertSee('учёт на паузе');
    }

    public function test_invalid_and_future_dates(): void
    {
        $this->actingAs($this->user)->get('/activity?date=not-a-date')->assertSessionHasErrors('date');
        $this->actingAs($this->user)->get('/activity?date='.now()->addDays(3)->toDateString())
            ->assertOk()->assertSee('Текущий сеанс');
    }
}
