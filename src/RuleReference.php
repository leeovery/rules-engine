<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

final readonly class RuleReference
{
    public function __construct(
        public string $uuid,
        public string $key,
    ) {}
}
