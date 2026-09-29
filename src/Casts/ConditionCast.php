<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\ConditionBuilder;
use LeeOvery\RulesEngine\ConditionParser;

/**
 * @implements CastsAttributes<ConditionBuilder, ConditionBuilder|array<array-key, mixed>>
 */
final class ConditionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ConditionBuilder
    {
        if (blank($value)) {
            return Condition::always();
        }

        return (new ConditionParser)->fromJson($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof ConditionBuilder) {
            return (new ConditionParser)->toJson($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
