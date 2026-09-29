<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use InvalidArgumentException;

final class CannotPublishRuleSetException extends InvalidArgumentException
{
    public static function withoutRules(string $ruleSet): self
    {
        return new self("Rule set '{$ruleSet}' can't be published without rules.");
    }

    public static function notJsonSafe(string $ruleSet, string $key, string $part, string $type): self
    {
        return new self(
            "The {$part} of rule '{$key}' in rule set '{$ruleSet}' holds {$type}, which can't be stored as JSON. "
            .'Use arrays, scalars, null or backed enums.'
        );
    }

    public static function duplicateKey(string $ruleSet, string $key): self
    {
        return new self("Rule set '{$ruleSet}' lists rule '{$key}' more than once.");
    }

    public static function duplicateCondition(string $ruleSet, string $key, string $repeatingKey): self
    {
        return new self("Rules '{$key}' and '{$repeatingKey}' in rule set '{$ruleSet}' have the same condition.");
    }

    public static function effectiveDateRequired(string $ruleSet): self
    {
        return new self("Rule set '{$ruleSet}' is dated: its first version has an effective date, so every version needs one.");
    }

    public static function effectiveDateNotAllowed(string $ruleSet): self
    {
        return new self("Rule set '{$ruleSet}' isn't dated: its first version has no effective date, so no version can have one.");
    }

    public static function effectiveBeforeFirstVersion(string $ruleSet, string $effectiveFrom, string $firstEffectiveFrom): self
    {
        return new self("Rule set '{$ruleSet}' starts on {$firstEffectiveFrom}, so a version can't take effect on {$effectiveFrom}.");
    }

    public static function listAndEdits(string $ruleSet): self
    {
        return new self("Publish rule set '{$ruleSet}' either as a complete list of rules or as edits, not both.");
    }

    public static function revertWithChanges(string $ruleSet): self
    {
        return new self("Reverting rule set '{$ruleSet}' publishes an earlier version as it was, so it can't be combined with rules or edits.");
    }

    public static function ruleExists(string $ruleSet, string $key): self
    {
        return new self("Rule set '{$ruleSet}' already has a rule '{$key}'.");
    }

    public static function noSuchRule(string $ruleSet, string $key): self
    {
        return new self("Rule set '{$ruleSet}' has no rule '{$key}'.");
    }

    public static function nothingToUpdate(string $ruleSet, string $key): self
    {
        return new self("Updating rule '{$key}' in rule set '{$ruleSet}' needs a value, condition or metadata.");
    }

    public static function beforeAndAfter(string $ruleSet, string $key): self
    {
        return new self("Rule '{$key}' in rule set '{$ruleSet}' can be added before a rule or after one, not both.");
    }

    public static function moveNeedsOnePlace(string $ruleSet, string $key): self
    {
        return new self("Moving rule '{$key}' in rule set '{$ruleSet}' needs exactly one of before or after.");
    }

    public static function movedAgainstItself(string $ruleSet, string $key): self
    {
        return new self("Rule '{$key}' in rule set '{$ruleSet}' can't move before or after itself.");
    }
}
