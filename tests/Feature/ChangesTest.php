<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Exceptions\RuleSetNotFoundException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\RenamedRule;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\RuleSetChanges;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

/**
 * @param  list<RuleReference>  $references
 * @return list<string>
 */
function referencedKeys(array $references): array
{
    return array_map(fn (RuleReference $reference): string => $reference->key, $references);
}

beforeEach(function (): void {
    $this->first = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries, ['owner' => 'lee'])
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('pret', Condition::where('merchant', 'PRET'), Category::EatingOut)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $this->uuids = $this->first->rules->pluck('uuid', 'key')->all();

    $this->changesAfter = function (Closure $edit): RuleSetChanges {
        $edit(RulesEngine::ruleSet(TestRuleSet::MerchantCategories))->publish();

        return RulesEngine::changes(TestRuleSet::MerchantCategories, from: 1, to: 2);
    };
});

it('lists added and removed rules', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending
        ->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries, before: 'tesco')
        ->removeRule('shell'));

    expect(referencedKeys($changes->added))->toBe(['aldi'])
        ->and($changes->removed)->toEqual([new RuleReference($this->uuids['shell'], 'shell')])
        ->and($changes->moved)->toBe([])
        ->and($changes->renamed)->toBe([])
        ->and($changes->valueChanged)->toBe([]);
});

it('lists renamed rules with their old and new keys', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending->renameRule('tesco', 'tesco-stores'));

    expect($changes->renamed)->toEqual([new RenamedRule($this->uuids['tesco'], 'tesco', 'tesco-stores')])
        ->and($changes->added)->toBe([])
        ->and($changes->removed)->toBe([]);
});

it('lists rules whose condition, value or metadata changed', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending
        ->updateRule('tesco', metadata: ['owner' => 'sam'])
        ->updateRule('shell', condition: Condition::whereStartsWith('merchant', 'SHELL'))
        ->updateRule('pret', value: Category::Groceries));

    expect(referencedKeys($changes->metadataChanged))->toBe(['tesco'])
        ->and(referencedKeys($changes->conditionChanged))->toBe(['shell'])
        ->and(referencedKeys($changes->valueChanged))->toBe(['pret'])
        ->and($changes->valueChanged[0]->uuid)->toBe($this->uuids['pret']);
});

it('lists the rule that moved, not the rules it passed', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending->moveRule('fallback', before: 'tesco'));

    expect($changes->moved)->toEqual([new RuleReference($this->uuids['fallback'], 'fallback')]);
});

it('lists every kind of change to one rule', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending
        ->renameRule('tesco', 'tesco-stores')
        ->updateRule('tesco-stores', value: Category::Fuel)
        ->moveRule('tesco-stores', after: 'pret'));

    expect($changes->renamed)->toEqual([new RenamedRule($this->uuids['tesco'], 'tesco', 'tesco-stores')])
        ->and(referencedKeys($changes->valueChanged))->toBe(['tesco-stores'])
        ->and(referencedKeys($changes->moved))->toBe(['tesco-stores']);
});

it('finds nothing when a version repeats its predecessor', function (): void {
    $changes = ($this->changesAfter)(fn ($pending) => $pending
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries, ['owner' => 'lee'])
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('pret', Condition::where('merchant', 'PRET'), Category::EatingOut)
        ->rule('fallback', Condition::always(), Category::Uncategorised));

    expect($changes->isEmpty())->toBeTrue();
});

it('compares any two versions, in either direction', function (): void {
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)->removeRule('shell')->publish();
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries)->publish();

    $forwards = RulesEngine::changes(TestRuleSet::MerchantCategories, from: 1, to: 3);
    $backwards = RulesEngine::changes('merchant-categories', from: 3, to: 1);

    expect(referencedKeys($forwards->added))->toBe(['aldi'])
        ->and(referencedKeys($forwards->removed))->toBe(['shell'])
        ->and(referencedKeys($backwards->added))->toBe(['shell'])
        ->and(referencedKeys($backwards->removed))->toBe(['aldi']);
});

it('needs both versions to exist', function (): void {
    RulesEngine::changes(TestRuleSet::MerchantCategories, from: 1, to: 2);
})->throws(NoApplicableVersionException::class, "Rule set 'merchant-categories' has no version 2.");

it('needs the rule set to exist', function (): void {
    RulesEngine::changes('missing', from: 1, to: 2);
})->throws(RuleSetNotFoundException::class);
