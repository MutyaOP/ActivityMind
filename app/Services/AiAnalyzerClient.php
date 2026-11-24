<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Клиент к внешнему AI-модулю (Python: Pandas + Scikit-learn).
 * Пока адрес не задан в .env (AI_ANALYZER_URL), клиент выключен и ничего не делает.
 *
 * Контракт: POST {url}  JSON {"user_id", "timezone", "days": {date: {...}}, "hours": [24]}
 *           ответ: [{"key": "trend_down", "type": "focus|break|habit|planning|other", "text": "..."}]
 *           key — необязательный стабильный идентификатор вывода (a-z, 0-9, _; до 40 символов): по нему сайт
 *           не создаёт один и тот же совет повторно, даже если цифры в тексте изменились.
 */
class AiAnalyzerClient
{
    private const TYPES = ['focus', 'break', 'habit', 'planning', 'other'];

    public function enabled(): bool
    {
        return filled(config('services.ai_analyzer.url'));
    }

    /** @return array<int, array{key:?string, type:string, text:string}> */
    public function suggest(User $user, array $metrics): array
    {
        if (! $this->enabled()) {
            return [];
        }

        try {
            $http = Http::timeout(5)->acceptJson();
            if ($token = config('services.ai_analyzer.token')) {
                $http = $http->withToken($token);
            }

            $response = $http->post(config('services.ai_analyzer.url'), [
                'user_id'  => $user->id,
                'timezone' => $user->timezone,
            ] + $metrics);

            if ($response->failed()) {
                return [];
            }

            return collect($response->json())
                ->filter(fn ($item) => is_array($item) && filled($item['text'] ?? null))
                ->map(fn ($item) => [
                    'key'  => isset($item['key']) && is_string($item['key']) && preg_match('/^[a-z0-9_]{1,40}$/', $item['key'])
                        ? $item['key']
                        : null,
                    'type' => in_array($item['type'] ?? null, self::TYPES, true) ? $item['type'] : 'other',
                    'text' => Str::limit(trim((string) $item['text']), 500),
                ])
                ->take(3)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
