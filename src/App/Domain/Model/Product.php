<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\ProductRegistered;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

final class Product extends AggregateRoot
{
    private string $name;
    private float $price;
    private string $currency;
    private string $type;
    private string $supplierId;
    /** @var array<string, mixed> */
    private array $details;

    /**
     * @param array<string, mixed> $details
     */
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

    public function getSnapshotState(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'type' => $this->type,
            'supplierId' => $this->supplierId,
            'details' => $this->details,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->name = TypeAssert::string($state['name'] ?? null);
        $this->price = TypeAssert::float($state['price'] ?? null);
        $this->currency = TypeAssert::string($state['currency'] ?? null);
        $this->type = TypeAssert::string($state['type'] ?? null);
        $this->supplierId = TypeAssert::string($state['supplierId'] ?? null);
        $this->details = TypeAssert::array($state['details'] ?? null);
    }
}
