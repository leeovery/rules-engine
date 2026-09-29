<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Events\RuleSetVersionPublished;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

beforeEach(function (): void {
    $this->first = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();
});

it('shows the rules and changes a publish would make, without publishing', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    $preview = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->addRule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel, after: 'tesco')
        ->updateRule('tesco', value: Category::EatingOut)
        ->preview();

    expect($preview->version)->toBe(2)
        ->and($preview->previousVersion)->toBe(1)
        ->and(array_map(fn (RuleDefinition $rule): array => [$rule->key, $rule->value], $preview->rules))->toBe([
            ['tesco', 'eating-out'],
            ['shell', 'fuel'],
            ['fallback', 'uncategorised'],
        ])
        ->and($preview->rules[0]->uuid)->toBe($this->first->rules->firstOrFail()->uuid)
        ->and(array_map(fn (RuleReference $rule): string => $rule->key, $preview->changes->added))->toBe(['shell'])
        ->and(array_map(fn (RuleReference $rule): string => $rule->key, $preview->changes->valueChanged))->toBe(['tesco'])
        ->and(RuleSetVersion::query()->count())->toBe(1);

    Event::assertNotDispatched(RuleSetVersionPublished::class);
});

it('previews the first version of a new set without creating it', function (): void {
    $preview = RulesEngine::ruleSet('templates')
        ->rule('a', Condition::where('category', 'A'), 'template_a')
        ->rule('default', Condition::always(), 'template_default')
        ->preview();

    expect($preview->version)->toBe(1)
        ->and($preview->previousVersion)->toBeNull()
        ->and(array_map(fn (RuleReference $rule): string => $rule->key, $preview->changes->added))->toBe(['a', 'default'])
        ->and(RuleSet::query()->whereName('templates')->exists())->toBeFalse();
});

it('refuses what publishing would refuse', function (): void {
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)->removeRule('lidl')->preview();
})->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");

it('matches what publishing then does', function (): void {
    $pending = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->moveRule('fallback', before: 'tesco')
        ->renameRule('tesco', 'tesco-stores');

    $preview = $pending->preview();
    $version = $pending->publish();

    expect($version->version)->toBe($preview->version)
        ->and($version->rules->pluck('key')->all())->toBe(array_map(fn (RuleDefinition $rule): string => $rule->key, $preview->rules))
        ->and($version->rules->pluck('uuid')->all())->toBe(array_map(fn (RuleDefinition $rule): ?string => $rule->uuid, $preview->rules));
});
