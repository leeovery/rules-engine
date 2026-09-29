<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use Closure;
use Illuminate\Support\Collection;

final readonly class RuleSetChanges
{
    /**
     * @param  list<RuleReference>  $added
     * @param  list<RuleReference>  $removed
     * @param  list<RenamedRule>  $renamed
     * @param  list<RuleReference>  $conditionChanged
     * @param  list<RuleReference>  $valueChanged
     * @param  list<RuleReference>  $metadataChanged
     * @param  list<RuleReference>  $moved
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
        public array $renamed = [],
        public array $conditionChanged = [],
        public array $valueChanged = [],
        public array $metadataChanged = [],
        public array $moved = [],
    ) {}

    /**
     * @param  list<RuleDefinition>  $before
     * @param  list<RuleDefinition>  $after
     */
    public static function between(array $before, array $after): self
    {
        $old = collect($before)->keyBy('uuid');
        $new = collect($after)->keyBy('uuid');
        $kept = $new->intersectByKeys($old);
        $was = $old->all();

        $changedIn = fn (Closure $part): array => self::references(
            $kept->filter(fn (RuleDefinition $rule, string $uuid): bool => $part($rule) !== $part($was[$uuid])),
        );

        return new self(
            added: self::references($new->diffKeys($old)),
            removed: self::references($old->diffKeys($new)),
            renamed: array_values($kept
                ->filter(fn (RuleDefinition $rule, string $uuid): bool => $rule->key !== $was[$uuid]->key)
                ->map(fn (RuleDefinition $rule, string $uuid): RenamedRule => new RenamedRule($uuid, $was[$uuid]->key, $rule->key))
                ->all()),
            conditionChanged: $changedIn(fn (RuleDefinition $rule): string => $rule->conditionHash()),
            valueChanged: $changedIn(fn (RuleDefinition $rule): string => self::json($rule->value)),
            metadataChanged: $changedIn(fn (RuleDefinition $rule): string => self::json($rule->metadata)),
            moved: self::references($kept->only(self::outOfOrder(array_keys($kept->all()), $old->keys()->flip()->all()))),
        );
    }

    public function isEmpty(): bool
    {
        return $this->added === []
            && $this->removed === []
            && $this->renamed === []
            && $this->conditionChanged === []
            && $this->valueChanged === []
            && $this->metadataChanged === []
            && $this->moved === [];
    }

    /**
     * @param  Collection<string, RuleDefinition>  $rules
     * @return list<RuleReference>
     */
    private static function references(Collection $rules): array
    {
        return array_values($rules
            ->map(fn (RuleDefinition $rule, string $uuid): RuleReference => new RuleReference($uuid, $rule->key))
            ->all());
    }

    private static function json(mixed $value): string
    {
        return json_encode(JsonValue::normalise($value), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * The rules kept in both versions that aren't in the longest run whose order the versions share,
     * which is the fewest rules that could have moved to give the new order.
     *
     * @param  list<array-key>  $uuids  in their new order
     * @param  array<array-key, int>  $oldPositions
     * @return list<array-key>
     */
    private static function outOfOrder(array $uuids, array $oldPositions): array
    {
        $runs = [];

        foreach ($uuids as $index => $uuid) {
            $runs[$index] = [$index];

            foreach (array_slice($runs, 0, $index) as $earlier => $run) {
                if ($oldPositions[$uuids[$earlier]] < $oldPositions[$uuid] && count($run) >= count($runs[$index])) {
                    $runs[$index] = [...$run, $index];
                }
            }
        }

        $longest = collect($runs)->sortByDesc(fn (array $run): int => count($run))->first() ?? [];

        return array_values(array_diff_key($uuids, array_flip($longest)));
    }
}
