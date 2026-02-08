<?php

declare(strict_types=1);

namespace App\Domain\Shared;

trait NamedConstructorTrait
{
    /**
     * @param array<string, mixed> $params
     */
    public static function create(mixed ...$params): static
    {
        return new static(...$params);
    }
}
