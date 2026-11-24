<?php

namespace App\Recommendations;

use App\Models\Recommendation;
use App\Models\User;

/**
 * КЕЙІН: сервис тек оркестрация жасайды — ережелерді шақырады, сұрыптайды, кідірісті тексереді, сақтайды.
 * Ережелер мен AI провайдері конструктор арқылы беріледі (Dependency Injection).
 */
final class RecommendationService
{
    private const MAX_PER_RUN = 4;

    /** @param iterable<RecommendationRule> $rules */
    public function __construct(
        private readonly iterable $rules,
        private readonly InsightProvider $insights,
    ) {
    }

    public function generate(RecommendationContext $ctx): int
    {
        $candidates = [];
        foreach ($this->rules as $rule) {
            if ($advice = $rule->evaluate($ctx)) {
                $candidates[] = $advice;
            }
        }
        array_push($candidates, ...$this->insights->suggest($ctx));

        usort($candidates, fn (Advice $a, Advice $b) => $b->priority <=> $a->priority);

        $created = 0;
        foreach ($candidates as $advice) {
            if ($created >= self::MAX_PER_RUN) {
                break;
            }
            if ($this->isCoolingDown($ctx->user, $advice)) {
                continue;
            }
            Recommendation::create([
                'user_id'  => $ctx->user->id,
                'type'     => $advice->type,
                'text'     => $advice->text,
                'source'   => $advice->source,
                'rule_key' => $advice->ruleKey,
                'is_read'  => false,
            ]);
            $created++;
        }

        return $created;
    }

    private function isCoolingDown(User $user, Advice $advice): bool
    {
        return Recommendation::where('user_id', $user->id)
            ->where('rule_key', $advice->ruleKey)
            ->where('created_at', '>=', now()->subDays($advice->cooldownDays))
            ->exists();
    }
}
