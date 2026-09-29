<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\MultipleRulesMatchedException;
use LeeOvery\RulesEngine\Exceptions\NoMatchingRuleException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\RuleMatch;
use LeeOvery\RulesEngine\RulesEngine as RulesEngineService;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

it('resolves the matching rule with the highest score', function (array $facts, string $template): void {
    RulesEngine::ruleSet('templates')
        ->rule('a-high', Condition::where('category', 'A')->where('priority', 'high'), 'template_a_high')
        ->rule('a', Condition::where('category', 'A'), 'template_a_standard')
        ->rule('default', Condition::else(), 'template_default')
        ->publish();

    expect(RulesEngine::for('templates')->with($facts)->firstValue())->toBe($template);
})->with([
    'two conditions met' => [['category' => 'A', 'priority' => 'high'], 'template_a_high'],
    'one condition met' => [['category' => 'A', 'priority' => 'low'], 'template_a_standard'],
    'none met' => [['category' => 'B'], 'template_default'],
]);

it('orders all matches by score', function (): void {
    RulesEngine::ruleSet('scores')
        ->rule('one', Condition::where('a', true), 'one_match')
        ->rule('two', Condition::where('a', true)->where('b', true), 'two_matches')
        ->rule('three', Condition::where('a', true)->where('b', true)->where('c', true), 'three_matches')
        ->publish();

    $matches = RulesEngine::for('scores')->with(['a' => true, 'b' => true, 'c' => true])->all();

    expect(array_map(fn (RuleMatch $match): array => [$match->value, $match->score], $matches))->toBe([
        ['three_matches', 3],
        ['two_matches', 2],
        ['one_match', 1],
    ]);
});

it('ranks a rule that matched two clauses above one that matched only the other side of its OR', function (): void {
    RulesEngine::ruleSet('scores')
        ->rule('partly', Condition::where('a', 1)->where('b', 2)->where('c', 3)->orWhere('d', 4), 'matched_d_only')
        ->rule('two', Condition::where('a', 1)->where('b', 2), 'matched_two')
        ->publish();

    $matches = RulesEngine::for('scores')->with(['a' => 1, 'b' => 2, 'c' => 0, 'd' => 4])->all();

    expect(array_map(fn (RuleMatch $match): array => [$match->value, $match->score], $matches))->toBe([
        ['matched_two', 2],
        ['matched_d_only', 1],
    ]);
});

it('breaks ties by position', function (): void {
    RulesEngine::ruleSet('ties')
        ->rule('b', Condition::where('b', 2), 'b_at_position_1')
        ->rule('a', Condition::where('a', 1), 'a_at_position_2')
        ->rule('both', Condition::where('a', 1)->where('b', 2), 'both')
        ->rule('fallback', Condition::always(), 'fallback')
        ->publish();

    $matches = RulesEngine::for('ties')->with(['a' => 1, 'b' => 2])->all();

    expect(array_map(fn (RuleMatch $match): string => $match->value, $matches))
        ->toBe(['both', 'b_at_position_1', 'a_at_position_2', 'fallback']);
});

it('breaks ties by position, not by the order rows come back', function (): void {
    $version = RuleSetVersion::factory()->for(RuleSet::factory()->state(['name' => 'ties']))->create();

    foreach ([3 => 'third', 2 => 'second', 1 => 'first'] as $position => $value) {
        Rule::factory()->for($version)->create([
            'key' => $value,
            'position' => $position,
            'condition' => Condition::where('key', $value)->orWhere('shared', true),
            'value' => $value,
        ]);
    }

    $matches = RulesEngine::for('ties')->with(['shared' => true])->all();

    expect(array_map(fn (RuleMatch $match): int => $match->position, $matches))->toBe([1, 2, 3]);
});

it('carries the version and rule that answered', function (): void {
    publishUndated(TestRuleSet::PersonalTransfers, 'loan');
    $version = RulesEngine::ruleSet(TestRuleSet::PersonalTransfers)
        ->rule('j-parker', Condition::where('counterparty', 'J PARKER'), TransferPurpose::Loan, ['owner' => 'lee', 'source' => ['answer' => 42]])
        ->rule('value', Condition::always(), TransferPurpose::Payment)
        ->publish();

    $match = RulesEngine::for(TestRuleSet::PersonalTransfers)->with(['counterparty' => 'J PARKER'])->resolve()->firstOrFail();

    expect($match->ruleSet)->toBe('personal-transfers')
        ->and($match->version)->toBe(2)
        ->and($match->versionId)->toBe($version->id)
        ->and($match->ruleId)->toBe($version->rules->firstOrFail()->id)
        ->and($match->uuid)->toBe($version->rules->firstOrFail()->uuid)
        ->and($match->key)->toBe('j-parker')
        ->and($match->position)->toBe(1)
        ->and($match->value)->toBe('loan')
        ->and($match->score)->toBe(1)
        ->and($match->metadata())->toBe(['owner' => 'lee', 'source' => ['answer' => 42]])
        ->and($match->metadata('source.answer'))->toBe(42)
        ->and($match->as(TransferPurpose::class))->toBe(TransferPurpose::Loan);
});

