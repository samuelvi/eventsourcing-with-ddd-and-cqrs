<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\ProductName;
use PHPUnit\Framework\TestCase;

final class ProductNameTest extends TestCase
{
    public function testTrimsAndKeepsName(): void
    {
        $name = ProductName::fromString('  Menu Ejecutivo  ');

        self::assertSame('Menu Ejecutivo', $name->toString());
    }

    public function testRejectsBlankName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductName::fromString('   ');
    }
}
