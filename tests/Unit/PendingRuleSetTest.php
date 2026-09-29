<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\PendingRuleSet;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\RuleList;
use LeeOvery\RulesEngine\Tests\Fixtures\Merchant;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

it('takes its name from a string or a backed enum', function (): void {
    expect(new PendingRuleSet('templates')->getName())->toBe('templates')
        ->and(new PendingRuleSet(TestRuleSet::DividendRates)->getName())->toBe('uk.dividend-rates');
});

it('starts empty', function (): void {
    $pending = new PendingRuleSet('templates');

    expect($pending->getRules())->toBe([])
        ->and($pending->getEdits())->toBe([])
        ->and($pending->getRevertVersion())->toBeNull()
        ->and($pending->getDescription())->toBeNull()
        ->and($pending->getEffectiveFrom())->toBeNull()
        ->and($pending->getPublishedBy())->toBeNull()
        ->and($pending->getNote())->toBeNull();
});

it('keeps what is set on it', function (): void {
    $pending = new PendingRuleSet('templates')
        ->description('Template selection')
        ->effectiveFrom('2026-04-06 15:30:00')
        ->publishedBy('lee')
        ->note('Budget 2026');

    expect($pending->getDescription())->toBe('Template selection')
        ->and($pending->getEffectiveFrom()?->toDateTimeString())->toBe('2026-04-06 00:00:00')
        ->and($pending->getPublishedBy())->toBe('lee')
        ->and($pending->getNote())->toBe('Budget 2026');
});

it('keeps listed rules in order, with keys from strings or backed enums', function (): void {
    $condition = Condition::where('merchant', 'TESCO');

    $rules = new PendingRuleSet('templates')
        ->rule(Merchant::Tesco, $condition, 'groceries', ['priority' => 10])
        ->rule('fallback', Condition::always(), 'uncategorised')
        ->getRules();

    expect($rules)->toHaveCount(2)
        ->and($rules[0]->key)->toBe('tesco')
        ->and($rules[0]->condition)->toBe($condition)
        ->and($rules[0]->value)->toBe('groceries')
        ->and($rules[0]->metadata)->toBe(['priority' => 10])
        ->and($rules[0]->uuid)->toBeNull()
        ->and($rules[1]->key)->toBe('fallback');
});

it('keeps edits to apply in order when publishing', function (): void {
    $edits = new PendingRuleSet('templates')
        ->addRule('aldi', Condition::where('merchant', 'ALDI'), 'groceries')
        ->renameRule(Merchant::Aldi, 'aldi-stores')
        ->getEdits();

    $rules = array_reduce($edits, fn (RuleList $rules, Closure $edit): RuleList => $edit($rules), new RuleList('templates'));

    expect($edits)->toHaveCount(2)
        ->and(array_map(fn (RuleDefinition $rule): string => $rule->key, $rules->all()))->toBe(['aldi-stores']);
});

it('returns itself from each builder method', function (): void {
    $pending = new PendingRuleSet('templates');
    $edits = new PendingRuleSet('templates');

    expect($pending->description('x'))->toBe($pending)
        ->and($pending->effectiveFrom('2026-04-06'))->toBe($pending)
        ->and($pending->rule('x', Condition::always(), 'x'))->toBe($pending)
        ->and($pending->publishedBy('lee'))->toBe($pending)
        ->and($pending->note('x'))->toBe($pending)
        ->and($edits->addRule('x', Condition::always(), 'x'))->toBe($edits)
        ->and($edits->updateRule('x', value: 'y'))->toBe($edits)
        ->and($edits->moveRule('x', after: 'y'))->toBe($edits)
        ->and($edits->renameRule('x', 'z'))->toBe($edits)
        ->and($edits->removeRule('z'))->toBe($edits);
});
