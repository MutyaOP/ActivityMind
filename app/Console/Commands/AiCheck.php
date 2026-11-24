<?php

namespace App\Console\Commands;

use App\Services\AiAnalyzerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AiCheck extends Command
{
    protected $signature = 'activitymind:ai-check';

    protected $description = 'Проверяет подключение к AI-модулю: настройки, связь и ответ на тестовых данных';

    public function handle(AiAnalyzerClient $ai): int
    {
        if (! $ai->enabled()) {
            $this->warn('AI-модуль выключен: не задан адрес.');
            $this->line('1) В .env:  AI_ANALYZER_URL=http://127.0.0.1:5000/analyze  (и при необходимости AI_ANALYZER_TOKEN=...)');
            $this->line("2) В config/services.php добавьте:\n   'ai_analyzer' => ['url' => env('AI_ANALYZER_URL'), 'token' => env('AI_ANALYZER_TOKEN')],");
            $this->line('3) php artisan config:clear');

            return self::FAILURE;
        }

        $url = config('services.ai_analyzer.url');
        $this->line("Адрес: {$url}");
        $this->line('Токен: '.(config('services.ai_analyzer.token') ? 'задан' : 'не задан'));

        try {
            $http = Http::timeout(5)->acceptJson();
            if ($token = config('services.ai_analyzer.token')) {
                $http = $http->withToken($token);
            }
            $response = $http->post($url, $this->samplePayload());
        } catch (\Throwable $e) {
            $this->error('Не удалось подключиться: '.$e->getMessage());
            $this->line('Запущен ли AI-модуль? (python app.py в папке ai-module)');

            return self::FAILURE;
        }

        $this->line('HTTP '.$response->status());

        if ($response->failed()) {
            $this->error('Модуль ответил ошибкой: '.mb_substr($response->body(), 0, 300));

            return self::FAILURE;
        }

        $items = $response->json();
        if (! is_array($items) || $items === []) {
            $this->warn('Связь есть, но на тестовых данных советов нет.');

            return self::SUCCESS;
        }

        foreach ($items as $item) {
            $this->line(sprintf(' • [%s] %s', $item['type'] ?? '?', $item['text'] ?? ''));
        }
        $this->info('AI-модуль подключён и отвечает.');

        return self::SUCCESS;
    }

    /** 14 дней с падающим фокусом и слабыми пятницами — чтобы модуль нашёл закономерности. */
    private function samplePayload(): array
    {
        $focus = [4.6, 4.9, 4.4, 4.1, 0.6, 3.8, 3.5, 3.9, 3.0, 2.8, 2.5, 0.4, 2.0, 1.4];
        $days = [];
        foreach ($focus as $i => $h) {
            $days[now()->startOfWeek()->subWeeks(2)->addDays($i)->toDateString()] = [
                'focus_sec' => (int) ($h * 3600), 'tracked_sec' => 0, 'tasks_done' => $i % 3,
                'habits_done' => $i % 5, 'habits_total' => 4, 'score' => 50,
            ];
        }

        return ['user_id' => 0, 'timezone' => 'UTC', 'days' => $days, 'hours' => array_fill(0, 24, 0)];
    }
}
