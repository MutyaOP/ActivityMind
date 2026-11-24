<?php

namespace App\Recommendations\Insights;

use App\Recommendations\Advice;
use App\Recommendations\InsightProvider;
use App\Recommendations\RecommendationContext;
use Illuminate\Support\Facades\Http;

/** Python AI Analyzer-ге HTTP арқылы жүгінеді. Тек агрегатталған сандар жіберіледі (құпиялылық). */
final class AiAnalyzerInsightProvider implements InsightProvider
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token = null,
        private readonly int $timeoutSec = 5,
    ) {
    }

    public function suggest(RecommendationContext $ctx): array
    {
        try {
            $response = Http::timeout($this->timeoutSec)
                ->withToken($this->token ?? '')
                ->post(rtrim($this->url, '/') . '/analyze', [
                    'days'          => array_values($ctx->days),
                    'goal_sec'      => $ctx->goalSec,
                    'focus_by_hour' => $ctx->focusByHour,
                ]);
        } catch (\Throwable) {
            return []; // AI қолжетімсіз — ережелер бәрібір жұмыс істейді
        }

        if (! $response->ok()) {
            return [];
        }

        return array_map(
            fn (array $i) => new Advice('ai_' . $i['key'], $i['type'], $i['text'], 30, 7, 'ai'),
            array_slice($response->json('insights', []), 0, 3),
        );
    }
}

/** Null Object: AI өшірулі болғанда немесе тесттерде қолданылады. */
final class NullInsightProvider implements InsightProvider
{
    public function suggest(RecommendationContext $ctx): array
    {
        return [];
    }
}

/*
 * Тіркеу (AppServiceProvider::register) — жаңа ереже қосу үшін тек осы тізімге класс қосу жеткілікті:
 *
 *   $this->app->tag([LowFocusRule::class, OverdueTasksRule::class, LongSessionsRule::class, BestHourRule::class],
 *                   'recommendation.rules');
 *
 *   $this->app->bind(InsightProvider::class, fn () => config('services.ai_analyzer.url')
 *       ? new AiAnalyzerInsightProvider(config('services.ai_analyzer.url'), config('services.ai_analyzer.token'))
 *       : new NullInsightProvider());
 *
 *   $this->app->bind(RecommendationService::class, fn ($app) => new RecommendationService(
 *       $app->tagged('recommendation.rules'), $app->make(InsightProvider::class)));
 */
