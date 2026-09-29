<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use RuntimeException;

final class MultipleRulesMatchedException extends RuntimeException
{
    public static function forRuleSet(string $ruleSetName, int $count): self
    {
        return new self(
            sprintf('Expected exactly one matching rule for rule set "%s", but found %d.', $ruleSetName, $count)
        );
    }
}
