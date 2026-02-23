<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\ProductRegistered;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\ProductName;
use App\Domain\ValueObject\ProductType;
use App\Domain\ValueObject\UuidString;
use Symfony\Component\Uid\Uuid;

final class Product extends AggregateRoot
{
    private ProductName $name;
    private Money $price;
    private ProductType $type;
    private UuidString $supplierId;
    /** @var array<string, mixed> */
    private array $details;

    /**
     * @param array<string, mixed> $details
     */
    public static function register(
        Uuid $id,
        ProductName $name,
        Money $price,
        ProductType $type,
        UuidString $supplierId,
        array $details
    ): self {
        $product = new self($id);
        $product->recordThat(new ProductRegistered(
            productId: $id->toRfc4122(),
            name: $name->toString(),
            price: $price->amount()->toFloat(),
            currency: $price->currency()->toString(),
            type: $type->toString(),
            supplierId: $supplierId->toString(),
            details: $details,
            occurredOn: new \DateTimeImmutable()
        ));

        return $product;
    }

    protected function apply(object $event): void
    {
        if ($event instanceof ProductRegistered) {
            $this->name = ProductName::fromString($event->name);
            $this->price = Money::fromFloat($event->price, Currency::fromString($event->currency));
            $this->type = ProductType::fromString($event->type);
            $this->supplierId = UuidString::fromString($event->supplierId);
            $this->details = $event->details;
        }
    }

    public function getSnapshotState(): array
    {
        return [
            'name' => $this->name->toString(),
            'price' => $this->price->amount()->toFloat(),
            'currency' => $this->price->currency()->toString(),
            'type' => $this->type->toString(),
            'supplierId' => $this->supplierId->toString(),
            'details' => $this->details,
        ];
    }

    protected function applySnapshot(array $state): void
    {
        $this->name = ProductName::fromString(TypeAssert::string($state['name'] ?? null));
        $this->price = Money::fromFloat(
            TypeAssert::float($state['price'] ?? null),
            Currency::fromString(TypeAssert::string($state['currency'] ?? null))
        );
        $this->type = ProductType::fromString(TypeAssert::string($state['type'] ?? null));
        $this->supplierId = UuidString::fromString(TypeAssert::string($state['supplierId'] ?? null));
        $this->details = TypeAssert::array($state['details'] ?? null);
    }
}
