<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

final readonly class EvaluationResult
{
    public function __construct(
        public bool $matched,
        public int $score,
    ) {}
}
