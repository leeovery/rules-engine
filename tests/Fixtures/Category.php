<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Tests\Fixtures;

enum Category: string
{
    case Groceries = 'groceries';
    case Fuel = 'fuel';
    case EatingOut = 'eating-out';
    case Uncategorised = 'uncategorised';
}
