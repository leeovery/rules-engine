<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Exceptions\RuleSetNotFoundException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

describe('undated sets', function (): void {
    it('resolve the newest version', function (): void {
        publishUndated('templates', 'template_a');
        publishUndated('templates', 'template_b');

        expect(RulesEngine::for('templates')->firstValue())->toBe('template_b');
    });

    it('are in force from the start, whatever the as-of date', function (): void {
        publishUndated('templates', 'template_a');
        publishUndated('templates', 'template_b');

        expect(RulesEngine::for('templates')->asOf('2000-01-01')->firstValue())->toBe('template_b');
    });
});

describe('dated sets', function (): void {
    beforeEach(function (): void {
        publishDated(TestRuleSet::DividendRates, '2025-04-06', ['allowance' => 50000, 'ordinary' => '0.0875']);
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['allowance' => 50000, 'ordinary' => '0.1075']);
    });

    it('resolve the version in force on the as-of date', function (string $asOf, string $ordinary): void {
        $rates = RulesEngine::for(TestRuleSet::DividendRates)->asOf($asOf)->firstValue();

        expect($rates['ordinary'])->toBe($ordinary);
    })->with([
        'the first effective date' => ['2025-04-06', '0.0875'],
        'within the first tax year' => ['2025-12-31', '0.0875'],
        'the day before the next effective date' => ['2026-04-05', '0.0875'],
        'the next effective date' => ['2026-04-06', '0.1075'],
        'long after' => ['2030-01-01', '0.1075'],
    ]);

    it('accept a date object as the as-of date', function (): void {
        $rates = RulesEngine::for(TestRuleSet::DividendRates)
            ->asOf(new DateTimeImmutable('2026-04-06 00:30:00', new DateTimeZone('Europe/London')))
            ->firstValue();

        expect($rates['ordinary'])->toBe('0.1075');
    });

    it('prefer the highest version among those with the same effective date', function (): void {
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['allowance' => 50000, 'ordinary' => '0.1080']);

        expect(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-06-01')->firstValue()['ordinary'])->toBe('0.1080')
            ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2025-06-01')->firstValue()['ordinary'])->toBe('0.0875');
    });

    it('apply a later correction to an earlier period only', function (): void {
        publishDated(TestRuleSet::DividendRates, '2025-04-06', ['allowance' => 50000, 'ordinary' => '0.0900']);

        expect(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2025-06-01')->firstValue()['ordinary'])->toBe('0.0900')
            ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-06-01')->firstValue()['ordinary'])->toBe('0.1075');
    });

    it('resolve as of today by default', function (): void {
        $resolveToday = fn (): string => RulesEngine::for(TestRuleSet::DividendRates)
            ->knownAt('2030-01-01 00:00:00')
            ->firstValue()['ordinary'];

        $this->travelTo('2026-04-05 23:59:59');
        expect($resolveToday())->toBe('0.0875');

        $this->travelTo('2026-04-06 00:00:00');
        expect($resolveToday())->toBe('0.1075');
    });

    it('have no version in force before the first effective date', function (): void {
        RulesEngine::for(TestRuleSet::DividendRates)->asOf('2025-04-05')->knownAt('2030-01-01 00:00:00')->first();
    })->throws(
        NoApplicableVersionException::class,
        "Rule set 'uk.dividend-rates' has no version in force on 2025-04-05 among those published by 2030-01-01 00:00:00.000000.",
    );
});

it('ranks an undated version below dated ones', function (): void {
    $ruleSet = RuleSet::factory()->create(['name' => 'mixed']);

    foreach ([1 => null, 2 => '2026-04-06', 3 => null] as $number => $effectiveFrom) {
        $version = RuleSetVersion::factory()->for($ruleSet)->create(['version' => $number, 'effective_from' => $effectiveFrom]);
        Rule::factory()->for($version)->create(['value' => "version {$number}"]);
    }

    expect(RulesEngine::for('mixed')->asOf('2026-04-05')->firstValue())->toBe('version 3')
        ->and(RulesEngine::for('mixed')->asOf('2026-04-06')->firstValue())->toBe('version 2');
});

