<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

function publishUndated(BackedEnum|string $ruleSet, mixed $value): RuleSetVersion
{
    return RulesEngine::ruleSet($ruleSet)
        ->rule('value', Condition::always(), $value)
        ->publish();
}

function publishDated(BackedEnum|string $ruleSet, string $effectiveFrom, mixed $value): RuleSetVersion
{
    return RulesEngine::ruleSet($ruleSet)
        ->effectiveFrom($effectiveFrom)
        ->rule('value', Condition::always(), $value)
        ->publish();
}
