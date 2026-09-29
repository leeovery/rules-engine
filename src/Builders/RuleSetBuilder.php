<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Builders;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Name;

/**
 * @extends Builder<RuleSet>
 */
class RuleSetBuilder extends Builder
{
    public function whereName(BackedEnum|string $ruleSet): self
    {
        return $this->where('name', Name::of($ruleSet));
    }
}
