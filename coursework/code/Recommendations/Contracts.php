<?php

/**
 * ActivityMind — RecommendationService рефакторингі (SOLID + Strategy үлгісі).
 *
 * ДЕЙІН: app/Services/RecommendationService.php
 *   - ruleCandidates() әдісінде 12 ереже бір жерде (≈140 жол) → SRP және OCP бұзылған:
 *     жаңа ереже қосу үшін сервистің өзін өзгерту керек.
 *   - конструктор нақты AiAnalyzerClient класына тәуелді → DIP бұзылған:
 *     AI модулін ауыстыру немесе тестте «өшіру» қиын.
 *
 * КЕЙІН: әр ереже — жеке класс (Strategy), сервис тек интерфейстерді біледі.
 */

namespace App\Recommendations;

use App\Models\User;
use Carbon\CarbonImmutable;

/** Ереже немесе AI ұсынған бір кеңес (мәндік объект). */
final class Advice
{
    public function __construct(
        public readonly string $ruleKey,
        public readonly string $type,      // focus | planning | habits | rest
        public readonly string $text,
        public readonly int $priority,     // 0..100, жоғары — маңыздырақ
        public readonly int $cooldownDays, // осы күн ішінде қайталанбайды
        public readonly string $source = 'rule', // rule | ai
    ) {
    }
}

/** Ережелерге берілетін контекст: 14 күндік статистика және пайдаланушы деректері. */
final class RecommendationContext
{
    /**
     * @param array<string, array{focus_sec:int, tasks_done:int}> $days  күн → көрсеткіштер (өсу ретімен)
     * @param list<array{title:string, due_date:?string, priority:string}> $openTasks
     * @param array<int, int> $focusByHour  сағат (0..23) → секунд
     */
    public function __construct(
        public readonly User $user,
        public readonly CarbonImmutable $today,
        public readonly int $goalSec,
        public readonly array $days,
        public readonly array $openTasks,
        public readonly array $focusByHour,
        public readonly array $sessionLengthsSec = [],
    ) {
    }

    /** Бүгінгі (толық емес) күнсіз соңғы N күн. */
    public function lastCompletedDays(int $n): array
    {
        return array_slice(array_slice($this->days, 0, -1, true), -$n, $n, true);
    }
}

/** Strategy: бір ереже — бір класс (SRP). Жаңа ереже = жаңа класс (OCP). */
interface RecommendationRule
{
    public function key(): string;

    /** Шарт орындалса — кеңес, әйтпесе null. */
    public function evaluate(RecommendationContext $ctx): ?Advice;
}

/** DIP: сервис нақты HTTP-клиентке емес, абстракцияға тәуелді. */
interface InsightProvider
{
    /** @return list<Advice> */
    public function suggest(RecommendationContext $ctx): array;
}
