<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

class ConditionEvaluator
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function evaluate(ConditionBuilder $condition, array $data): EvaluationResult
    {
        if ($condition->isEmpty()) {
            return new EvaluationResult(matched: true, score: 0);
        }

        return $this->evaluateClauses($condition->getClauses(), $data);
    }

    /**
     * @param  list<array<string, mixed>>  $clauses
     * @param  array<array-key, mixed>  $data
     */
    private function evaluateClauses(array $clauses, array $data): EvaluationResult
    {
        if ($clauses === []) {
            return new EvaluationResult(matched: true, score: 0);
        }

        $first = $this->evaluateClause($clauses[0], $data);

        return collect($clauses)
            ->skip(1)
            ->reduce(
                fn (EvaluationResult $carry, array $clause) => $this->combineResults(
                    $carry,
                    $this->evaluateClause($clause, $data),
                    $clause['boolean'],
                ),
                $first,
            );
    }

    /**
     * A side that failed scores nothing, however many of its own clauses matched: a failed AND
     * group scores 0, and an OR scores its best side that matched, since that path is the most specific.
     */
    private function combineResults(EvaluationResult $carry, EvaluationResult $current, string $boolean): EvaluationResult
    {
        if ($boolean === 'and') {
            return $carry->matched && $current->matched
                ? new EvaluationResult(matched: true, score: $carry->score + $current->score)
                : new EvaluationResult(matched: false, score: 0);
        }

        return new EvaluationResult(
            matched: $carry->matched || $current->matched,
            score: max($carry->matched ? $carry->score : 0, $current->matched ? $current->score : 0),
        );
    }

    /**
     * @param  array<string, mixed>  $clause
     * @param  array<array-key, mixed>  $data
     */
    private function evaluateClause(array $clause, array $data): EvaluationResult
    {
        if ($clause['type'] === 'nested') {
            return $this->evaluateClauses($clause['condition']->getClauses(), $data);
        }

        $field = $clause['field'];
        $operator = $clause['operator'];
        $expected = $clause['value'];

        if (! array_key_exists($field, $data)) {
            return new EvaluationResult(matched: false, score: 0);
        }

        $actual = $data[$field];
        $matched = $this->compare($actual, $operator, $expected);

        return new EvaluationResult(
            matched: $matched,
            score: $matched ? 1 : 0,
        );
    }

    private function compare(mixed $actual, Operator $operator, mixed $expected): bool
    {
        return match ($operator) {
            Operator::Equals => $actual === $expected,
            Operator::NotEquals => $actual !== $expected,
            Operator::GreaterThan => $actual > $expected,
            Operator::GreaterThanOrEquals => $actual >= $expected,
            Operator::LessThan => $actual < $expected,
            Operator::LessThanOrEquals => $actual <= $expected,
            Operator::In => in_array($actual, (array) $expected, true),
            Operator::NotIn => ! in_array($actual, (array) $expected, true),
            Operator::Contains => str_contains((string) $actual, (string) $expected),
            Operator::StartsWith => str_starts_with((string) $actual, (string) $expected),
            Operator::EndsWith => str_ends_with((string) $actual, (string) $expected),
        };
    }
}
