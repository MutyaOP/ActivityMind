<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Console\Command;

class GenerateRecommendations extends Command
{
    protected $signature = 'activitymind:generate-recommendations {--user= : ID одного пользователя}';

    protected $description = 'Генерирует рекомендации для всех пользователей или одного';

    public function handle(RecommendationService $service): int
    {
        $users = 0;
        $created = 0;

        User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->each(function (User $user) use ($service, &$users, &$created) {
                $created += $service->generate($user);
                $users++;
            });

        $this->info("Пользователей: {$users}, новых рекомендаций: {$created}.");

        return self::SUCCESS;
    }
}
