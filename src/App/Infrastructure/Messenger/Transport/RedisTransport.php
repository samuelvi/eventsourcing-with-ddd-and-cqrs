<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class RedisTransport implements TransportInterface
{
    private const KEY_PREFIX = 'messenger';
    private const MAX_DELAY_PROMOTION = 100;

    private object|null $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $queue,
        private readonly SerializerInterface $serializer,
    ) {}

    public function get(): iterable
    {
        /** @var \Redis $redis */
        $redis = $this->redis();
        $this->promoteDelayedMessages($redis);

        $raw = $redis->rPop($this->readyKey());
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new TransportException('Unable to decode Redis transport payload.');
        }

        $messageId = is_string($decoded['id'] ?? null) ? $decoded['id'] : uniqid('redis-msg-', true);
        return [
            $this->serializer->decode($this->toEncodedEnvelope($decoded['envelope'] ?? null))
                ->with(new TransportMessageIdStamp($messageId))
        ];
    }

    public function ack(Envelope $envelope): void
    {
        // Redis list transport removes messages eagerly on read.
    }

    public function reject(Envelope $envelope): void
    {
        // Retry/failure is managed by Messenger middleware and failure transport.
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var \Redis $redis */
        $redis = $this->redis();
        $messageId = uniqid('redis-msg-', true);
        $payload = [
            'id' => $messageId,
            'envelope' => $this->serializer->encode($envelope),
        ];
        $jsonPayload = json_encode($payload);
        if (!is_string($jsonPayload)) {
            throw new TransportException('Unable to encode Redis transport payload.');
        }

        $delayStamp = $envelope->last(DelayStamp::class);
        if ($delayStamp instanceof DelayStamp) {
            $availableAtMs = $this->nowInMs() + $delayStamp->getDelay();
            $redis->zAdd($this->delayedKey(), $availableAtMs, $jsonPayload);
        } else {
            $redis->lPush($this->readyKey(), $jsonPayload);
        }

        return $envelope->with(new TransportMessageIdStamp($messageId));
    }

    private function promoteDelayedMessages(\Redis $redis): void
    {
        $now = $this->nowInMs();
        $dueMessages = $redis->zRangeByScore(
            $this->delayedKey(),
            '-inf',
            (string) $now,
            ['limit' => [0, self::MAX_DELAY_PROMOTION]]
        );

        if (!is_array($dueMessages) || $dueMessages === []) {
            return;
        }

        foreach ($dueMessages as $message) {
            if (!is_string($message)) {
                continue;
            }

            $redis->zRem($this->delayedKey(), $message);
            $redis->lPush($this->readyKey(), $message);
        }
    }

    private function readyKey(): string
    {
        return sprintf('%s:%s:ready', self::KEY_PREFIX, $this->queue);
    }

    private function delayedKey(): string
    {
        return sprintf('%s:%s:delayed', self::KEY_PREFIX, $this->queue);
    }

    private function nowInMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function redis(): object
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!class_exists(\Redis::class)) {
            throw new TransportException('Redis extension is not installed.');
        }

        /** @var \Redis $redis */
        $redis = new \Redis();
        $connected = $redis->connect($this->host, $this->port);
        if ($connected !== true) {
            throw new TransportException(sprintf('Unable to connect to Redis at %s:%d.', $this->host, $this->port));
        }

        $this->client = $redis;

        return $this->client;
    }

    /**
     * @param mixed $payload
     * @return array{body: string, headers?: array<string, string>}
     */
    private function toEncodedEnvelope(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new TransportException('Invalid Redis encoded envelope payload.');
        }

        $body = $payload['body'] ?? null;
        if (!is_string($body)) {
            throw new TransportException('Invalid Redis encoded envelope body.');
        }

        $headers = $payload['headers'] ?? null;
        if ($headers === null) {
            return ['body' => $body];
        }

        if (!is_array($headers)) {
            throw new TransportException('Invalid Redis encoded envelope headers.');
        }

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new TransportException('Invalid Redis encoded envelope header entry.');
            }
            $normalizedHeaders[$key] = $value;
        }

        return [
            'body' => $body,
            'headers' => $normalizedHeaders,
        ];
    }
}
