<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\ProductRegistered;
use Symfony\Component\Uid\Uuid;

final class Product extends AggregateRoot
{
    private string $name;
    private float $price;
    private string $currency;
    private string $type;
    private string $supplierId;
    private array $details;

    public static function register(
        Uuid $id,
        string $name,
        float $price,
        string $currency,
        string $type,
        string $supplierId,
        array $details
    ): self {
        $product = new self($id);
        $product->recordThat(new ProductRegistered(
            productId: $id->toRfc4122(),
            name: $name,
            price: $price,
            currency: $currency,
            type: $type,
            supplierId: $supplierId,
            details: $details,
            occurredOn: new \DateTimeImmutable()
        ));

        return $product;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof ProductRegistered) {
            $this->name = $event->name;
            $this->price = $event->price;
            $this->currency = $event->currency;
            $this->type = $event->type;
            $this->supplierId = $event->supplierId;
            $this->details = $event->details;
        }
    }
}
