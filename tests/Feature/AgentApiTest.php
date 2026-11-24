<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DailyStat;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Device, 1: string, 2: User} */
    private function device(?User $user = null): array
    {
        $user ??= User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);
        $device = Device::create(['user_id' => $user->id, 'name' => 'PC', 'platform' => 'windows']);

        return [$device, $device->rotateToken(), $user];
    }

    private function item(array $override = []): array
    {
        return $override + [
            'app_name'     => 'Code',
            'window_title' => 'main.php',
            'started_at'   => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration_sec' => 600,
            'is_idle'      => false,
        ];
    }

    private function send(string $token, array $items)
    {
        return $this->postJson('/api/agent/activity', ['items' => $items], ['Authorization' => "Bearer {$token}"]);
    }

    public function test_api_routes_are_registered(): void
    {
        $this->assertTrue(
            Route::has('api.agent.activity'),
            'API-маршруты не подключены: добавьте api: __DIR__.\'/../routes/api.php\' в withRouting() файла bootstrap/app.php'
        );
    }

    // ---------- аутентификация ----------

    public function test_requests_without_or_with_wrong_token_are_rejected(): void
    {
        $this->postJson('/api/agent/heartbeat')->assertUnauthorized();
        $this->postJson('/api/agent/activity', ['items' => [$this->item()]], ['Authorization' => 'Bearer amd_wrong'])
            ->assertUnauthorized();
    }

    public function test_heartbeat_marks_device_as_synced(): void
    {
        [$device, $token] = $this->device();

        $this->postJson('/api/agent/heartbeat', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJson(['ok' => true, 'device' => ['id' => $device->id, 'name' => 'PC']]);

        $this->assertNotNull($device->fresh()->last_sync_at);
    }

    // ---------- приём данных ----------

    public function test_activity_is_saved_for_the_device_owner(): void
    {
        $this->seed(CategorySeeder::class);
        [$device, $token, $user] = $this->device();

        $this->send($token, [$this->item()])->assertOk()->assertJson(['accepted' => 1, 'duplicates' => 0]);

        $log = ActivityLog::firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($device->id, $log->device_id);
        $this->assertSame(600, $log->duration_sec);
        $this->assertSame(Category::where('name', 'Программирование')->value('id'), $log->category_id);
    }

    public function test_two_devices_write_to_their_own_users(): void
    {
        [, $tokenA, $a] = $this->device();
        [, $tokenB, $b] = $this->device();

        $this->send($tokenA, [$this->item(['app_name' => 'A'])]);
        $this->send($tokenB, [$this->item(['app_name' => 'B'])]);

        $this->assertSame($a->id, ActivityLog::where('app_name', 'A')->value('user_id'));
        $this->assertSame($b->id, ActivityLog::where('app_name', 'B')->value('user_id'));
    }

    public function test_resending_the_same_batch_does_not_create_duplicates(): void
    {
        [, $token] = $this->device();
        $items = [$this->item(), $this->item(['app_name' => 'chrome', 'window_title' => ''])];

        $this->send($token, $items)->assertJson(['accepted' => 2, 'duplicates' => 0]);
        $this->send($token, $items)->assertJson(['accepted' => 0, 'duplicates' => 2]);

        $this->assertSame(2, ActivityLog::count());
    }

    public function test_idle_sessions_have_no_category(): void
    {
        $this->seed(CategorySeeder::class);
        [, $token] = $this->device();

        $this->send($token, [$this->item(['app_name' => 'Code', 'is_idle' => true])])->assertOk();

        $log = ActivityLog::firstOrFail();
        $this->assertTrue($log->is_idle);
        $this->assertNull($log->category_id);
    }

    public function test_long_window_title_is_truncated_to_255(): void
    {
        [, $token] = $this->device();

        $this->send($token, [$this->item(['window_title' => str_repeat('я', 600)])])->assertOk();

        $this->assertSame(255, mb_strlen(ActivityLog::firstOrFail()->window_title));
    }

    // ---------- валидация ----------

    public function test_invalid_payloads_are_rejected(): void
    {
        [, $token] = $this->device();

        $this->send($token, [])->assertStatus(422);
        $this->send($token, [$this->item(['app_name' => ''])])->assertStatus(422);
        $this->send($token, [$this->item(['duration_sec' => 0])])->assertStatus(422);
        $this->send($token, [$this->item(['duration_sec' => 90000])])->assertStatus(422);
        $this->send($token, [$this->item(['started_at' => 'не дата'])])->assertStatus(422);
        $this->send($token, [$this->item(['started_at' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z')])])->assertStatus(422);

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_batch_over_500_items_is_rejected(): void
    {
        [, $token] = $this->device();
        $items = [];
        for ($i = 0; $i < 501; $i++) {
            $items[] = $this->item(['started_at' => now()->subDays(2)->addSeconds($i)->utc()->format('Y-m-d\TH:i:s\Z')]);
        }

        $this->send($token, $items)->assertStatus(422);
    }

    // ---------- интеграция с аналитикой ----------

    public function test_agent_data_appears_in_analytics_and_daily_stats(): void
    {
        [, $token, $user] = $this->device();

        $this->send($token, [
            $this->item(['app_name' => 'Code', 'duration_sec' => 600]),
            $this->item([
                'app_name' => 'Простой', 'window_title' => '', 'is_idle' => true, 'duration_sec' => 300,
                'started_at' => now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z'),
            ]),
        ])->assertOk();

        $this->actingAs($user)->get('/analytics?days=7')->assertOk()->assertSee('Code');

        // Простой в «отслеживаемое время» не входит
        $this->assertSame(600, (int) DailyStat::where('user_id', $user->id)->sum('tracked_sec'));
    }
    // ---------- вкладки браузера и состояние агента ----------

    public function test_category_hint_from_agent_sets_category(): void
    {
        $this->seed(CategorySeeder::class);
        [, $token] = $this->device();

        $this->send($token, [$this->item(['app_name' => 'chrome', 'window_title' => null, 'category_hint' => 'rest'])])->assertOk();

        $this->assertSame('Отдых', ActivityLog::first()->category->name);
    }

    public function test_unknown_category_hint_is_rejected(): void
    {
        [, $token] = $this->device();

        $this->send($token, [$this->item(['category_hint' => 'hacking'])])->assertUnprocessable();
    }

    public function test_short_browser_tab_title_is_not_stored(): void
    {
        $this->seed(CategorySeeder::class);
        [, $token] = $this->device();

        $this->send($token, [
            $this->item(['app_name' => 'chrome', 'window_title' => 'YouTube', 'duration_sec' => 120]),
            $this->item(['app_name' => 'chrome', 'window_title' => 'Google Classroom', 'duration_sec' => 600,
                'started_at' => now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z')]),
        ])->assertOk();

        $logs = ActivityLog::orderBy('started_at')->get();
        $this->assertNull($logs[0]->window_title);
        $this->assertSame('Отдых', $logs[0]->category->name);  // категорию по заголовку всё равно определили
        $this->assertSame('Google Classroom', $logs[1]->window_title);
    }

    public function test_heartbeat_stores_agent_state(): void
    {
        [$device, $token] = $this->device();
        $since = now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->postJson('/api/agent/heartbeat', ['paused' => false, 'current_app' => 'Code', 'current_since' => $since],
            ['Authorization' => "Bearer {$token}"])->assertOk();
        $device->refresh();
        $this->assertSame('Code', $device->currentApp());
        $this->assertFalse($device->is_paused);

        $this->postJson('/api/agent/heartbeat', ['paused' => true, 'current_app' => 'Code'],
            ['Authorization' => "Bearer {$token}"])->assertOk();
        $device->refresh();
        $this->assertTrue($device->is_paused);
        $this->assertNull($device->currentApp());

        // --check шлёт пустой запрос — состояние не трогается
        $this->postJson('/api/agent/heartbeat', [], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertTrue($device->fresh()->is_paused);
    }
}
