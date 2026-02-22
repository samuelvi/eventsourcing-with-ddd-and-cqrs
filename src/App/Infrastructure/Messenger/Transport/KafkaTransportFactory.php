<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @implements TransportFactoryInterface<TransportInterface>
 */
final class KafkaTransportFactory implements TransportFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parts = parse_url($dsn);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'appkafka') {
            throw new \InvalidArgumentException(sprintf('Invalid Kafka transport DSN: "%s".', $dsn));
        }

        $host = (string) ($parts['host'] ?? 'kafka');
        $port = (int) ($parts['port'] ?? 9092);
        $path = trim((string) ($parts['path'] ?? ''), '/');

        $topic = is_string($options['topic'] ?? null) && $options['topic'] !== ''
            ? $options['topic']
            : ($path !== '' ? $path : 'async');
        $groupId = is_string($options['group_id'] ?? null) && $options['group_id'] !== ''
            ? $options['group_id']
            : sprintf('messenger-%s-group', $topic);

        return new KafkaTransport(
            sprintf('%s:%d', $host, $port),
            $topic,
            $groupId,
            $serializer,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'appkafka://');
    }
}
