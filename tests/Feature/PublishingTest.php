<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\Merchant;
use LeeOvery\RulesEngine\Tests\Fixtures\Priority;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

it('publishes version 1 of a new rule set', function (): void {
    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->description('Categories by merchant')
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publishedBy('lee')
        ->note('First cut')
        ->publish();

    expect($version->version)->toBe(1)
        ->and($version->effective_from)->toBeNull()
        ->and($version->published_by)->toBe('lee')
        ->and($version->note)->toBe('First cut')
        ->and($version->ruleSet->name)->toBe('merchant-categories')
        ->and($version->ruleSet->description)->toBe('Categories by merchant');
});

it('keeps the rules in the order they are listed, with their keys', function (): void {
    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries, ['owner' => 'lee'])
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $rules = RuleSetVersion::query()->findOrFail($version->id)->rules;

    expect($rules->pluck('key', 'position')->all())->toBe([1 => 'tesco', 2 => 'shell', 3 => 'fallback'])
        ->and($rules->pluck('value', 'key')->all())->toBe(['tesco' => 'groceries', 'shell' => 'fuel', 'fallback' => 'uncategorised'])
        ->and($rules->firstOrFail()->metadata)->toBe(['owner' => 'lee'])
        ->and($rules->sole('key', 'fallback')->metadata)->toBeNull();
});

it('takes rule keys from backed enums', function (): void {
    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule(Merchant::Tesco, Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule(Priority::High, Condition::always(), Category::Uncategorised)
        ->publish();

    expect($version->rules->pluck('key')->all())->toBe(['tesco', '2']);
});

it('gives every rule a uuid7', function (): void {
    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $uuids = RuleSetVersion::query()->findOrFail($version->id)->rules->pluck('uuid');

    expect($uuids->unique())->toHaveCount(2)
        ->and($uuids->every(fn (string $uuid): bool => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)))->toBeTrue();
});

it('keeps a rule\'s uuid when the next list uses its key, and gives new keys new uuids', function (): void {
    $first = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $second = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('tesco', Condition::where('merchant', 'TESCO STORES'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $before = $first->rules->pluck('uuid', 'key');
    $after = $second->rules->pluck('uuid', 'key');

    expect($after['tesco'])->toBe($before['tesco'])
        ->and($after['fallback'])->toBe($before['fallback'])
        ->and($before->values())->not->toContain($after['shell']);
});

it('gives a key a new uuid when it comes back after being dropped', function (): void {
    $first = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->publish();

    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->publish();

    $third = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->publish();

    expect($third->rules->sole()->uuid)->not->toBe($first->rules->sole()->uuid);
});

it('records the moment a version was published', function (): void {
    $this->travelTo('2026-09-28 10:15:30.123456');

    $version = publishUndated('templates', 'template_a');

    expect(RuleSetVersion::query()->findOrFail($version->id)->published_at->format('Y-m-d H:i:s.u'))
        ->toBe('2026-09-28 10:15:30.123456');
});

it('publishes each list as the next version, leaving earlier versions as they were', function (): void {
    RulesEngine::ruleSet('templates')
        ->rule('a', Condition::where('category', 'A'), 'template_a')
        ->rule('default', Condition::always(), 'template_default')
        ->publish();

    $second = RulesEngine::ruleSet('templates')
        ->rule('new', Condition::always(), 'template_new')
        ->publish();

    expect($second->version)->toBe(2)
        ->and($second->rules->pluck('value')->all())->toBe(['template_new'])
        ->and(RulesEngine::version('templates', 1)->rules->pluck('value')->all())->toBe(['template_a', 'template_default'])
        ->and(RuleSet::query()->count())->toBe(1);
});

it('names rule sets with a string-backed or int-backed enum', function (): void {
    expect(publishUndated(TestRuleSet::PersonalTransfers, 'loan')->ruleSet->name)->toBe('personal-transfers')
        ->and(publishUndated(Priority::High, 'urgent')->ruleSet->name)->toBe('2');
});

it('keeps the description the set was created with', function (): void {
    RulesEngine::ruleSet('templates')->description('Original')->rule('a', Condition::always(), 'a')->publish();

    RulesEngine::ruleSet('templates')->description('Ignored')->rule('b', Condition::always(), 'b')->publish();

    expect(RuleSet::query()->sole()->description)->toBe('Original');
});

it('keeps only the calendar date of an effective date', function (): void {
    $effectiveFrom = new DateTimeImmutable('2026-04-06 00:30:00', new DateTimeZone('Europe/London'));

    $version = RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom($effectiveFrom)
        ->rule('rates', Condition::always(), ['allowance' => 50000])
        ->publish();

    expect(RuleSetVersion::query()->findOrFail($version->id)->effective_from?->toDateString())->toBe('2026-04-06');
});

it('stores values as JSON and reads them back unchanged', function (mixed $value): void {
    publishUndated('values', $value);

    expect(Rule::query()->sole()->value)->toBe($value);
})->with([
    'string' => 'template_a',
    'integer' => 50000,
    'float' => 0.1075,
    'whole float' => 1.0,
    'boolean' => false,
    'null' => null,
    'list' => [['a', 'b', 'c']],
    'map' => [['allowance' => 50000, 'ordinary' => '0.1075', 'bands' => ['basic' => 3770000]]],
]);

it('stores a backed enum value as its backing value', function (): void {
    publishUndated(TestRuleSet::PersonalTransfers, ['purpose' => TransferPurpose::Loan, 'fallback' => TransferPurpose::Gift]);

    expect(Rule::query()->sole()->value)->toBe(['purpose' => 'loan', 'fallback' => 'gift']);
});

it('returns the published version with its rules', function (): void {
    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->publish();

    expect($version->relationLoaded('rules'))->toBeTrue()
        ->and($version->rules->sole()->value)->toBe('groceries')
        ->and($version->rules->sole()->key)->toBe('tesco')
        ->and($version->rules->sole()->position)->toBe(1);
});
