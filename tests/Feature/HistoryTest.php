<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Exceptions\RuleSetNotFoundException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

beforeEach(function (): void {
    publishDated(TestRuleSet::DividendRates, '2025-04-06', ['ordinary' => '0.0875']);

    RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2026-04-06')
        ->rule('basic', Condition::where('band', 'basic'), ['rate' => '0.1075'])
        ->rule('other', Condition::always(), ['rate' => '0.3575'])
        ->publishedBy('lee')
        ->note('Budget 2026')
        ->publish();
});

it('lists every version, oldest first', function (): void {
    $versions = RulesEngine::versions(TestRuleSet::DividendRates);

    expect($versions)->toHaveCount(2)
        ->and($versions->pluck('version')->all())->toBe([1, 2])
        ->and($versions->map(fn (RuleSetVersion $version): ?string => $version->effective_from?->toDateString())->all())
        ->toBe(['2025-04-06', '2026-04-06'])
        ->and($versions->last()?->published_by)->toBe('lee')
        ->and($versions->last()?->note)->toBe('Budget 2026');
});

it('loads the rules of listed versions on request', function (): void {
    $versions = RulesEngine::versions('uk.dividend-rates')->load('rules');

    expect($versions->firstOrFail()->rules->pluck('value')->all())->toBe([['ordinary' => '0.0875']])
        ->and($versions->last()?->rules->pluck('value', 'position')->all())->toBe([
            1 => ['rate' => '0.1075'],
            2 => ['rate' => '0.3575'],
        ]);
});

it('finds a single version', function (): void {
    $version = RulesEngine::version(TestRuleSet::DividendRates, 2);

    expect($version->version)->toBe(2)
        ->and($version->rules)->toHaveCount(2);
});

it('finds the version published last, whatever its effective date', function (): void {
    RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2025-04-06')
        ->updateRule('value', value: ['ordinary' => '0.0876'])
        ->note('A correction for 2025/26')
        ->publish();

    expect(RulesEngine::newestVersion(TestRuleSet::DividendRates))
        ->version->toBe(3)
        ->note->toBe('A correction for 2025/26')
        ->and(RulesEngine::newestVersion('uk.dividend-rates')->effective_from?->toDateString())->toBe('2025-04-06');
});

it('throws for the newest version of a rule set that does not exist', function (): void {
    RulesEngine::newestVersion('missing');
})->throws(RuleSetNotFoundException::class, "RuleSet 'missing' not found.");

it('throws for a version that does not exist', function (): void {
    RulesEngine::version(TestRuleSet::DividendRates, 3);
})->throws(NoApplicableVersionException::class, "Rule set 'uk.dividend-rates' has no version 3.");

it('throws for a rule set that does not exist', function (): void {
    RulesEngine::versions('missing');
})->throws(RuleSetNotFoundException::class, "RuleSet 'missing' not found.");
