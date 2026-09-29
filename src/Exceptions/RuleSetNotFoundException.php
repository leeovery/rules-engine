<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use RuntimeException;

final class RuleSetNotFoundException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("RuleSet '{$name}' not found.");
    }
}
