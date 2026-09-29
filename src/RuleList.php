<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;

final readonly class RuleList
{
    /**
     * @param  list<RuleDefinition>  $rules
     */
    public function __construct(
        private string $ruleSet,
        private array $rules = [],
    ) {}

    /**
     * @return list<RuleDefinition>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function find(string $key): ?RuleDefinition
    {
        return collect($this->rules)->first(fn (RuleDefinition $rule): bool => $rule->key === $key);
    }

    public function add(RuleDefinition $rule, ?string $before = null, ?string $after = null): self
    {
        throw_if($this->find($rule->key) !== null, CannotPublishRuleSetException::ruleExists($this->ruleSet, $rule->key));

        return $this->insert($rule, $this->placeFor($before, $after));
    }

    /**
     * @param  array<array-key, mixed>|Unchanged|null  $metadata
     */
    public function update(string $key, ConditionBuilder|Unchanged $condition, mixed $value, array|Unchanged|null $metadata): self
    {
        return $this->replace($key, $this->get($key)->with(condition: $condition, value: $value, metadata: $metadata));
    }

    public function move(string $key, ?string $before = null, ?string $after = null): self
    {
        throw_if($key === ($before ?? $after), CannotPublishRuleSetException::movedAgainstItself($this->ruleSet, $key));

        $rest = $this->remove($key);

        return $rest->insert($this->get($key), $rest->placeFor($before, $after));
    }

    public function rename(string $from, string $to): self
    {
        $rule = $this->get($from);

        throw_if($this->find($to) !== null, CannotPublishRuleSetException::ruleExists($this->ruleSet, $to));

        return $this->replace($from, $rule->with(key: $to));
    }

    public function remove(string $key): self
    {
        $this->get($key);

        return new self($this->ruleSet, array_values(array_filter(
            $this->rules,
            fn (RuleDefinition $rule): bool => $rule->key !== $key,
        )));
    }

    private function get(string $key): RuleDefinition
    {
        return $this->find($key) ?? throw CannotPublishRuleSetException::noSuchRule($this->ruleSet, $key);
    }

    private function indexOf(string $key): int
    {
        foreach ($this->rules as $index => $rule) {
            if ($rule->key === $key) {
                return $index;
            }
        }

        throw CannotPublishRuleSetException::noSuchRule($this->ruleSet, $key);
    }

    private function placeFor(?string $before, ?string $after): int
    {
        return match (true) {
            $before !== null => $this->indexOf($before),
            $after !== null => $this->indexOf($after) + 1,
            default => count($this->rules),
        };
    }

    private function insert(RuleDefinition $rule, int $index): self
    {
        return new self($this->ruleSet, [
            ...array_slice($this->rules, 0, $index),
            $rule,
            ...array_slice($this->rules, $index),
        ]);
    }

    private function replace(string $key, RuleDefinition $rule): self
    {
        $index = $this->indexOf($key);

        return new self($this->ruleSet, [
            ...array_slice($this->rules, 0, $index),
            $rule,
            ...array_slice($this->rules, $index + 1),
        ]);
    }
}
