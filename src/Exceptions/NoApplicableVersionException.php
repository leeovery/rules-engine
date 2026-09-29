<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use Carbon\CarbonImmutable;
use RuntimeException;

final class NoApplicableVersionException extends RuntimeException
{
    public static function inForce(string $ruleSet, CarbonImmutable $asOf, CarbonImmutable $knownAt): self
    {
        return new self(sprintf(
            "Rule set '%s' has no version in force on %s among those published by %s.",
            $ruleSet,
            $asOf->toDateString(),
            $knownAt->toDateTimeString('microsecond'),
        ));
    }

    public static function numbered(string $ruleSet, int $version): self
    {
        return new self("Rule set '{$ruleSet}' has no version {$version}.");
    }
}
