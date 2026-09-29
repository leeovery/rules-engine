<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Tests\Fixtures;

enum TestRuleSet: string
{
    case DividendRates = 'uk.dividend-rates';
    case MerchantCategories = 'merchant-categories';
    case PersonalTransfers = 'personal-transfers';
}
