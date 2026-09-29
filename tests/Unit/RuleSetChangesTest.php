<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\RenamedRule;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\RuleSetChanges;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;

/**
 * @param  array<array-key, mixed>|null  $metadata
 */
function definition(string $key, mixed $value = 'value', ?array $metadata = null, ?string $uuid = null, string $merchant = ''): RuleDefinition
{
    return new RuleDefinition($key, Condition::where('merchant', $merchant === '' ? $key : $merchant), $value, $metadata, $uuid ?? "uuid-{$key}");
}

/** @return list<RuleDefinition> */
function definitionsFor(string ...$keys): array
{
    return array_map(fn (string $key): RuleDefinition => definition($key), array_values($keys));
}

/** @return list<string> */
function movedKeys(RuleSetChanges $changes): array
{
    return array_map(fn (RuleReference $rule): string => $rule->key, $changes->moved);
}

it('lists every rule as added when there was nothing before', function (): void {
    $changes = RuleSetChanges::between([], definitionsFor('a', 'b'));

    expect($changes->added)->toEqual([new RuleReference('uuid-a', 'a'), new RuleReference('uuid-b', 'b')])
        ->and($changes->removed)->toBe([])
        ->and($changes->moved)->toBe([])
        ->and($changes->isEmpty())->toBeFalse();
});

it('finds nothing between identical lists', function (): void {
    expect(RuleSetChanges::between(definitionsFor('a', 'b'), definitionsFor('a', 'b'))->isEmpty())->toBeTrue()
        ->and(new RuleSetChanges()->isEmpty())->toBeTrue();
});

it('matches rules by uuid, not key', function (): void {
    $changes = RuleSetChanges::between([definition('a')], [definition('a', uuid: 'uuid-new')]);

    expect($changes->added)->toEqual([new RuleReference('uuid-new', 'a')])
        ->and($changes->removed)->toEqual([new RuleReference('uuid-a', 'a')])
        ->and($changes->renamed)->toBe([]);
});

it('lists renames, and changes to conditions, values and metadata', function (): void {
    $changes = RuleSetChanges::between(
        [definition('a'), definition('b'), definition('c'), definition('d')],
        [
            definition('a-renamed', uuid: 'uuid-a', merchant: 'a'),
            definition('b', merchant: 'b-changed'),
            definition('c', value: 'changed'),
            definition('d', metadata: ['owner' => 'lee']),
        ],
    );

    expect($changes->renamed)->toEqual([new RenamedRule('uuid-a', 'a', 'a-renamed')])
        ->and($changes->conditionChanged)->toEqual([new RuleReference('uuid-b', 'b')])
        ->and($changes->valueChanged)->toEqual([new RuleReference('uuid-c', 'c')])
        ->and($changes->metadataChanged)->toEqual([new RuleReference('uuid-d', 'd')]);
});

it('compares values as stored, so an enum equals its backing value', function (): void {
    $changes = RuleSetChanges::between(
        [definition('a', value: 'groceries'), definition('b', value: 1.0)],
        [definition('a', value: Category::Groceries), definition('b', value: 1)],
    );

    expect(array_map(fn (RuleReference $rule): string => $rule->key, $changes->valueChanged))->toBe(['b']);
});

it('lists the fewest rules that moved', function (array $after, array $moved): void {
    expect(movedKeys(RuleSetChanges::between(definitionsFor('a', 'b', 'c', 'd', 'e'), definitionsFor(...$after))))->toBe($moved);
})->with([
    'one moved to the front' => [['e', 'a', 'b', 'c', 'd'], ['e']],
    'one moved to the end' => [['b', 'c', 'd', 'e', 'a'], ['a']],
    'one moved into the middle' => [['b', 'c', 'a', 'd', 'e'], ['a']],
    'two moved' => [['e', 'b', 'c', 'd', 'a'], ['e', 'a']],
    'reversed' => [['e', 'd', 'c', 'b', 'a'], ['d', 'c', 'b', 'a']],
    'rules added and removed around the rest' => [['x', 'a', 'c', 'y', 'e'], []],
]);
