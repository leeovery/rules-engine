<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

final class ConditionParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(ConditionBuilder $condition): array
    {
        return $this->serializeClauses($condition->getClauses());
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    public function fromArray(array $data): ConditionBuilder
    {
        return ConditionBuilder::fromClauses($this->deserializeClauses($data));
    }

    public function toJson(ConditionBuilder $condition): string
    {
        return json_encode($this->toArray($condition), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public function fromJson(string $json): ConditionBuilder
    {
        return $this->fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $clauses
     * @return list<array<string, mixed>>
     */
    private function serializeClauses(array $clauses): array
    {
        return array_map($this->serializeClause(...), $clauses);
    }

    /**
     * @param  array<string, mixed>  $clause
     * @return array<string, mixed>
     */
    private function serializeClause(array $clause): array
    {
        if ($clause['type'] === 'nested') {
            return [
                'type' => 'nested',
                'boolean' => $clause['boolean'],
                'clauses' => $this->serializeClauses($clause['condition']->getClauses()),
            ];
        }

        return [
            'type' => 'basic',
            'boolean' => $clause['boolean'],
            'field' => $clause['field'],
            'operator' => $clause['operator']->value,
            'value' => $clause['value'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return list<array<string, mixed>>
     */
    private function deserializeClauses(array $data): array
    {
        return array_map($this->deserializeClause(...), $data);
    }

    /**
     * @param  array<string, mixed>  $clause
     * @return array<string, mixed>
     */
    private function deserializeClause(array $clause): array
    {
        if ($clause['type'] === 'nested') {
            return [
                'type' => 'nested',
                'boolean' => $clause['boolean'],
                'condition' => ConditionBuilder::fromClauses($this->deserializeClauses($clause['clauses'])),
            ];
        }

        return [
            'type' => 'basic',
            'boolean' => $clause['boolean'],
            'field' => $clause['field'],
            'operator' => Operator::from($clause['operator']),
            'value' => $clause['value'],
        ];
    }
}
