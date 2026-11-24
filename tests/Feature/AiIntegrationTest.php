<?php

namespace Tests\Feature;

use App\Models\Recommendation;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);
    }

    private function aiRules(User $user): array
    {
        return Recommendation::where('user_id', $user->id)->where('source', 'ai')->pluck('rule_key')->all();
    }

    public function test_stable_key_prevents_repeats_even_when_text_changes(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::sequence()
            ->push([['key' => 'trend_down', 'type' => 'focus', 'text' => 'Фокус снижается на 15 мин в день']])
            ->push([['key' => 'trend_down', 'type' => 'focus', 'text' => 'Фокус снижается на 17 мин в день']]),
        ]);
        $user = $this->user();
        $service = app(RecommendationService::class);

        $service->generate($user);
        $service->generate($user);

        $this->assertSame(['ai_trend_down'], $this->aiRules($user));
    }

    public function test_invalid_key_falls_back_to_text_hash(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([['key' => 'Bad Key!', 'type' => 'focus', 'text' => 'Совет без нормального ключа']], 200)]);
        $user = $this->user();

        app(RecommendationService::class)->generate($user);

        $this->assertMatchesRegularExpression('/^ai_[a-f0-9]{12}$/', $this->aiRules($user)[0]);
    }

    public function test_item_without_key_still_works(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([['type' => 'habit', 'text' => 'Совет без ключа']], 200)]);
        $user = $this->user();

        app(RecommendationService::class)->generate($user);

        $this->assertDatabaseHas('recommendations', ['user_id' => $user->id, 'source' => 'ai', 'type' => 'habit']);
    }

    public function test_token_is_sent_to_the_module(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze', 'services.ai_analyzer.token' => 'secret']);
        Http::fake(['ai.test/*' => Http::response([], 200)]);

        app(RecommendationService::class)->generate($this->user());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret')
            && isset($request['days'], $request['hours']));
    }

    public function test_payload_contains_no_personal_data(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([], 200)]);
        $user = User::factory()->create(['name' => 'Секретное Имя', 'email' => 'secret@example.com', 'timezone' => 'Asia/Almaty']);

        app(RecommendationService::class)->generate($user);

        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return ! str_contains($body, 'Секретное Имя') && ! str_contains($body, 'secret@example.com');
        });
    }

    // ---------- команда проверки ----------

    public function test_ai_check_reports_when_disabled(): void
    {
        config(['services.ai_analyzer.url' => null]);

        $this->artisan('activitymind:ai-check')
            ->expectsOutputToContain('AI-модуль выключен')
            ->assertFailed();
    }

    public function test_ai_check_prints_insights_when_connected(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response([['key' => 'trend_down', 'type' => 'focus', 'text' => 'Проверочный совет']], 200)]);

        $this->artisan('activitymind:ai-check')
            ->expectsOutputToContain('Проверочный совет')
            ->assertSuccessful();
    }

    public function test_ai_check_fails_on_module_error(): void
    {
        config(['services.ai_analyzer.url' => 'http://ai.test/analyze']);
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $this->artisan('activitymind:ai-check')->assertFailed();
    }
}
