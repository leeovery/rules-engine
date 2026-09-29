<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;

final class JsonValue
{
    public static function normalise(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            is_array($value) => array_map(self::normalise(...), $value),
            default => $value,
        };
    }

    public static function unsafeTypeIn(mixed $value): ?string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(self::unsafeTypeIn(...))
                ->first(fn (?string $type): bool => $type !== null);
        }

        return match (true) {
            $value === null, is_bool($value), is_int($value), is_string($value), $value instanceof BackedEnum => null,
            is_float($value) => is_finite($value) ? null : var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
