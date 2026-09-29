<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;

final class Name
{
    public static function of(BackedEnum|string $name): string
    {
        return $name instanceof BackedEnum ? (string) $name->value : $name;
    }
}
