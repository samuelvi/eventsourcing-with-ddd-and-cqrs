<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testNormalizesAndValidatesEmail(): void
    {
        $email = Email::fromString('  USER@Example.COM ');

        self::assertSame('user@example.com', $email->toString());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString('invalid-email');
    }
}