describe('known at', function (): void {
    it('ignores versions published after the moment', function (): void {
        $this->travelTo('2026-01-01 09:00:00');
        publishUndated('templates', 'template_a');

        $this->travelTo('2026-02-01 09:00:00');
        publishUndated('templates', 'template_b');

        expect(RulesEngine::for('templates')->knownAt('2026-01-15 12:00:00')->firstValue())->toBe('template_a')
            ->and(RulesEngine::for('templates')->knownAt('2026-02-01 09:00:00')->firstValue())->toBe('template_b');
    });

    it('reproduces a dated lookup as it was known then', function (): void {
        $this->travelTo('2026-03-01 09:00:00');
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['ordinary' => '0.1075']);

        $this->travelTo('2026-05-01 09:00:00');
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['ordinary' => '0.1080']);

        $asKnownInApril = RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-04-20')->knownAt('2026-04-20 12:00:00');

        expect($asKnownInApril->firstValue()['ordinary'])->toBe('0.1075')
            ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-04-20')->firstValue()['ordinary'])->toBe('0.1080');
    });

    it('distinguishes moments within the same second', function (): void {
        $this->travelTo('2026-01-01 09:00:00.500000');
        publishUndated('templates', 'template_a');

        expect(fn () => RulesEngine::for('templates')->knownAt('2026-01-01 09:00:00.499999')->first())
            ->toThrow(NoApplicableVersionException::class)
            ->and(RulesEngine::for('templates')->knownAt('2026-01-01 09:00:00.500000')->firstValue())->toBe('template_a');
    });

    it('compares moments in any timezone', function (): void {
        $this->travelTo(Date::parse('2026-07-01 09:00:00', 'UTC'));
        publishUndated('templates', 'template_a');

        $sameMomentInLondon = new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('Europe/London'));
        $secondBeforeInLondon = new DateTimeImmutable('2026-07-01 09:59:59', new DateTimeZone('Europe/London'));

        expect(RulesEngine::for('templates')->knownAt($sameMomentInLondon)->firstValue())->toBe('template_a')
            ->and(fn () => RulesEngine::for('templates')->knownAt($secondBeforeInLondon)->first())
            ->toThrow(NoApplicableVersionException::class);
    });

    it('defaults to now', function (): void {
        $this->travelTo('2026-01-01 09:00:00');
        publishUndated('templates', 'template_a');

        $this->travelTo('2025-12-31 09:00:00');

        RulesEngine::for('templates')->first();
    })->throws(NoApplicableVersionException::class, "Rule set 'templates' has no version in force on 2025-12-31 among those published by 2025-12-31 09:00:00.000000.");
});

describe('pinned versions', function (): void {
    it('resolve exactly the version asked for', function (): void {
        publishUndated('templates', 'template_a');
        publishUndated('templates', 'template_b');

        $match = RulesEngine::for('templates')->atVersion(1)->sole();

        expect($match->value)->toBe('template_a')
            ->and($match->version)->toBe(1);
    });

    it('ignore the as-of date and known-at moment', function (): void {
        $this->travelTo('2026-03-01 09:00:00');
        publishDated(TestRuleSet::DividendRates, '2026-04-06', ['ordinary' => '0.1075']);

        $rates = RulesEngine::for(TestRuleSet::DividendRates)
            ->asOf('2020-01-01')
            ->knownAt('2020-01-01 00:00:00')
            ->atVersion(1)
            ->firstValue();

        expect($rates['ordinary'])->toBe('0.1075');
    });

    it('must exist', function (): void {
        publishUndated('templates', 'template_a');

        RulesEngine::for('templates')->atVersion(2)->first();
    })->throws(NoApplicableVersionException::class, "Rule set 'templates' has no version 2.");
});

it('throws for a rule set that does not exist', function (): void {
    RulesEngine::for('missing')->first();
})->throws(RuleSetNotFoundException::class, "RuleSet 'missing' not found.");

it('finds rule sets named by an enum', function (): void {
    publishUndated(TestRuleSet::PersonalTransfers, 'loan');

    expect(RulesEngine::for(TestRuleSet::PersonalTransfers)->firstValue())->toBe('loan')
        ->and(RulesEngine::for('personal-transfers')->firstValue())->toBe('loan');
});
