<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class Constants
{
    /**
     * Namespace OID for deterministic UUID v5 generation.
     * This ensures that the same email always produces the same User ID.
     */
    public const USER_NAMESPACE = '6ba7b812-9bad-11d1-80b4-00c04fd430c8';
}
