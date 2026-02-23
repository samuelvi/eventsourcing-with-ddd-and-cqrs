<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\PersonName;
use PHPUnit\Framework\TestCase;

final class PersonNameTest extends TestCase
{
    public function testTrimsName(): void
    {
        $name = PersonName::fromString('  Ada Lovelace  ');

        self::assertSame('Ada Lovelace', $name->toString());
    }

    public function testRejectsShortName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PersonName::fromString('A');
    }
}
