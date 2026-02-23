<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testFromNullableReturnsNullForBlankString(): void
    {
        self::assertNull(Address::fromNullable('   '));
    }

    public function testFromStringTrimsAddress(): void
    {
        $address = Address::fromString('  Street 1  ');

        self::assertSame('Street 1', $address->toString());
    }
}
