<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\ConditionBuilder;
use LeeOvery\RulesEngine\Operator;

it('creates condition with static where', function (): void {
    $condition = Condition::where('field', 'value');

    $clauses = $condition->getClauses();

    expect($clauses)->toHaveCount(1);
    expect($clauses[0]['type'])->toBe('basic');
    expect($clauses[0]['boolean'])->toBe('and');
    expect($clauses[0]['field'])->toBe('field');
    expect($clauses[0]['operator'])->toBe(Operator::Equals);
    expect($clauses[0]['value'])->toBe('value');
});

it('defaults to equals operator with 2 params', function (): void {
    $condition = Condition::where('status', 'active');

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::Equals);
    expect($clauses[0]['value'])->toBe('active');
});

it('accepts operator as string', function (): void {
    $condition = Condition::where('amount', '>', 100);

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::GreaterThan);
    expect($clauses[0]['value'])->toBe(100);
});

it('accepts operator as enum', function (): void {
    $condition = Condition::where('amount', Operator::GreaterThan, 100);

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::GreaterThan);
});

it('chains where clauses with and', function (): void {
    $condition = Condition::where('a', 1)
        ->where('b', 2)
        ->where('c', 3);

    $clauses = $condition->getClauses();

    expect($clauses)->toHaveCount(3);
    expect($clauses[0]['boolean'])->toBe('and');
    expect($clauses[1]['boolean'])->toBe('and');
    expect($clauses[2]['boolean'])->toBe('and');
});

it('chains orWhere clauses with or', function (): void {
    $condition = Condition::where('status', 'active')
        ->orWhere('status', 'pending');

    $clauses = $condition->getClauses();

    expect($clauses)->toHaveCount(2);
    expect($clauses[0]['boolean'])->toBe('and');
    expect($clauses[1]['boolean'])->toBe('or');
});

it('supports nested conditions with closure', function (): void {
    $condition = Condition::where('type', 'premium')
        ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'US'));

    $clauses = $condition->getClauses();

    expect($clauses)->toHaveCount(2);
    expect($clauses[0]['type'])->toBe('basic');
    expect($clauses[1]['type'])->toBe('nested');
    expect($clauses[1]['condition'])->toBeInstanceOf(ConditionBuilder::class);
    expect($clauses[1]['condition']->getClauses())->toHaveCount(2);
});

it('creates empty condition with always', function (): void {
    $condition = Condition::always();

    expect($condition->isEmpty())->toBeTrue();
    expect($condition->getClauses())->toBe([]);
});

it('reports isEmpty correctly', function (): void {
    expect(Condition::always()->isEmpty())->toBeTrue();
    expect(Condition::where('a', 1)->isEmpty())->toBeFalse();
});

it('creates condition from clauses', function (): void {
    $original = Condition::where('a', 1)->where('b', 2);
    $clauses = $original->getClauses();

    $restored = Condition::fromClauses($clauses);

    expect($restored->getClauses())->toBe($clauses);
});

it('throws for invalid operator string', function (): void {
    Condition::where('field', 'invalid_op', 'value');
})->throws(InvalidArgumentException::class, 'Unknown operator: invalid_op');

it('adds whereIn clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereIn('status', ['active', 'pending']);

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::In);
    expect($clauses[1]['value'])->toBe(['active', 'pending']);
    expect($clauses[1]['boolean'])->toBe('and');
});

it('adds orWhereIn clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereIn('status', ['active', 'pending']);

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::In);
    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereNotIn clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereNotIn('status', ['deleted', 'archived']);

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotIn);
});

it('adds orWhereNotIn clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereNotIn('status', ['deleted']);

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotIn);
    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereNot clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereNot('status', 'deleted');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotEquals);
    expect($clauses[1]['value'])->toBe('deleted');
});

it('adds orWhereNot clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereNot('status', 'deleted');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotEquals);
    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereContains clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereContains('email', '@example.com');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::Contains);
    expect($clauses[1]['value'])->toBe('@example.com');
});

it('adds orWhereContains clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereContains('email', '@example.com');

    $clauses = $condition->getClauses();

    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereStartsWith clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereStartsWith('code', 'PRE_');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::StartsWith);
});

it('adds orWhereStartsWith clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereStartsWith('code', 'PRE_');

    $clauses = $condition->getClauses();

    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereEndsWith clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereEndsWith('file', '.pdf');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::EndsWith);
});

it('adds orWhereEndsWith clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereEndsWith('file', '.pdf');

    $clauses = $condition->getClauses();

    expect($clauses[1]['boolean'])->toBe('or');
});

it('throws when starting with orWhere', function (): void {
    Condition::orWhere('field', 'value');
})->throws(BadMethodCallException::class, 'Cannot start a condition with orWhere()');

it('throws when starting with invalid method', function (): void {
    // @phpstan-ignore staticMethod.notFound (The mistake under test.)
    Condition::invalidMethod('field', 'value');
})->throws(BadMethodCallException::class, 'Cannot start a condition with invalidMethod()');

it('throws when operator is blank', function (): void {
    Condition::where('field', null, 'value');
})->throws(InvalidArgumentException::class, 'Operator is required');

it('allows null value with 2-param shorthand', function (): void {
    $condition = Condition::where('deleted_at');

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::Equals);
    expect($clauses[0]['value'])->toBeNull();
});

it('allows null value with explicit operator string', function (): void {
    $condition = Condition::where('deleted_at', '=');

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::Equals);
    expect($clauses[0]['value'])->toBeNull();
});

it('allows null value with explicit operator enum', function (): void {
    $condition = Condition::where('deleted_at', Operator::NotEquals);

    $clauses = $condition->getClauses();

    expect($clauses[0]['operator'])->toBe(Operator::NotEquals);
    expect($clauses[0]['value'])->toBeNull();
});

it('adds whereNull clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereNull('deleted_at');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::Equals);
    expect($clauses[1]['value'])->toBeNull();
    expect($clauses[1]['boolean'])->toBe('and');
});

it('adds orWhereNull clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereNull('deleted_at');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::Equals);
    expect($clauses[1]['value'])->toBeNull();
    expect($clauses[1]['boolean'])->toBe('or');
});

it('adds whereNotNull clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->whereNotNull('verified_at');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotEquals);
    expect($clauses[1]['value'])->toBeNull();
    expect($clauses[1]['boolean'])->toBe('and');
});

it('adds orWhereNotNull clause', function (): void {
    $condition = Condition::where('type', 'user')
        ->orWhereNotNull('verified_at');

    $clauses = $condition->getClauses();

    expect($clauses[1]['operator'])->toBe(Operator::NotEquals);
    expect($clauses[1]['value'])->toBeNull();
    expect($clauses[1]['boolean'])->toBe('or');
});
