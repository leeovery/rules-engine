<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

final readonly class RenamedRule
{
    public function __construct(
        public string $uuid,
        public string $from,
        public string $to,
    ) {}
}
