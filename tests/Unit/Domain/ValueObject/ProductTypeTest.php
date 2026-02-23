<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\ProductType;
use PHPUnit\Framework\TestCase;

final class ProductTypeTest extends TestCase
{
    public function testNormalizesToLowercase(): void
    {
        $type = ProductType::fromString(' MENU_ITEM ');

        self::assertSame('menu_item', $type->toString());
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductType::fromString('$$');
    }
}
