<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Events\RuleSetVersionPublished;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

beforeEach(function (): void {
    $this->first = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries, ['owner' => 'lee'])
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->removeRule('shell')
        ->updateRule('tesco', value: Category::EatingOut)
        ->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries)
        ->publish();
});

it('publishes a copy of an earlier version as the next version', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->publishedBy('lee')
        ->note('Undo the shop changes')
        ->revertTo(1);

    $columns = fn (RuleSetVersion $version): array => $version->rules
        ->map(fn ($rule): array => [$rule->uuid, $rule->key, $rule->position, $rule->value, $rule->metadata])
        ->all();

    expect($version->version)->toBe(3)
        ->and($version->published_by)->toBe('lee')
        ->and($version->note)->toBe('Undo the shop changes')
        ->and($columns(RuleSetVersion::query()->findOrFail($version->id)))->toBe($columns($this->first));

    Event::assertDispatched(fn (RuleSetVersionPublished $event): bool => $event->version === 3
        && array_map(fn (RuleReference $rule): string => $rule->key, $event->changes->added) === ['shell']
        && array_map(fn (RuleReference $rule): string => $rule->key, $event->changes->removed) === ['aldi']
        && array_map(fn (RuleReference $rule): string => $rule->key, $event->changes->valueChanged) === ['tesco']);
});

it('takes the effective date of the new version for a dated set', function (): void {
    publishDated(TestRuleSet::DividendRates, '2025-04-06', ['ordinary' => '0.0875']);
    publishDated(TestRuleSet::DividendRates, '2026-04-06', ['ordinary' => '0.1075']);

    $version = RulesEngine::ruleSet(TestRuleSet::DividendRates)->effectiveFrom('2027-04-06')->revertTo(1);

    expect($version->effective_from?->toDateString())->toBe('2027-04-06')
        ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2027-06-01')->firstValue())->toBe(['ordinary' => '0.0875'])
        ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-06-01')->firstValue())->toBe(['ordinary' => '0.1075']);
});

it('follows the first version on effective dates', function (): void {
    publishDated(TestRuleSet::DividendRates, '2025-04-06', ['ordinary' => '0.0875']);

    RulesEngine::ruleSet(TestRuleSet::DividendRates)->revertTo(1);
})->throws(CannotPublishRuleSetException::class, 'every version needs one');

it('needs the version to exist', function (): void {
    expect(fn () => RulesEngine::ruleSet(TestRuleSet::MerchantCategories)->revertTo(5))
        ->toThrow(NoApplicableVersionException::class, "Rule set 'merchant-categories' has no version 5.");

    expect(RulesEngine::versions(TestRuleSet::MerchantCategories))->toHaveCount(2);
});

it('needs the rule set to exist', function (): void {
    expect(fn () => RulesEngine::ruleSet('missing')->revertTo(1))->toThrow(NoApplicableVersionException::class);

    expect(RuleSet::query()->whereName('missing')->exists())->toBeFalse();
});

it('can\'t be combined with rules or edits', function (Closure $changes): void {
    $changes(RulesEngine::ruleSet(TestRuleSet::MerchantCategories))->revertTo(1);
})->with([
    'a rule' => fn ($pending) => $pending->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries),
    'an edit' => fn ($pending) => $pending->removeRule('tesco'),
])->throws(
    CannotPublishRuleSetException::class,
    "Reverting rule set 'merchant-categories' publishes an earlier version as it was, so it can't be combined with rules or edits.",
);
