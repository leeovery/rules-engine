<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\ConditionBuilder;
use LeeOvery\RulesEngine\ConditionParser;
use LeeOvery\RulesEngine\Operator;

beforeEach(function (): void {
    $this->parser = new ConditionParser;
});

it('serializes simple condition to array', function (): void {
    $condition = Condition::where('status', 'active');

    $array = $this->parser->toArray($condition);

    expect($array)->toBe([
        [
            'type' => 'basic',
            'boolean' => 'and',
            'field' => 'status',
            'operator' => '=',
            'value' => 'active',
        ],
    ]);
});

it('serializes condition with operator to array', function (): void {
    $condition = Condition::where('amount', '>', 100);

    $array = $this->parser->toArray($condition);

    expect($array[0]['operator'])->toBe('>');
    expect($array[0]['value'])->toBe(100);
});

it('serializes multiple clauses', function (): void {
    $condition = Condition::where('a', 1)
        ->where('b', 2)
        ->orWhere('c', 3);

    $array = $this->parser->toArray($condition);

    expect($array)->toHaveCount(3);
    expect($array[0]['boolean'])->toBe('and');
    expect($array[1]['boolean'])->toBe('and');
    expect($array[2]['boolean'])->toBe('or');
});

it('serializes nested conditions', function (): void {
    $condition = Condition::where('type', 'premium')
        ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'US'));

    $array = $this->parser->toArray($condition);

    expect($array)->toHaveCount(2);
    expect($array[0]['type'])->toBe('basic');
    expect($array[1]['type'])->toBe('nested');
    expect($array[1]['clauses'])->toHaveCount(2);
    expect($array[1]['clauses'][0]['field'])->toBe('country');
    expect($array[1]['clauses'][1]['boolean'])->toBe('or');
});

it('deserializes array to condition', function (): void {
    $array = [
        [
            'type' => 'basic',
            'boolean' => 'and',
            'field' => 'status',
            'operator' => '=',
            'value' => 'active',
        ],
    ];

    $condition = $this->parser->fromArray($array);

    $clauses = $condition->getClauses();
    expect($clauses)->toHaveCount(1);
    expect($clauses[0]['field'])->toBe('status');
    expect($clauses[0]['operator'])->toBe(Operator::Equals);
    expect($clauses[0]['value'])->toBe('active');
});

it('deserializes nested conditions', function (): void {
    $array = [
        [
            'type' => 'basic',
            'boolean' => 'and',
            'field' => 'type',
            'operator' => '=',
            'value' => 'premium',
        ],
        [
            'type' => 'nested',
            'boolean' => 'and',
            'clauses' => [
                [
                    'type' => 'basic',
                    'boolean' => 'and',
                    'field' => 'country',
                    'operator' => '=',
                    'value' => 'UK',
                ],
            ],
        ],
    ];

    $condition = $this->parser->fromArray($array);
    $clauses = $condition->getClauses();

    expect($clauses)->toHaveCount(2);
    expect($clauses[1]['type'])->toBe('nested');
    expect($clauses[1]['condition'])->toBeInstanceOf(ConditionBuilder::class);
});

it('round trips condition through serialization', function (): void {
    $original = Condition::where('type', 'premium')
        ->where('amount', '>', 1000)
        ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'US'));

    $array = $this->parser->toArray($original);
    $restored = $this->parser->fromArray($array);

    expect($this->parser->toArray($restored))->toBe($array);
});

it('serializes empty condition', function (): void {
    $condition = Condition::always();

    $array = $this->parser->toArray($condition);

    expect($array)->toBe([]);
});

it('deserializes empty array to empty condition', function (): void {
    $condition = $this->parser->fromArray([]);

    expect($condition->isEmpty())->toBeTrue();
});

it('serializes condition to json', function (): void {
    $condition = Condition::where('status', 'active');

    $json = $this->parser->toJson($condition);

    expect($json)->toBe('[{"type":"basic","boolean":"and","field":"status","operator":"=","value":"active"}]');
});

it('deserializes json to condition', function (): void {
    $json = '[{"type":"basic","boolean":"and","field":"status","operator":"=","value":"active"}]';

    $condition = $this->parser->fromJson($json);

    expect($condition->getClauses())->toHaveCount(1);
    expect($condition->getClauses()[0]['field'])->toBe('status');
});

it('round trips condition through json', function (): void {
    $original = Condition::where('type', 'premium')
        ->where('amount', '>', 1000);

    $json = $this->parser->toJson($original);
    $restored = $this->parser->fromJson($json);

    expect($this->parser->toJson($restored))->toBe($json);
});

it('drops a strict flag from a stored clause', function (): void {
    $clause = [
        'type' => 'basic',
        'boolean' => 'and',
        'field' => 'status',
        'operator' => '=',
        'value' => 1,
    ];

    $condition = $this->parser->fromArray([[...$clause, 'strict' => false]]);

    expect($condition->getClauses()[0])->not->toHaveKey('strict')
        ->and($this->parser->toArray($condition))->toBe([$clause]);
});
