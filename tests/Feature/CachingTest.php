<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;

beforeEach(function (): void {
    RulesEngine::ruleSet('templates')
        ->rule('a', Condition::where('category', 'A'), 'template_a', ['owner' => 'lee'])
        ->rule('default', Condition::always(), 'template_default')
        ->publish();

    $this->countRuleQueries = function (Closure $resolve): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolve();

        return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "rules"'))->count();
    };
});

it('evaluates a version once for the same facts', function (): void {
    $resolve = fn () => RulesEngine::for('templates')->with(['category' => 'A'])->cache(60)->all();

    expect(($this->countRuleQueries)($resolve))->toBe(1)
        ->and(($this->countRuleQueries)($resolve))->toBe(0);
});

it('caches by default for the configured time', function (): void {
    config()->set('rules-engine.cache.ttl', 600);

    $resolve = fn () => RulesEngine::for('templates')->with(['category' => 'A'])->first();

    expect(($this->countRuleQueries)($resolve))->toBe(1)
        ->and(($this->countRuleQueries)($resolve))->toBe(0);
});

it('serves cached matches as they were first resolved', function (): void {
    $uncached = RulesEngine::for('templates')->with(['category' => 'A'])->withoutCache()->all();

    RulesEngine::for('templates')->with(['category' => 'A'])->cache(60)->all();
    $cached = RulesEngine::for('templates')->with(['category' => 'A'])->cache(60)->all();

    expect($cached)->toEqual($uncached)
        ->and($cached[0]->metadata('owner'))->toBe('lee');
});

it('keys the cache by the facts, whatever their order', function (): void {
    ($this->countRuleQueries)(fn () => RulesEngine::for('templates')->with(['category' => 'A', 'region' => 'EU'])->cache(60)->all());

    $reordered = fn () => RulesEngine::for('templates')->with(['region' => 'EU', 'category' => 'A'])->cache(60)->all();
    $different = fn () => RulesEngine::for('templates')->with(['category' => 'B', 'region' => 'EU'])->cache(60)->firstValue();

    expect(($this->countRuleQueries)($reordered))->toBe(0)
        ->and(($this->countRuleQueries)($different))->toBe(1)
        ->and($different())->toBe('template_default');
});

it('uses a newly published version at once', function (): void {
    expect(RulesEngine::for('templates')->with(['category' => 'A'])->cache(60)->firstValue())->toBe('template_a');

    RulesEngine::ruleSet('templates')->updateRule('a', value: 'template_a_revised')->publish();

    expect(RulesEngine::for('templates')->with(['category' => 'A'])->cache(60)->firstValue())->toBe('template_a_revised');
});

it('skips the cache when asked', function (): void {
    $resolve = fn () => RulesEngine::for('templates')->with(['category' => 'A'])->withoutCache()->first();

    expect(($this->countRuleQueries)($resolve))->toBe(1)
        ->and(($this->countRuleQueries)($resolve))->toBe(1);
});
