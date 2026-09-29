<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\JsonValue;
use LeeOvery\RulesEngine\Tests\Fixtures\Colour;
use LeeOvery\RulesEngine\Tests\Fixtures\Priority;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

describe('normalise', function (): void {
    it('turns backed enums into their values, however deep', function (): void {
        expect(JsonValue::normalise(TransferPurpose::Loan))->toBe('loan')
            ->and(JsonValue::normalise(['a' => Priority::High, 'b' => [TransferPurpose::Gift, 3]]))->toBe(['a' => 2, 'b' => ['gift', 3]]);
    });

    it('leaves everything else alone', function (): void {
        $object = new stdClass;

        expect(JsonValue::normalise('loan'))->toBe('loan')
            ->and(JsonValue::normalise(null))->toBeNull()
            ->and(JsonValue::normalise(['object' => $object]))->toBe(['object' => $object]);
    });
});

describe('unsafeTypeIn', function (): void {
    it('accepts arrays, scalars, null and backed enums', function (mixed $value): void {
        expect(JsonValue::unsafeTypeIn($value))->toBeNull();
    })->with([
        'string' => 'loan',
        'integer' => 42,
        'float' => 0.1075,
        'boolean' => true,
        'null' => [null],
        'backed enum' => TransferPurpose::Loan,
        'nested array' => [['a' => [1, 'b', [TransferPurpose::Gift, null]]]],
    ]);

    it('names the first thing JSON cannot hold', function (mixed $value, string $type): void {
        expect(JsonValue::unsafeTypeIn($value))->toBe($type);
    })->with([
        'closure' => [fn (): int => 1, 'Closure'],
        'object' => [new stdClass, 'stdClass'],
        'pure enum' => [Colour::Red, Colour::class],
        'infinite float' => [INF, 'INF'],
        'not a number' => [NAN, 'NAN'],
        'deep in an array' => [['a' => ['b' => [1, new ArrayObject]]], 'ArrayObject'],
    ]);
});
