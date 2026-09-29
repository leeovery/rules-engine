<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Exceptions\NoMatchingRuleException;
use LeeOvery\RulesEngine\Exceptions\RuleSetNotFoundException;

describe('RuleSetNotFoundException', function (): void {
    it('creates exception with rule set name', function (): void {
        $exception = RuleSetNotFoundException::forName('pricing-rules');

        expect($exception->getMessage())->toBe("RuleSet 'pricing-rules' not found.");
    });
});

describe('NoMatchingRuleException', function (): void {
    it('creates exception without data', function (): void {
        $exception = NoMatchingRuleException::forRuleSet('pricing-rules');

        expect($exception->getMessage())->toBe("No matching rule found in RuleSet 'pricing-rules'.");
    });

    it('creates exception with empty data array', function (): void {
        $exception = NoMatchingRuleException::forRuleSet('pricing-rules', []);

        expect($exception->getMessage())->toBe("No matching rule found in RuleSet 'pricing-rules'.");
    });

    it('creates exception with data keys', function (): void {
        $exception = NoMatchingRuleException::forRuleSet('pricing-rules', [
            'region' => 'US',
            'tier' => 'premium',
        ]);

        expect($exception->getMessage())
            ->toBe("No matching rule found in RuleSet 'pricing-rules' for data keys: [region, tier].");
    });
});

describe('NoApplicableVersionException', function (): void {
    it('explains what was looked for', function (): void {
        $asOf = CarbonImmutable::parse('2024-01-01');
        $knownAt = CarbonImmutable::parse('2026-09-28 16:57:04.5');

        expect(NoApplicableVersionException::inForce('uk.dividend-rates', $asOf, $knownAt)->getMessage())
            ->toBe("Rule set 'uk.dividend-rates' has no version in force on 2024-01-01 among those published by 2026-09-28 16:57:04.500000.")
            ->and(NoApplicableVersionException::numbered('templates', 4)->getMessage())
            ->toBe("Rule set 'templates' has no version 4.");
    });
});
