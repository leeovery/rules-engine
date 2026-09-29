<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\RuleList;
use LeeOvery\RulesEngine\Unchanged;

function listedRule(string $key): RuleDefinition
{
    return new RuleDefinition($key, Condition::where('merchant', strtoupper($key)), $key, uuid: "uuid-{$key}");
}

/** @return list<string> */
function keysIn(RuleList $rules): array
{
    return array_map(fn (RuleDefinition $rule): string => $rule->key, $rules->all());
}

beforeEach(function (): void {
    $this->rules = new RuleList('merchant-categories', [listedRule('tesco'), listedRule('shell'), listedRule('pret')]);
});

it('finds a rule by key', function (): void {
    expect($this->rules->find('shell')?->uuid)->toBe('uuid-shell')
        ->and($this->rules->find('lidl'))->toBeNull();
});

it('adds at the end, before or after a rule, leaving the original list alone', function (): void {
    expect(keysIn($this->rules->add(listedRule('aldi'))))->toBe(['tesco', 'shell', 'pret', 'aldi'])
        ->and(keysIn($this->rules->add(listedRule('aldi'), before: 'tesco')))->toBe(['aldi', 'tesco', 'shell', 'pret'])
        ->and(keysIn($this->rules->add(listedRule('aldi'), after: 'pret')))->toBe(['tesco', 'shell', 'pret', 'aldi'])
        ->and(keysIn($this->rules))->toBe(['tesco', 'shell', 'pret']);
});

it('updates only what it is given', function (): void {
    $shell = $this->rules->update('shell', Unchanged::Keep, 'fuel', Unchanged::Keep)->find('shell');

    expect($shell?->value)->toBe('fuel')
        ->and($shell?->uuid)->toBe('uuid-shell')
        ->and($shell?->condition->getClauses()[0]['value'])->toBe('SHELL')
        ->and($shell?->metadata)->toBeNull();
});

it('moves a rule before or after another', function (): void {
    expect(keysIn($this->rules->move('pret', before: 'tesco')))->toBe(['pret', 'tesco', 'shell'])
        ->and(keysIn($this->rules->move('tesco', after: 'pret')))->toBe(['shell', 'pret', 'tesco'])
        ->and(keysIn($this->rules->move('tesco', after: 'shell')))->toBe(['shell', 'tesco', 'pret']);
});

it('renames a rule in place', function (): void {
    $renamed = $this->rules->rename('shell', 'shell-garages');

    expect(keysIn($renamed))->toBe(['tesco', 'shell-garages', 'pret'])
        ->and($renamed->find('shell-garages')?->uuid)->toBe('uuid-shell');
});

it('removes a rule', function (): void {
    expect(keysIn($this->rules->remove('shell')))->toBe(['tesco', 'pret']);
});

it('refuses keys that are missing or taken', function (Closure $edit, string $message): void {
    expect(fn () => $edit($this->rules))->toThrow(CannotPublishRuleSetException::class, $message);
})->with([
    'adding a key that exists' => [fn (RuleList $rules) => $rules->add(listedRule('shell')), "already has a rule 'shell'"],
    'adding next to a missing rule' => [fn (RuleList $rules) => $rules->add(listedRule('aldi'), before: 'lidl'), "has no rule 'lidl'"],
    'updating a missing rule' => [fn (RuleList $rules) => $rules->update('lidl', Unchanged::Keep, 'x', Unchanged::Keep), "has no rule 'lidl'"],
    'moving a missing rule' => [fn (RuleList $rules) => $rules->move('lidl', after: 'tesco'), "has no rule 'lidl'"],
    'moving next to a missing rule' => [fn (RuleList $rules) => $rules->move('tesco', after: 'lidl'), "has no rule 'lidl'"],
    'moving next to itself' => [fn (RuleList $rules) => $rules->move('tesco', before: 'tesco'), "can't move before or after itself"],
    'renaming a missing rule' => [fn (RuleList $rules) => $rules->rename('lidl', 'aldi'), "has no rule 'lidl'"],
    'renaming to a taken key' => [fn (RuleList $rules) => $rules->rename('tesco', 'pret'), "already has a rule 'pret'"],
    'removing a missing rule' => [fn (RuleList $rules) => $rules->remove('lidl'), "has no rule 'lidl'"],
]);
