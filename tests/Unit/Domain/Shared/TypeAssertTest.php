<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\TypeAssert;
use PHPUnit\Framework\TestCase;

final class TypeAssertTest extends TestCase
{
    public function testStringPasses(): void
    {
        $this->assertSame('test', TypeAssert::string('test'));
    }

    public function testStringFailsForInt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeAssert::string(123);
    }

    public function testIntPasses(): void
    {
        $this->assertSame(123, TypeAssert::int(123));
    }

    public function testIntPassesForStringInt(): void
    {
        $this->assertSame(123, TypeAssert::int('123'));
    }

    public function testIntFailsForFloat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeAssert::int(12.34);
    }

    public function testFloatPasses(): void
    {
        $this->assertSame(12.34, TypeAssert::float(12.34));
    }

    public function testFloatPassesForStringFloat(): void
    {
        $this->assertSame(12.34, TypeAssert::float('12.34'));
    }

    public function testFloatPassesForInt(): void
    {
        $this->assertSame(10.0, TypeAssert::float(10));
    }

    public function testArrayPasses(): void
    {
        $arr = ['key' => 'val'];
        $this->assertSame($arr, TypeAssert::array($arr));
    }

    public function testArrayFailsForString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeAssert::array('json');
    }
}
