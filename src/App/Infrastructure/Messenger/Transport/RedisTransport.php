<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport;

use App\Infrastructure\Messenger\Transport\Stamp\RedisReceivedStamp;
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
    private const MAX_INFLIGHT_REQUEUE = 100;
    private const MAX_PROCESSING_SCAN = 200;
    private const LUA_PROMOTE_DELAYED = <<<'LUA'
local delayed = KEYS[1]
local ready = KEYS[2]
local now = ARGV[1]
local maxItems = tonumber(ARGV[2])
local due = redis.call('ZRANGEBYSCORE', delayed, '-inf', now, 'LIMIT', 0, maxItems)

for _, message in ipairs(due) do
    redis.call('ZREM', delayed, message)
    redis.call('LPUSH', ready, message)
end

return due
LUA;
    private const LUA_REQUEUE_EXPIRED_INFLIGHT = <<<'LUA'
local inflight = KEYS[1]
local inflightPayload = KEYS[2]
local processing = KEYS[3]
local ready = KEYS[4]
local now = ARGV[1]
local maxItems = tonumber(ARGV[2])
local expired = redis.call('ZRANGEBYSCORE', inflight, '-inf', now, 'LIMIT', 0, maxItems)

for _, messageId in ipairs(expired) do
    local payload = redis.call('HGET', inflightPayload, messageId)
    if payload then
        redis.call('LREM', processing, 1, payload)
        redis.call('LPUSH', ready, payload)
    end

    redis.call('ZREM', inflight, messageId)
    redis.call('HDEL', inflightPayload, messageId)
end

return expired
LUA;

    private object|null $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $queue,
        private readonly SerializerInterface $serializer,
        private readonly int $visibilityTimeoutMs = 60000,
    ) {}

    public function get(): iterable
    {
        /** @var \Redis $redis */
        $redis = $this->redis();
        $this->promoteDelayedMessages($redis);
        $this->requeueExpiredInflightMessages($redis);
        $this->claimOrphanedProcessingMessages($redis);

        $raw = $redis->rPopLPush($this->readyKey(), $this->processingKey());
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        [$messageId, $encodedEnvelope] = $this->decodeRawEnvelope($raw);
        $this->markInflight($redis, $messageId, $raw);

        return [
            $this->serializer->decode($encodedEnvelope)
                ->with(new RedisReceivedStamp($messageId))
                ->with(new TransportMessageIdStamp($messageId))
        ];
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = $envelope->last(RedisReceivedStamp::class);
        if (!$stamp instanceof RedisReceivedStamp) {
            return;
        }

        /** @var \Redis $redis */
        $redis = $this->redis();
        $this->completeInflight($redis, $stamp->messageId);
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $envelope->last(RedisReceivedStamp::class);
        if (!$stamp instanceof RedisReceivedStamp) {
            return;
        }

        /** @var \Redis $redis */
        $redis = $this->redis();
        $this->completeInflight($redis, $stamp->messageId);
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
        $atomicResult = $this->runLua(
            $redis,
            self::LUA_PROMOTE_DELAYED,
            [$this->delayedKey(), $this->readyKey(), (string) $now, (string) self::MAX_DELAY_PROMOTION],
            2
        );

        if ($atomicResult !== null) {
            return;
        }

        // Fallback path when Lua is unavailable; keeps behavior correct but not fully atomic.
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

    private function processingKey(): string
    {
        return sprintf('%s:%s:processing', self::KEY_PREFIX, $this->queue);
    }

    private function delayedKey(): string
    {
        return sprintf('%s:%s:delayed', self::KEY_PREFIX, $this->queue);
    }

    private function inflightKey(): string
    {
        return sprintf('%s:%s:inflight', self::KEY_PREFIX, $this->queue);
    }

    private function inflightPayloadKey(): string
    {
        return sprintf('%s:%s:inflight_payload', self::KEY_PREFIX, $this->queue);
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

    private function requeueExpiredInflightMessages(\Redis $redis): void
    {
        $now = $this->nowInMs();
        $atomicResult = $this->runLua(
            $redis,
            self::LUA_REQUEUE_EXPIRED_INFLIGHT,
            [
                $this->inflightKey(),
                $this->inflightPayloadKey(),
                $this->processingKey(),
                $this->readyKey(),
                (string) $now,
                (string) self::MAX_INFLIGHT_REQUEUE
            ],
            4
        );

        if ($atomicResult !== null) {
            return;
        }

        // Fallback path when Lua is unavailable; keeps behavior correct but not fully atomic.
        $expiredIds = $redis->zRangeByScore(
            $this->inflightKey(),
            '-inf',
            (string) $now,
            ['limit' => [0, self::MAX_INFLIGHT_REQUEUE]]
        );

        if (!is_array($expiredIds) || $expiredIds === []) {
            return;
        }

        foreach ($expiredIds as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }

            $payload = $redis->hGet($this->inflightPayloadKey(), $id);
            if (is_string($payload) && $payload !== '') {
                $redis->lRem($this->processingKey(), $payload, 1);
                $redis->lPush($this->readyKey(), $payload);
            }

            $redis->zRem($this->inflightKey(), $id);
            $redis->hDel($this->inflightPayloadKey(), $id);
        }
    }

    private function claimOrphanedProcessingMessages(\Redis $redis): void
    {
        $processing = $redis->lRange($this->processingKey(), 0, self::MAX_PROCESSING_SCAN - 1);
        if (!is_array($processing) || $processing === []) {
            return;
        }

        foreach ($processing as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            try {
                [$messageId] = $this->decodeRawEnvelope($raw);
            } catch (TransportException) {
                continue;
            }

            $score = $redis->zScore($this->inflightKey(), $messageId);
            if ($score !== false) {
                continue;
            }

            $this->markInflight($redis, $messageId, $raw);
        }
    }

    private function markInflight(\Redis $redis, string $messageId, string $raw): void
    {
        $deadline = $this->nowInMs() + max(1000, $this->visibilityTimeoutMs);
        $redis->hSet($this->inflightPayloadKey(), $messageId, $raw);
        $redis->zAdd($this->inflightKey(), $deadline, $messageId);
    }

    private function completeInflight(\Redis $redis, string $messageId): void
    {
        $payload = $redis->hGet($this->inflightPayloadKey(), $messageId);
        if (is_string($payload) && $payload !== '') {
            $redis->lRem($this->processingKey(), $payload, 1);
        }

        $redis->zRem($this->inflightKey(), $messageId);
        $redis->hDel($this->inflightPayloadKey(), $messageId);
    }

    /**
     * @return array{0: string, 1: array{body: string, headers?: array<string, string>}}
     */
    private function decodeRawEnvelope(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new TransportException('Unable to decode Redis transport payload.');
        }

        $messageId = is_string($decoded['id'] ?? null) ? $decoded['id'] : '';
        if ($messageId === '') {
            throw new TransportException('Redis transport payload is missing a valid message id.');
        }

        return [$messageId, $this->toEncodedEnvelope($decoded['envelope'] ?? null)];
    }

    /**
     * @param list<string> $args
     * @return array<mixed, mixed>|null
     */
    private function runLua(\Redis $redis, string $script, array $args, int $numKeys): ?array
    {
        $result = $redis->eval($script, $args, $numKeys);
        if ($result === false) {
            return null;
        }

        if (!is_array($result)) {
            return [];
        }

        return $result;
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
