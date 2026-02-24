<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @implements TransportFactoryInterface<TransportInterface>
 */
final class RedisTransportFactory implements TransportFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parts = parse_url($dsn);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'appredis') {
            throw new \InvalidArgumentException(sprintf('Invalid Redis transport DSN: "%s".', $dsn));
        }

        $host = (string) ($parts['host'] ?? 'redis');
        $port = (int) ($parts['port'] ?? 6379);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $queue = is_string($options['queue'] ?? null) && $options['queue'] !== ''
            ? $options['queue']
            : ($path !== '' ? $path : 'async');
        $visibilityTimeoutMs = is_numeric($options['visibility_timeout_ms'] ?? null)
            ? max(1000, (int) $options['visibility_timeout_ms'])
            : 60000;

        return new RedisTransport($host, $port, $queue, $serializer, $visibilityTimeoutMs);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'appredis://');
    }
}
