<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class ProductRegistered
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $productId,
        public string $name,
        public float $price,
        public string $currency,
        public string $type,
        public string $supplierId,
        public array $details,
        public \DateTimeImmutable $occurredOn
    ) {}
}