it('matches enum facts against conditions built from enum cases', function (): void {
    RulesEngine::ruleSet(TestRuleSet::PersonalTransfers)
        ->rule('loan', Condition::where('purpose', TransferPurpose::Loan), 'record_a_loan')
        ->rule('other', Condition::whereIn('purpose', [TransferPurpose::Gift, TransferPurpose::Payment]), 'categorise')
        ->publish();

    expect(RulesEngine::for(TestRuleSet::PersonalTransfers)->with('purpose', TransferPurpose::Loan)->firstValue())->toBe('record_a_loan')
        ->and(RulesEngine::for(TestRuleSet::PersonalTransfers)->with(['purpose' => TransferPurpose::Gift])->firstValue())->toBe('categorise')
        ->and(RulesEngine::for(TestRuleSet::PersonalTransfers)->with(['purpose' => 'loan'])->firstValue())->toBe('record_a_loan');
});

it('supports OR conditions', function (): void {
    RulesEngine::ruleSet('outcomes')
        ->rule('success', Condition::where('status', 'approved')->orWhere('status', 'completed'), 'success')
        ->publish();

    expect(RulesEngine::for('outcomes')->with(['status' => 'approved'])->firstValue())->toBe('success')
        ->and(RulesEngine::for('outcomes')->with(['status' => 'completed'])->firstValue())->toBe('success')
        ->and(RulesEngine::for('outcomes')->with(['status' => 'pending'])->firstValue())->toBeNull();
});

it('supports nested conditions', function (): void {
    RulesEngine::ruleSet('regions')
        ->rule(
            'premium',
            Condition::where('tier', 'premium')->where(fn ($query) => $query->where('region', 'EU')->orWhere('region', 'US')),
            'premium_allowed_regions',
        )
        ->publish();

    expect(RulesEngine::for('regions')->with(['tier' => 'premium', 'region' => 'EU'])->firstValue())->toBe('premium_allowed_regions')
        ->and(RulesEngine::for('regions')->with(['tier' => 'premium', 'region' => 'APAC'])->firstValue())->toBeNull();
});

it('returns null when no rule matches', function (): void {
    RulesEngine::ruleSet('strict')->rule('high', Condition::where('amount', '>', 10000), 'high_value')->publish();

    expect(RulesEngine::for('strict')->with(['amount' => 500])->first())->toBeNull()
        ->and(RulesEngine::for('strict')->with(['amount' => 500])->firstValue())->toBeNull()
        ->and(RulesEngine::for('strict')->with(['amount' => 500])->all())->toBe([]);
});

it('resolves a sole match', function (): void {
    RulesEngine::ruleSet('sole')
        ->rule('one', Condition::where('id', 1), 'only_one')
        ->rule('two', Condition::where('id', 2), 'another')
        ->publish();

    expect(RulesEngine::for('sole')->with(['id' => 1])->sole()->value)->toBe('only_one')
        ->and(RulesEngine::for('sole')->with(['id' => 1])->soleValue())->toBe('only_one');
});

it('refuses a sole match when none or several match', function (): void {
    RulesEngine::ruleSet('sole')
        ->rule('one', Condition::where('id', 1), 'only_one')
        ->rule('fallback', Condition::always(), 'fallback')
        ->publish();

    expect(fn () => RulesEngine::for('sole')->with(['id' => 1])->sole())->toThrow(MultipleRulesMatchedException::class);

    RulesEngine::ruleSet('sole')->removeRule('fallback')->publish();

    expect(fn () => RulesEngine::for('sole')->with(['id' => 2])->soleValue())->toThrow(NoMatchingRuleException::class);
});

it('accepts facts as an array or a key and value', function (): void {
    RulesEngine::ruleSet('facts')->rule('both', Condition::where('a', 1)->where('b', 2), 'matched')->publish();

    expect(RulesEngine::for('facts')->with(['a' => 1])->with('b', 2)->firstValue())->toBe('matched');
});

it('resolves through the container as well as the facade', function (): void {
    publishUndated('templates', 'template_a');

    expect(resolve(RulesEngineService::class)->for('templates')->firstValue())->toBe('template_a');
});
