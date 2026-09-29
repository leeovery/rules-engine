<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Events;

use Carbon\CarbonImmutable;
use LeeOvery\RulesEngine\RuleSetChanges;

final readonly class RuleSetVersionPublished
{
    public function __construct(
        public string $ruleSet,
        public int $version,
        public ?CarbonImmutable $effectiveFrom,
        public ?string $publishedBy,
        public ?string $note,
        public RuleSetChanges $changes,
    ) {}
}
