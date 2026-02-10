<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class TypeAssert
{
    public static function string(mixed $value, string $message = 'Value is not a string'): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }

    public static function int(mixed $value, string $message = 'Value is not an integer'): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false) {
             throw new \InvalidArgumentException($message);
        }
        return $filtered;
    }

    public static function float(mixed $value, string $message = 'Value is not a float'): float
    {
        if (!is_float($value) && !is_numeric($value)) {
            throw new \InvalidArgumentException($message);
        }
        return (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function array(mixed $value, string $message = 'Value is not an array'): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }
        /** @var array<string, mixed> $value */
        return $value;
    }
}
