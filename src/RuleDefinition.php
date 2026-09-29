<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use LeeOvery\RulesEngine\Models\Rule;

final readonly class RuleDefinition
{
    /**
     * @param  array<array-key, mixed>|null  $metadata
     */
    public function __construct(
        public string $key,
        public ConditionBuilder $condition,
        public mixed $value,
        public ?array $metadata = null,
        public ?string $uuid = null,
    ) {}

    public static function fromRule(Rule $rule): self
    {
        return new self($rule->key, $rule->condition, $rule->value, $rule->metadata, $rule->uuid);
    }

    /**
     * @param  array<array-key, mixed>|Unchanged|null  $metadata
     */
    public function with(
        string|Unchanged $key = Unchanged::Keep,
        ConditionBuilder|Unchanged $condition = Unchanged::Keep,
        mixed $value = Unchanged::Keep,
        array|Unchanged|null $metadata = Unchanged::Keep,
        string|Unchanged $uuid = Unchanged::Keep,
    ): self {
        return new self(
            key: $key instanceof Unchanged ? $this->key : $key,
            condition: $condition instanceof Unchanged ? $this->condition : $condition,
            value: $value instanceof Unchanged ? $this->value : $value,
            metadata: $metadata instanceof Unchanged ? $this->metadata : $metadata,
            uuid: $uuid instanceof Unchanged ? $this->uuid : $uuid,
        );
    }

    public function conditionHash(): string
    {
        return Rule::hashCondition($this->condition);
    }
}
