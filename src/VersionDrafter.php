<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use Closure;
use Illuminate\Support\Str;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

class VersionDrafter
{
    public function __construct(
        private readonly ConditionParser $conditionParser,
    ) {}

    public function draft(PendingRuleSet $pending, ?RuleSet $ruleSet): DraftVersion
    {
        $this->guardEffectiveDate($pending, $ruleSet);

        $previous = $this->previousVersion($pending, $ruleSet);
        $before = new RuleList($pending->getName(), $this->definitionsOf($previous));
        $after = $this->rulesAfter($before, $pending, $ruleSet)->all();

        $this->guardRules($pending->getName(), $after);

        $after = array_map($this->asStored(...), $after);

        return new DraftVersion(
            version: $this->latestVersionNumber($ruleSet) + 1,
            previousVersion: $previous?->version,
            rules: $after,
            changes: RuleSetChanges::between($before->all(), $after),
        );
    }

    private function guardEffectiveDate(PendingRuleSet $pending, ?RuleSet $ruleSet): void
    {
        $first = $ruleSet?->versions()->whereVersion(1)->first();

        if ($first === null) {
            return;
        }

        $effectiveFrom = $pending->getEffectiveFrom()?->toDateString();
        $firstEffectiveFrom = $first->effective_from?->toDateString();

        if ($firstEffectiveFrom === null) {
            throw_if($effectiveFrom !== null, CannotPublishRuleSetException::effectiveDateNotAllowed($pending->getName()));

            return;
        }

        throw_if($effectiveFrom === null, CannotPublishRuleSetException::effectiveDateRequired($pending->getName()));

        if ($effectiveFrom < $firstEffectiveFrom) {
            throw CannotPublishRuleSetException::effectiveBeforeFirstVersion($pending->getName(), $effectiveFrom, $firstEffectiveFrom);
        }
    }

    private function previousVersion(PendingRuleSet $pending, ?RuleSet $ruleSet): ?RuleSetVersion
    {
        $versions = $ruleSet?->versions()->with('rules');
        $effectiveFrom = $pending->getEffectiveFrom();

        return $effectiveFrom === null
            ? $versions?->newestFirst()->first()
            : $versions?->inForceOn($effectiveFrom)->first();
    }

    private function rulesAfter(RuleList $before, PendingRuleSet $pending, ?RuleSet $ruleSet): RuleList
    {
        $revertTo = $pending->getRevertVersion();

        return match (true) {
            $revertTo !== null => $this->reverted($pending->getName(), $revertTo, $ruleSet),
            $pending->getEdits() !== [] => array_reduce(
                $pending->getEdits(),
                fn (RuleList $rules, Closure $edit): RuleList => $edit($rules),
                $before,
            ),
            default => $this->listed($pending, $before),
        };
    }

    private function listed(PendingRuleSet $pending, RuleList $before): RuleList
    {
        return new RuleList($pending->getName(), array_map(
            fn (RuleDefinition $rule): RuleDefinition => $rule->with(uuid: $before->find($rule->key)->uuid ?? (string) Str::uuid7()),
            $pending->getRules(),
        ));
    }

    private function reverted(string $name, int $number, ?RuleSet $ruleSet): RuleList
    {
        $version = $ruleSet?->versions()->whereVersion($number)->with('rules')->first()
            ?? throw NoApplicableVersionException::numbered($name, $number);

        return new RuleList($name, $this->definitionsOf($version));
    }

    /**
     * @return list<RuleDefinition>
     */
    private function definitionsOf(?RuleSetVersion $version): array
    {
        return array_values($version?->rules->map(RuleDefinition::fromRule(...))->all() ?? []);
    }

    private function asStored(RuleDefinition $rule): RuleDefinition
    {
        return $rule->with(value: JsonValue::normalise($rule->value), metadata: JsonValue::normalise($rule->metadata));
    }

    private function latestVersionNumber(?RuleSet $ruleSet): int
    {
        return (int) $ruleSet?->versions()->max('version');
    }

    /**
     * @param  list<RuleDefinition>  $rules
     */
    private function guardRules(string $ruleSet, array $rules): void
    {
        throw_if($rules === [], CannotPublishRuleSetException::withoutRules($ruleSet));

        foreach ($rules as $rule) {
            $this->guardJsonSafe($ruleSet, $rule);
        }

        $this->guardUnique(
            $rules,
            fn (RuleDefinition $rule): string => $rule->key,
            fn (RuleDefinition $first, RuleDefinition $repeat) => CannotPublishRuleSetException::duplicateKey($ruleSet, $repeat->key),
        );

        $this->guardUnique(
            $rules,
            fn (RuleDefinition $rule): string => $rule->conditionHash(),
            fn (RuleDefinition $first, RuleDefinition $repeat) => CannotPublishRuleSetException::duplicateCondition($ruleSet, $first->key, $repeat->key),
        );
    }

    private function guardJsonSafe(string $ruleSet, RuleDefinition $rule): void
    {
        $parts = [
            'value' => $rule->value,
            'metadata' => $rule->metadata,
            'condition' => $this->conditionParser->toArray($rule->condition),
        ];

        foreach ($parts as $part => $content) {
            $unsafeType = JsonValue::unsafeTypeIn($content);

            if ($unsafeType !== null) {
                throw CannotPublishRuleSetException::notJsonSafe($ruleSet, $rule->key, $part, $unsafeType);
            }
        }
    }

    /**
     * @param  list<RuleDefinition>  $rules
     * @param  Closure(RuleDefinition): string  $by
     * @param  Closure(RuleDefinition, RuleDefinition): CannotPublishRuleSetException  $exception
     */
    private function guardUnique(array $rules, Closure $by, Closure $exception): void
    {
        $firstWith = [];

        foreach ($rules as $rule) {
            $value = $by($rule);

            if (isset($firstWith[$value])) {
                throw $exception($firstWith[$value], $rule);
            }

            $firstWith[$value] = $rule;
        }
    }
}
