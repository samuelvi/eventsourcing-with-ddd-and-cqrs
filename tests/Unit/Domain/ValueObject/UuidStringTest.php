<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\UuidString;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UuidStringTest extends TestCase
{
    public function testAcceptsValidUuid(): void
    {
        $id = Uuid::v7()->toRfc4122();

        self::assertSame($id, UuidString::fromString($id)->toString());
    }

    public function testRejectsInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UuidString::fromString('not-a-uuid');
    }
}
