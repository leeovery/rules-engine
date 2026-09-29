<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\PendingRuleSet;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\Colour;
use LeeOvery\RulesEngine\Tests\Fixtures\Merchant;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

it('refuses to publish without rules', function (): void {
    RulesEngine::ruleSet('templates')->publish();
})->throws(CannotPublishRuleSetException::class, "Rule set 'templates' can't be published without rules.");

it('refuses values that JSON cannot hold', function (mixed $value, string $type): void {
    expect(fn () => publishUndated('templates', $value))
        ->toThrow(CannotPublishRuleSetException::class, "The value of rule 'value' in rule set 'templates' holds {$type}");

    expect(RuleSet::query()->count())->toBe(0);
})->with([
    'closure' => [fn (): string => 'computed', 'Closure'],
    'object' => [new stdClass, 'stdClass'],
    'object in an array' => [['nested' => ['date' => new DateTimeImmutable('2026-04-06')]], 'DateTimeImmutable'],
    'pure enum' => [Colour::Red, Colour::class],
    'infinite float' => [INF, 'INF'],
]);

it('refuses metadata that JSON cannot hold', function (): void {
    RulesEngine::ruleSet('templates')
        ->rule('a', Condition::always(), 'template_a', ['handler' => new stdClass])
        ->publish();
})->throws(CannotPublishRuleSetException::class, "The metadata of rule 'a' in rule set 'templates' holds stdClass");

it('refuses conditions that JSON cannot hold', function (): void {
    RulesEngine::ruleSet('templates')
        ->rule('a', Condition::where('category', 'A'), 'template_a')
        ->rule('b', Condition::where('category', new stdClass), 'template_b')
        ->publish();
})->throws(CannotPublishRuleSetException::class, "The condition of rule 'b' in rule set 'templates' holds stdClass");

it('refuses two rules with the same condition', function (): void {
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->rule('tesco-again', Condition::where('merchant', 'TESCO'), Category::Fuel)
        ->publish();
})->throws(CannotPublishRuleSetException::class, "Rules 'tesco' and 'tesco-again' in rule set 'merchant-categories' have the same condition.");

it('refuses two rules with the same key', function (): void {
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule(Merchant::Tesco, Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('tesco', Condition::where('merchant', 'TESCO STORES'), Category::Groceries)
        ->publish();
})->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' lists rule 'tesco' more than once.");

it('refuses a list and edits in the same publish', function (Closure $mix): void {
    expect(fn () => $mix(RulesEngine::ruleSet(TestRuleSet::MerchantCategories)))->toThrow(
        CannotPublishRuleSetException::class,
        "Publish rule set 'merchant-categories' either as a complete list of rules or as edits, not both.",
    );
})->with([
    'an edit after a rule' => fn (PendingRuleSet $pending) => $pending
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->removeRule('shell'),
    'a rule after an edit' => fn (PendingRuleSet $pending) => $pending
        ->updateRule('tesco', value: Category::Fuel)
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel),
]);

describe('effective dates', function (): void {
    it('are required once the first version had one', function (): void {
        publishDated(TestRuleSet::DividendRates, '2025-04-06', ['allowance' => 50000]);

        publishUndated(TestRuleSet::DividendRates, ['allowance' => 50000]);
    })->throws(
        CannotPublishRuleSetException::class,
        "Rule set 'uk.dividend-rates' is dated: its first version has an effective date, so every version needs one.",
    );

    it('are refused when the first version had none', function (): void {
        publishUndated('templates', 'template_a');

        expect(fn () => publishDated('templates', '2026-04-06', 'template_b'))->toThrow(
            CannotPublishRuleSetException::class,
            "Rule set 'templates' isn't dated: its first version has no effective date, so no version can have one.",
        );

        expect(RuleSetVersion::query()->count())->toBe(1);
    });

    it('can\'t come before the first version\'s', function (): void {
        publishDated(TestRuleSet::DividendRates, '2025-04-06', ['allowance' => 50000]);

        publishDated(TestRuleSet::DividendRates, '2025-04-05', ['allowance' => 1000]);
    })->throws(
        CannotPublishRuleSetException::class,
        "Rule set 'uk.dividend-rates' starts on 2025-04-06, so a version can't take effect on 2025-04-05.",
    );

    it('can be on or after the first version\'s', function (string $effectiveFrom): void {
        publishDated(TestRuleSet::DividendRates, '2025-04-06', ['allowance' => 50000]);
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['allowance' => 50000]);

        $version = publishDated(TestRuleSet::DividendRates, $effectiveFrom, ['allowance' => 1000]);

        expect($version->version)->toBe(3);
    })->with([
        'the first effective date' => '2025-04-06',
        'a correction between versions' => '2025-10-01',
        'a later date' => '2027-04-06',
    ]);
});
