<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

class RuleMatcher
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
    ) {}

    /**
     * @param  array<array-key, mixed>  $facts
     */
    public function match(RuleSetVersion $version, array $facts): RuleMatchCollection
    {
        $matches = $version->loadMissing('rules')->rules
            ->map(fn (Rule $rule): ?RuleMatch => $this->matchRule($version, $rule, $facts))
            ->filter()
            ->sort(fn (RuleMatch $a, RuleMatch $b): int => [$b->score, $a->position] <=> [$a->score, $b->position])
            ->values()
            ->all();

        return new RuleMatchCollection($matches, $version->ruleSet->name);
    }

    /**
     * @param  array<array-key, mixed>  $facts
     */
    private function matchRule(RuleSetVersion $version, Rule $rule, array $facts): ?RuleMatch
    {
        $result = $this->conditionEvaluator->evaluate($rule->condition, $facts);

        if (! $result->matched) {
            return null;
        }

        return new RuleMatch(
            ruleSet: $version->ruleSet->name,
            version: $version->version,
            versionId: $version->id,
            ruleId: $rule->id,
            uuid: $rule->uuid,
            key: $rule->key,
            position: $rule->position,
            value: $rule->value,
            score: $result->score,
            metadata: $rule->metadata,
        );
    }
}
