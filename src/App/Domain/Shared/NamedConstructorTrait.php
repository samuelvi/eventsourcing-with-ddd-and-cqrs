<?php

declare(strict_types=1);

namespace App\Domain\Shared;

trait NamedConstructorTrait
{
    /**
     * @param mixed ...$params
     */
    public static function create(mixed ...$params): static
    {
        /** @phpstan-ignore-next-line */
        return new static(...$params);
    }
}
