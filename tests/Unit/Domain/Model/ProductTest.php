<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\ProductRegistered;
use App\Domain\Model\Product;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\ProductName;
use App\Domain\ValueObject\ProductType;
use App\Domain\ValueObject\UuidString;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProductTest extends TestCase
{
    public function testRegisterRecordsProductRegisteredEvent(): void
    {
        $product = Product::register(
            Uuid::v7(),
            ProductName::fromString('Menu Premium'),
            Money::fromFloat(49.99, Currency::fromString('eur')),
            ProductType::fromString('menu'),
            UuidString::fromString(Uuid::v7()->toRfc4122()),
            ['description' => 'Test menu']
        );

        $events = $product->getRecordedEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductRegistered::class, $events[0]);
        self::assertSame('EUR', $events[0]->currency);
        self::assertSame(49.99, $events[0]->price);
    }
}
