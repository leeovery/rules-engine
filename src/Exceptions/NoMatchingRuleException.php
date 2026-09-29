<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use RuntimeException;

final class NoMatchingRuleException extends RuntimeException
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function forRuleSet(string $name, array $data = []): self
    {
        if ($data === []) {
            return new self("No matching rule found in RuleSet '{$name}'.");
        }

        $dataKeys = implode(', ', array_keys($data));

        return new self("No matching rule found in RuleSet '{$name}' for data keys: [{$dataKeys}].");
    }
}
