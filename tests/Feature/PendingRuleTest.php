<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

describe('with', function (): void {
    it('adds facts as an array or a key and value, later ones winning', function (): void {
        $pending = RulesEngine::for('rules')
            ->with(['a' => 1, 'b' => 2])
            ->with('b', 3)
            ->with(['c' => 4]);

        expect($pending->getFacts())->toBe(['a' => 1, 'b' => 3, 'c' => 4]);
    });

    it('turns backed enums into their values', function (): void {
        $pending = RulesEngine::for('rules')
            ->with('purpose', TransferPurpose::Loan)
            ->with(['history' => [TransferPurpose::Gift, 'payment'], 'amount' => 1250]);

        expect($pending->getFacts())->toBe([
            'purpose' => 'loan',
            'history' => ['gift', 'payment'],
            'amount' => 1250,
        ]);
    });
});

describe('versions', function (): void {
    it('resolves as of today and as known now by default', function (): void {
        $this->travelTo('2026-09-28 16:57:04.123456');

        $pending = RulesEngine::for('rules');

        expect($pending->getAsOf()->toDateTimeString())->toBe('2026-09-28 00:00:00')
            ->and($pending->getKnownAt()->format('Y-m-d H:i:s.u'))->toBe('2026-09-28 16:57:04.123456')
            ->and($pending->getVersion())->toBeNull();
    });

    it('keeps the as-of date, known-at moment and pinned version', function (): void {
        $pending = RulesEngine::for(TestRuleSet::DividendRates)
            ->asOf('2026-04-06 15:30:00')
            ->knownAt('2026-05-01 09:00:00')
            ->atVersion(3);

        expect($pending->getRuleSetName())->toBe('uk.dividend-rates')
            ->and($pending->getAsOf()->toDateTimeString())->toBe('2026-04-06 00:00:00')
            ->and($pending->getKnownAt()->toDateTimeString())->toBe('2026-05-01 09:00:00')
            ->and($pending->getVersion())->toBe(3);
    });
});

describe('cache', function (): void {
    it('uses the configured time by default', function (): void {
        config()->set('rules-engine.cache.ttl', 900);

        expect(RulesEngine::for('rules')->getCacheTtl())->toBe(900);
    });

    it('sets the time', function (): void {
        expect(RulesEngine::for('rules')->cache(120)->getCacheTtl())->toBe(120);
    });

    it('turns caching off', function (): void {
        expect(RulesEngine::for('rules')->cache(60)->withoutCache()->getCacheTtl())->toBeNull();
    });

    it('refuses a time that is not positive', function (int $ttl): void {
        RulesEngine::for('rules')->cache($ttl);
    })->with([0, -1])->throws(InvalidArgumentException::class, 'Cache TTL must be greater than zero.');
});
