<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

final readonly class DraftVersion
{
    /**
     * @param  list<RuleDefinition>  $rules
     */
    public function __construct(
        public int $version,
        public ?int $previousVersion,
        public array $rules,
        public RuleSetChanges $changes,
    ) {}
}
