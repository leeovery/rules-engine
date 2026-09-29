<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Tests\Fixtures;

enum TransferPurpose: string
{
    case Loan = 'loan';
    case Gift = 'gift';
    case Payment = 'payment';
}
