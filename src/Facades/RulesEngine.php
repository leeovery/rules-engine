<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Facades;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\PendingRule;
use LeeOvery\RulesEngine\PendingRuleSet;
use LeeOvery\RulesEngine\RuleSetChanges;

/**
 * @method static PendingRule for(BackedEnum|string $ruleSet)
 * @method static PendingRuleSet ruleSet(BackedEnum|string $ruleSet)
 * @method static Collection<int, RuleSetVersion> versions(BackedEnum|string $ruleSet)
 * @method static RuleSetVersion version(BackedEnum|string $ruleSet, int $version)
 * @method static RuleSetVersion newestVersion(BackedEnum|string $ruleSet)
 * @method static RuleSetChanges changes(BackedEnum|string $ruleSet, int $from, int $to)
 *
 * @see \LeeOvery\RulesEngine\RulesEngine
 *
 * @mixin \LeeOvery\RulesEngine\RulesEngine
 */
final class RulesEngine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rules-engine';
    }
}
