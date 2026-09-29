<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Tests\Fixtures;

enum Merchant: string
{
    case Tesco = 'tesco';
    case Shell = 'shell';
    case Pret = 'pret';
    case Aldi = 'aldi';
}
