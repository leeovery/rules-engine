<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BadMethodCallException;

/**
 * @mixin ConditionBuilder
 */
final class Condition
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): ConditionBuilder
    {
        $condition = new ConditionBuilder;

        return match ($method) {
            'where' => $condition->where(...$arguments),
            'whereIn' => $condition->whereIn(...$arguments),
            'whereNotIn' => $condition->whereNotIn(...$arguments),
            'whereNot' => $condition->whereNot(...$arguments),
            'whereContains' => $condition->whereContains(...$arguments),
            'whereStartsWith' => $condition->whereStartsWith(...$arguments),
            'whereEndsWith' => $condition->whereEndsWith(...$arguments),
            'whereNull' => $condition->whereNull(...$arguments),
            'whereNotNull' => $condition->whereNotNull(...$arguments),
            default => throw new BadMethodCallException("Cannot start a condition with {$method}()."),
        };
    }

    public static function else(): ConditionBuilder
    {
        return Condition::always();
    }

    public static function always(): ConditionBuilder
    {
        return new ConditionBuilder;
    }

    /**
     * @param  list<array<string, mixed>>  $clauses
     */
    public static function fromClauses(array $clauses): ConditionBuilder
    {
        return ConditionBuilder::fromClauses($clauses);
    }
}
