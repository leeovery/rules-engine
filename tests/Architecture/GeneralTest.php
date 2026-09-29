<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use LeeOvery\RulesEngine\DraftVersion;
use LeeOvery\RulesEngine\EvaluationResult;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Models\Concerns\Immutable;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\RenamedRule;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\RuleList;
use LeeOvery\RulesEngine\RuleMatch;
use LeeOvery\RulesEngine\RuleReference;
use LeeOvery\RulesEngine\RuleSetChanges;

arch('debugging calls are not left behind')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('every file declares strict types')
    ->expect('LeeOvery\RulesEngine')
    ->toUseStrictTypes();

arch('actions are invokable')
    ->expect('LeeOvery\RulesEngine\Actions')
    ->toHaveSuffix('Action')
    ->toHaveMethod('__invoke');

arch('events are plain values dispatched at once, inside the transaction')
    ->expect('LeeOvery\RulesEngine\Events')
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toImplement(ShouldDispatchAfterCommit::class)
    ->not->toImplement(ShouldQueue::class);

arch('exceptions are final and named as exceptions')
    ->expect('LeeOvery\RulesEngine\Exceptions')
    ->toBeFinal()
    ->toHaveSuffix('Exception')
    ->toExtend(Exception::class);

arch('a refused publish is an invalid argument')
    ->expect(CannotPublishRuleSetException::class)
    ->toExtend(InvalidArgumentException::class);

arch('values are final and readonly')
    ->expect([
        DraftVersion::class,
        EvaluationResult::class,
        RenamedRule::class,
        RuleDefinition::class,
        RuleList::class,
        RuleMatch::class,
        RuleReference::class,
        RuleSetChanges::class,
    ])
    ->toBeFinal()
    ->toBeReadonly();

arch('published versions and rules are immutable')
    ->expect([RuleSetVersion::class, Rule::class])
    ->toUseTrait(Immutable::class);
