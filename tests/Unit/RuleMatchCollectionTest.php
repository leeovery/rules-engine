<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Exceptions\MultipleRulesMatchedException;
use LeeOvery\RulesEngine\Exceptions\NoMatchingRuleException;
use LeeOvery\RulesEngine\RuleMatch;
use LeeOvery\RulesEngine\RuleMatchCollection;

function matchWith(string $value, int $score, int $position = 1): RuleMatch
{
    return new RuleMatch('test_ruleset', 1, 1, $position, "uuid-{$position}", "rule-{$position}", $position, $value, $score);
}

it('returns first match', function (): void {
    $matches = new RuleMatchCollection([matchWith('first', 2), matchWith('second', 1, 2)], 'test_ruleset');

    expect($matches->first()?->value)->toBe('first');
});

it('returns first value', function (): void {
    $matches = new RuleMatchCollection([matchWith('first_value', 2)], 'test_ruleset');

    expect($matches->firstValue())->toBe('first_value');
});

it('returns null for firstValue when empty', function (): void {
    expect(new RuleMatchCollection([], 'test_ruleset')->firstValue())->toBeNull();
});

it('returns sole match when exactly one exists', function (): void {
    $matches = new RuleMatchCollection([matchWith('only_match', 1)], 'test_ruleset');

    expect($matches->sole()->value)->toBe('only_match')
        ->and($matches->soleValue())->toBe('only_match');
});

it('throws NoMatchingRuleException when sole called on empty collection', function (): void {
    new RuleMatchCollection([], 'my_ruleset')->sole();
})->throws(NoMatchingRuleException::class, "No matching rule found in RuleSet 'my_ruleset'.");

it('throws MultipleRulesMatchedException when sole called with multiple matches', function (): void {
    new RuleMatchCollection([matchWith('first', 2), matchWith('second', 1, 2)], 'my_ruleset')->sole();
})->throws(MultipleRulesMatchedException::class, 'Expected exactly one matching rule for rule set "my_ruleset", but found 2.');

it('throws BadMethodCallException when sole called with filter params', function (): void {
    new RuleMatchCollection([matchWith('match', 1)], 'test_ruleset')->sole('value', 'match');
})->throws(BadMethodCallException::class, 'Filter parameters are not supported');

it('converts its matches to arrays', function (): void {
    $matches = new RuleMatchCollection([matchWith('first', 2)], 'test_ruleset');

    expect($matches->toArray())->toBe([matchWith('first', 2)->toArray()]);
});
