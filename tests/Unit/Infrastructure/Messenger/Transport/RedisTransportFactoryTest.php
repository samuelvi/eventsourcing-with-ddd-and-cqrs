<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messenger\Transport;

use App\Infrastructure\Messenger\Transport\RedisTransport;
use App\Infrastructure\Messenger\Transport\RedisTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class RedisTransportFactoryTest extends TestCase
{
    public function testCreateTransportUsesVisibilityTimeoutOption(): void
    {
        $factory = new RedisTransportFactory();
        $serializer = new RedisFactorySerializer();

        $transport = $factory->createTransport(
            'appredis://redis-test:6379/async',
            ['queue' => 'async', 'visibility_timeout_ms' => 2500],
            $serializer
        );

        self::assertInstanceOf(RedisTransport::class, $transport);
        self::assertSame(2500, $this->readIntProperty($transport, 'visibilityTimeoutMs'));
    }

    public function testSupportsRecognizesAppRedisScheme(): void
    {
        $factory = new RedisTransportFactory();

        self::assertTrue($factory->supports('appredis://redis:6379/async', []));
        self::assertFalse($factory->supports('redis://redis:6379/async', []));
    }

    private function readIntProperty(object $object, string $property): int
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $value = $prop->getValue($object);
        self::assertIsInt($value);

        return $value;
    }
}

final readonly class RedisFactorySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        return new Envelope((object) ['body' => $encodedEnvelope['body']]);
    }

    public function encode(Envelope $envelope): array
    {
        return ['body' => '{}', 'headers' => []];
    }
}
