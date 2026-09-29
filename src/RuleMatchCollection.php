<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BadMethodCallException;
use Illuminate\Support\Collection;
use LeeOvery\RulesEngine\Exceptions\MultipleRulesMatchedException;
use LeeOvery\RulesEngine\Exceptions\NoMatchingRuleException;

/**
 * @extends Collection<int, RuleMatch>
 */
final class RuleMatchCollection extends Collection
{
    public function __construct(
        $items = [],
        private readonly string $ruleSetName = '',
    ) {
        parent::__construct($items);
    }

    public function sole($key = null, $operator = null, $value = null): RuleMatch
    {
        throw_if(func_num_args() > 0, BadMethodCallException::class, 'Filter parameters are not supported. Use where() then sole() if filtering is needed.');

        if ($this->isEmpty()) {
            throw NoMatchingRuleException::forRuleSet($this->ruleSetName);
        }

        if ($this->count() > 1) {
            throw MultipleRulesMatchedException::forRuleSet($this->ruleSetName, $this->count());
        }

        return $this->first();
    }

    public function firstValue(): mixed
    {
        return $this->first()?->value;
    }

    public function soleValue(): mixed
    {
        return $this->sole()->value;
    }
}
