<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Events\RuleSetVersionPublished;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

/**
 * @param  list<RuleReference>  $references
 * @return list<string>
 */
function announcedKeys(array $references): array
{
    return array_map(fn (RuleReference $reference): string => $reference->key, $references);
}

it('announces each published version', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2026-04-06')
        ->rule('rates', Condition::always(), ['allowance' => 50000])
        ->publishedBy('lee')
        ->note('Budget 2026')
        ->publish();

    Event::assertDispatchedTimes(RuleSetVersionPublished::class, 1);
    Event::assertDispatched(fn (RuleSetVersionPublished $event): bool => $event->ruleSet === 'uk.dividend-rates'
        && $event->version === 1
        && $event->effectiveFrom?->toDateString() === '2026-04-06'
        && $event->publishedBy === 'lee'
        && $event->note === 'Budget 2026');
});

it('announces a version without an effective date', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    publishUndated('templates', 'template_a');
    publishUndated('templates', 'template_b');

    Event::assertDispatched(fn (RuleSetVersionPublished $event): bool => $event->version === 2
        && $event->effectiveFrom === null
        && $event->publishedBy === null
        && $event->note === null);
});

it('carries every rule of the first version as added', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    $version = RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    Event::assertDispatched(fn (RuleSetVersionPublished $event): bool => array_map(
        fn (RuleReference $rule): array => [$rule->uuid, $rule->key],
        $event->changes->added,
    ) === [
        [$version->rules->sole('key', 'tesco')->uuid, 'tesco'],
        [$version->rules->sole('key', 'fallback')->uuid, 'fallback'],
    ] && $event->changes->removed === []);
});

it('carries the changes from the version it follows', function (): void {
    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    Event::fake([RuleSetVersionPublished::class]);

    RulesEngine::ruleSet(TestRuleSet::MerchantCategories)
        ->addRule('pret', Condition::where('merchant', 'PRET'), Category::EatingOut)
        ->updateRule('tesco', value: Category::Fuel)
        ->renameRule('shell', 'shell-garages')
        ->removeRule('fallback')
        ->publish();

    Event::assertDispatched(fn (RuleSetVersionPublished $event): bool => announcedKeys($event->changes->added) === ['pret']
        && announcedKeys($event->changes->removed) === ['fallback']
        && announcedKeys($event->changes->valueChanged) === ['tesco']
        && $event->changes->renamed[0]->from === 'shell'
        && $event->changes->renamed[0]->to === 'shell-garages'
        && $event->changes->moved === []);
});

it('fires inside the publishing transaction, after the version is written', function (): void {
    $levelBeforePublishing = DB::transactionLevel();

    Event::listen(function (RuleSetVersionPublished $event): void {
        $this->levelWhileAnnouncing = DB::transactionLevel();
        $this->versionWrittenWhileAnnouncing = RuleSetVersion::query()->where('version', $event->version)->exists();
    });

    publishUndated('templates', 'template_a');

    expect($this->levelWhileAnnouncing)->toBe($levelBeforePublishing + 1)
        ->and($this->versionWrittenWhileAnnouncing)->toBeTrue();
});

it('rolls the version back when a listener fails', function (): void {
    Event::listen(function (RuleSetVersionPublished $event): void {
        throw new RuntimeException('Listener failed');
    });

    expect(fn () => publishUndated('templates', 'template_a'))->toThrow(RuntimeException::class, 'Listener failed');

    expect(RuleSet::query()->count())->toBe(0)
        ->and(RuleSetVersion::query()->count())->toBe(0);
});

it('does not wait for the transaction to commit', function (): void {
    expect(RuleSetVersionPublished::class)->not->toImplement(ShouldDispatchAfterCommit::class);
});

it('stays quiet when publishing fails', function (): void {
    Event::fake([RuleSetVersionPublished::class]);

    expect(fn () => RulesEngine::ruleSet('templates')->publish())->toThrow(CannotPublishRuleSetException::class);

    Event::assertNotDispatched(RuleSetVersionPublished::class);
});
