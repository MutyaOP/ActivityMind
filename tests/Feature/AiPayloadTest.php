<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Device;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AiAnalyzerClient;
use App\Services\AiPayloadBuilder;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Almaty';

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => self::TZ]);
    }

    private function log(User $user, Device $device, ?Category $cat, string $app, string $localTime, int $sec, bool $idle = false): void
    {
        ActivityLog::create([
            'user_id' => $user->id, 'device_id' => $device->id, 'category_id' => $cat?->id,
            'app_name' => $app, 'started_at' => now(self::TZ)->setTimeFromTimeString($localTime)->subDay()->utc(),
            'duration_sec' => $sec, 'is_idle' => $idle,
        ]);
    }

    public function test_payload_covers_eight_weeks(): void
    {
        $payload = app(AiPayloadBuilder::class)->build($this->user());

        $this->assertCount(AiPayloadBuilder::DAYS, $payload['days']);
        $this->assertSame(now(self::TZ)->toDateString(), array_key_last($payload['days']));
        $this->assertCount(24, $payload['hours']);
        $this->assertSame(0, $payload['days'][array_key_first($payload['days'])]['switches']);
    }

    public function test_agent_metrics_are_aggregated_per_day(): void
    {
        $user = $this->user();
        $device = Device::create(['user_id' => $user->id, 'name' => 'PC']);
        $work = Category::create(['name' => 'Работа', 'type' => 'work']);
        $rest = Category::create(['name' => 'Отдых', 'type' => 'rest']);

        $this->log($user, $device, $work, 'Code.exe', '10:00', 1800);
        $this->log($user, $device, $work, 'code.exe', '10:30', 1200);   // та же программа — не переключение
        $this->log($user, $device, $rest, 'chrome.exe', '10:50', 600);  // переключение 1
        $this->log($user, $device, $work, 'code.exe', '11:00', 900);    // переключение 2
        $this->log($user, $device, null, 'explorer.exe', '11:15', 60);  // переключение 3, без категории
        $this->log($user, $device, $rest, 'steam.exe', '11:20', 3000, idle: true); // простой не считается

        $day = app(AiPayloadBuilder::class)->build($user)['days'][now(self::TZ)->subDay()->toDateString()];

        $this->assertSame(3900, $day['productive_sec']);
        $this->assertSame(600, $day['rest_sec']);
        $this->assertSame(3, $day['switches']);
    }

    public function test_hours_split_entries_across_hour_boundaries(): void
    {
        $user = $this->user();
        $start = now(self::TZ)->subDay()->setTime(10, 30);
        TimeEntry::create([
            'user_id' => $user->id, 'started_at' => $start->copy()->utc(),
            'ended_at' => $start->copy()->addMinutes(90)->utc(), 'duration_sec' => 5400,
        ]);

        $hours = app(AiPayloadBuilder::class)->build($user)['hours'];

        $this->assertEquals(0.5, $hours[10]);
        $this->assertEquals(1.0, $hours[11]);
        $this->assertEquals(1.5, array_sum($hours));
    }

    public function test_module_receives_agent_fields(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([], 200)]);

        app(RecommendationService::class)->generate($this->user());

        Http::assertSent(function ($request) {
            $day = collect($request['days'])->first();

            return count($request['days']) === AiPayloadBuilder::DAYS
                && array_key_exists('productive_sec', $day)
                && array_key_exists('switches', $day);
        });
    }

    public function test_ai_insights_outrank_minor_rules_and_replace_overlapping_ones(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([
            ['key' => 'peak_hours', 'type' => 'focus', 'text' => 'Пик с 10 до 13'],
            ['key' => 'day_clusters', 'type' => 'planning', 'text' => 'Два типа дней'],
        ], 200)]);
        $user = $this->user();
        $start = now(self::TZ)->subDay()->setTime(9, 0);
        TimeEntry::create([
            'user_id' => $user->id, 'started_at' => $start->copy()->utc(),
            'ended_at' => $start->copy()->addHours(4)->utc(), 'duration_sec' => 4 * 3600,
        ]);

        app(RecommendationService::class)->generate($user);
        $keys = $user->recommendations()->pluck('rule_key')->all();

        $this->assertContains('ai_peak_hours', $keys);
        $this->assertContains('ai_day_clusters', $keys);
        $this->assertNotContains('best_hour', $keys); // правило заменено более точным выводом модуля
    }

    public function test_unreachable_module_is_not_retried_on_every_page(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('refused');
        });
        $client = app(AiAnalyzerClient::class);
        $user = $this->user();

        $this->assertSame([], $client->suggest($user, ['days' => [], 'hours' => []]));
        $this->assertSame([], $client->suggest($user, ['days' => [], 'hours' => []]));

        $this->assertSame(1, $calls);
        $this->assertTrue(Cache::has('ai_analyzer_down'));
    }
}
