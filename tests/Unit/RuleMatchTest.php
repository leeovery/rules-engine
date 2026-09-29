<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\RuleMatch;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

/**
 * @param  array<array-key, mixed>|null  $metadata
 */
function ruleMatch(mixed $value = 'loan', ?array $metadata = null): RuleMatch
{
    return new RuleMatch(
        ruleSet: 'personal-transfers',
        version: 2,
        versionId: 7,
        ruleId: 31,
        uuid: '0199b7c4-5e2a-7f11-9c3d-2f6e8a1b4c5d',
        key: 'j-parker',
        position: 3,
        value: $value,
        score: 2,
        metadata: $metadata,
    );
}

it('carries what answered', function (): void {
    $match = ruleMatch();

    expect($match->ruleSet)->toBe('personal-transfers')
        ->and($match->version)->toBe(2)
        ->and($match->versionId)->toBe(7)
        ->and($match->ruleId)->toBe(31)
        ->and($match->uuid)->toBe('0199b7c4-5e2a-7f11-9c3d-2f6e8a1b4c5d')
        ->and($match->key)->toBe('j-parker')
        ->and($match->position)->toBe(3)
        ->and($match->value)->toBe('loan')
        ->and($match->score)->toBe(2);
});

describe('metadata', function (): void {
    it('returns all of it without a key', function (): void {
        expect(ruleMatch(metadata: ['owner' => 'lee'])->metadata())->toBe(['owner' => 'lee'])
            ->and(ruleMatch()->metadata())->toBeNull();
    });

    it('returns a key, with dot notation and a default', function (): void {
        $match = ruleMatch(metadata: ['config' => ['timeout' => 30]]);

        expect($match->metadata('config.timeout'))->toBe(30)
            ->and($match->metadata('missing'))->toBeNull()
            ->and($match->metadata('missing', 'default'))->toBe('default')
            ->and(ruleMatch()->metadata('missing', 'default'))->toBe('default');
    });
});

describe('as', function (): void {
    it('reads the value back as a backed enum case', function (): void {
        expect(ruleMatch('gift')->as(TransferPurpose::class))->toBe(TransferPurpose::Gift);
    });

    it('refuses a value the enum does not have', function (): void {
        ruleMatch('bribe')->as(TransferPurpose::class);
    })->throws(ValueError::class);
});

it('converts to and from an array', function (): void {
    $match = ruleMatch(['category' => 'loan'], ['owner' => 'lee']);

    expect($match->toArray())->toBe([
        'rule_set' => 'personal-transfers',
        'version' => 2,
        'version_id' => 7,
        'rule_id' => 31,
        'uuid' => '0199b7c4-5e2a-7f11-9c3d-2f6e8a1b4c5d',
        'key' => 'j-parker',
        'position' => 3,
        'value' => ['category' => 'loan'],
        'score' => 2,
        'metadata' => ['owner' => 'lee'],
    ])
        ->and(RuleMatch::fromArray($match->toArray()))->toEqual($match);
});
