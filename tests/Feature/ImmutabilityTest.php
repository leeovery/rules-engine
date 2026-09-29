<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\ImmutableRecordException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

beforeEach(function (): void {
    RulesEngine::ruleSet('templates')
        ->rule('a', Condition::where('category', 'A'), 'template_a')
        ->rule('default', Condition::always(), 'template_default')
        ->note('Original')
        ->publish();

    $this->version = RuleSetVersion::query()->sole();
    $this->rule = $this->version->rules->firstOrFail();
});

it('refuses to change a published version', function (Closure $change): void {
    expect(fn () => $change($this->version))->toThrow(
        ImmutableRecordException::class,
        "RuleSetVersion {$this->version->id} is immutable. Publish a new version instead.",
    );

    expect($this->version->fresh()?->note)->toBe('Original')
        ->and($this->version->fresh()?->version)->toBe(1);
})->with([
    'update' => fn (RuleSetVersion $version) => $version->update(['note' => 'Edited']),
    'save' => function (RuleSetVersion $version): void {
        $version->note = 'Edited';
        $version->save();
    },
    'quiet update' => fn (RuleSetVersion $version) => $version->updateQuietly(['note' => 'Edited']),
    'increment' => fn (RuleSetVersion $version) => $version->increment('version'),
]);

it('refuses to delete a published version', function (Closure $delete): void {
    expect(fn () => $delete($this->version))->toThrow(ImmutableRecordException::class);

    expect(RuleSetVersion::query()->whereKey($this->version->id)->exists())->toBeTrue();
})->with([
    'delete' => fn (RuleSetVersion $version) => $version->delete(),
    'quiet delete' => fn (RuleSetVersion $version) => $version->deleteQuietly(),
]);

it('refuses to change or delete a published rule', function (Closure $change): void {
    expect(fn () => $change($this->rule))->toThrow(
        ImmutableRecordException::class,
        "Rule {$this->rule->id} is immutable. Publish a new version instead.",
    );

    expect($this->rule->fresh()?->value)->toBe('template_a');
})->with([
    'update' => fn (Rule $rule) => $rule->update(['value' => 'edited']),
    'delete' => fn (Rule $rule) => $rule->delete(),
]);

it('stays immutable while model events are faked', function (): void {
    Event::fake();

    expect(fn () => $this->version->update(['note' => 'Edited']))->toThrow(ImmutableRecordException::class)
        ->and(fn () => $this->rule->delete())->toThrow(ImmutableRecordException::class);
});

it('refuses to delete a rule set that has versions', function (): void {
    expect(fn () => DB::transaction(fn () => RuleSet::query()->sole()->delete()))->toThrow(QueryException::class);

    expect(RuleSetVersion::query()->count())->toBe(1);
});

it('leaves earlier versions untouched when a new version is published', function (): void {
    RulesEngine::ruleSet('templates')->rule('new', Condition::always(), 'template_new')->publish();

    $first = RuleSetVersion::query()->with('rules')->findOrFail($this->version->id);

    expect($first->note)->toBe('Original')
        ->and($first->rules->pluck('value', 'position')->all())->toBe([1 => 'template_a', 2 => 'template_default']);
});
