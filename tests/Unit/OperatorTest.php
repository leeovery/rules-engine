<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Operator;

it('has correct string value', function (Operator $operator, string $expected): void {
    expect($operator->value)->toBe($expected);
})->with([
    'Equals' => [Operator::Equals, '='],
    'NotEquals' => [Operator::NotEquals, '!='],
    'GreaterThan' => [Operator::GreaterThan, '>'],
    'GreaterThanOrEquals' => [Operator::GreaterThanOrEquals, '>='],
    'LessThan' => [Operator::LessThan, '<'],
    'LessThanOrEquals' => [Operator::LessThanOrEquals, '<='],
    'In' => [Operator::In, 'in'],
    'NotIn' => [Operator::NotIn, 'not_in'],
    'Contains' => [Operator::Contains, 'contains'],
    'StartsWith' => [Operator::StartsWith, 'starts_with'],
    'EndsWith' => [Operator::EndsWith, 'ends_with'],
]);

it('creates from string with fromString', function (string $input, Operator $expected): void {
    expect(Operator::fromString($input))->toBe($expected);
})->with([
    '=' => ['=', Operator::Equals],
    '==' => ['==', Operator::Equals],
    '!=' => ['!=', Operator::NotEquals],
    '<>' => ['<>', Operator::NotEquals],
    '>' => ['>', Operator::GreaterThan],
    '>=' => ['>=', Operator::GreaterThanOrEquals],
    '<' => ['<', Operator::LessThan],
    '<=' => ['<=', Operator::LessThanOrEquals],
    'in' => ['in', Operator::In],
    'not_in' => ['not_in', Operator::NotIn],
    'contains' => ['contains', Operator::Contains],
    'starts_with' => ['starts_with', Operator::StartsWith],
    'ends_with' => ['ends_with', Operator::EndsWith],
]);

it('throws for unknown operator in fromString', function (): void {
    Operator::fromString('unknown');
})->throws(InvalidArgumentException::class, 'Unknown operator: unknown');

it('creates from value with from', function (string $value, Operator $expected): void {
    expect(Operator::from($value))->toBe($expected);
})->with([
    '=' => ['=', Operator::Equals],
    '>' => ['>', Operator::GreaterThan],
    'in' => ['in', Operator::In],
]);

it('throws for invalid value with from', function (): void {
    Operator::from('==');
})->throws(ValueError::class);

it('returns operator from tryFromString for valid input', function (string $input, Operator $expected): void {
    expect(Operator::tryFromString($input))->toBe($expected);
})->with([
    '=' => ['=', Operator::Equals],
    '==' => ['==', Operator::Equals],
    '!=' => ['!=', Operator::NotEquals],
    '<>' => ['<>', Operator::NotEquals],
    '>' => ['>', Operator::GreaterThan],
    '>=' => ['>=', Operator::GreaterThanOrEquals],
    '<' => ['<', Operator::LessThan],
    '<=' => ['<=', Operator::LessThanOrEquals],
    'in' => ['in', Operator::In],
    'not_in' => ['not_in', Operator::NotIn],
    'contains' => ['contains', Operator::Contains],
    'starts_with' => ['starts_with', Operator::StartsWith],
    'ends_with' => ['ends_with', Operator::EndsWith],
]);

it('returns null from tryFromString for invalid input', function (): void {
    expect(Operator::tryFromString('unknown'))->toBeNull();
    expect(Operator::tryFromString('not_an_operator'))->toBeNull();
    expect(Operator::tryFromString(''))->toBeNull();
});
