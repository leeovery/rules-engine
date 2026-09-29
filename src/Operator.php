<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use InvalidArgumentException;

enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEquals = '>=';
    case LessThan = '<';
    case LessThanOrEquals = '<=';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';

    public static function fromString(string $value): self
    {
        return self::tryFromString($value)
            ?? throw new InvalidArgumentException("Unknown operator: {$value}");
    }

    public static function tryFromString(string $value): ?self
    {
        return match ($value) {
            '=', '==' => self::Equals,
            '!=', '<>' => self::NotEquals,
            '>' => self::GreaterThan,
            '>=' => self::GreaterThanOrEquals,
            '<' => self::LessThan,
            '<=' => self::LessThanOrEquals,
            'in' => self::In,
            'not_in' => self::NotIn,
            'contains' => self::Contains,
            'starts_with' => self::StartsWith,
            'ends_with' => self::EndsWith,
            default => null,
        };
    }
}
