<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use Closure;
use InvalidArgumentException;

final class ConditionBuilder
{
    /** @var list<array<string, mixed>> */
    private array $clauses = [];

    /**
     * @param  list<array<string, mixed>>  $clauses
     */
    public static function fromClauses(array $clauses): self
    {
        $builder = new self;
        $builder->clauses = $clauses;

        return $builder;
    }

    public function where(string|Closure $field, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if ($field instanceof Closure) {
            return $this->addNestedClause($boolean, $field);
        }

        // Detect 2-param shorthand vs 3-param explicit mode
        if (is_null($value)) {
            // If operator is null: where('field', null) → equals null
            if (is_null($operator)) {
                return $this->addClause($boolean, $field, Operator::Equals, null);
            }

            // If operator is an Operator enum: where('field', Operator::Equals, null)
            if ($operator instanceof Operator) {
                return $this->addClause($boolean, $field, $operator, null);
            }

            // Check if it's a valid operator string: where('field', '=', null)
            if (is_string($operator)) {
                $parsedOperator = Operator::tryFromString($operator);

                if (filled($parsedOperator)) {
                    return $this->addClause($boolean, $field, $parsedOperator, null);
                }
            }

            // Not a valid operator, assume 2-param shorthand: where('field', 'value')
            return $this->addClause($boolean, $field, Operator::Equals, $operator);
        }

        // 3-param mode with non-null value
        throw_if(blank($operator), InvalidArgumentException::class, 'Operator is required.');

        $operator = $operator instanceof Operator ? $operator : Operator::fromString($operator);

        return $this->addClause($boolean, $field, $operator, $value);
    }

    public function orWhere(string|Closure $field, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($field, $operator, $value, 'or');
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereIn(string $field, array $values, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::In, $values, $boolean);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function orWhereIn(string $field, array $values): self
    {
        return $this->whereIn($field, $values, 'or');
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereNotIn(string $field, array $values, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::NotIn, $values, $boolean);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function orWhereNotIn(string $field, array $values): self
    {
        return $this->whereNotIn($field, $values, 'or');
    }

    public function whereNot(string $field, mixed $value, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::NotEquals, $value, $boolean);
    }

    public function orWhereNot(string $field, mixed $value): self
    {
        return $this->whereNot($field, $value, 'or');
    }

    public function whereContains(string $field, string $value, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::Contains, $value, $boolean);
    }

    public function orWhereContains(string $field, string $value): self
    {
        return $this->whereContains($field, $value, 'or');
    }

    public function whereStartsWith(string $field, string $value, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::StartsWith, $value, $boolean);
    }

    public function orWhereStartsWith(string $field, string $value): self
    {
        return $this->whereStartsWith($field, $value, 'or');
    }

    public function whereEndsWith(string $field, string $value, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::EndsWith, $value, $boolean);
    }

    public function orWhereEndsWith(string $field, string $value): self
    {
        return $this->whereEndsWith($field, $value, 'or');
    }

    public function whereNull(string $field, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::Equals, null, $boolean);
    }

    public function orWhereNull(string $field): self
    {
        return $this->whereNull($field, 'or');
    }

    public function whereNotNull(string $field, string $boolean = 'and'): self
    {
        return $this->where($field, Operator::NotEquals, null, $boolean);
    }

    public function orWhereNotNull(string $field): self
    {
        return $this->whereNotNull($field, 'or');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getClauses(): array
    {
        return $this->clauses;
    }

    public function isEmpty(): bool
    {
        return $this->clauses === [];
    }

    private function addClause(string $boolean, string $field, Operator $operator, mixed $value): self
    {
        $this->clauses[] = [
            'type' => 'basic',
            'boolean' => $boolean,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    private function addNestedClause(string $boolean, Closure $callback): self
    {
        $nested = new self;
        $callback($nested);

        $this->clauses[] = [
            'type' => 'nested',
            'boolean' => $boolean,
            'condition' => $nested,
        ];

        return $this;
    }
}
