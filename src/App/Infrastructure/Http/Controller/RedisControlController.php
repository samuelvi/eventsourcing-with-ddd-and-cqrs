<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RedisControlController extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernel,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $defaultConnection,
    ) {}

    #[Route('/api/redis-control/status', methods: ['GET'])]
    public function status(): Response
    {
        try {
            $this->assertEnvironmentIsAllowed();

            $redis = $this->connect();

            return new JsonResponse([
                'status' => 'ok',
                'host' => $this->redisHost(),
                'port' => $this->redisPort(),
                'database' => $this->redisDatabase(),
                'ping' => (string) $redis->ping(),
                'dbSize' => $redis->dbSize(),
                'keyspace' => $this->extractKeyspaceInfo($redis),
                'queueMetrics' => $this->collectQueueMetrics($redis),
                'sampleBullKeys' => $this->scanKeys($redis, 'bull:*', 30),
                'sampleN8nCacheKeys' => $this->scanKeys($redis, 'n8n:cache:*', 30),
                'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
        } catch (AccessDeniedHttpException $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_FORBIDDEN
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/redis-control/jobs', methods: ['GET'])]
    public function jobs(Request $request): Response
    {
        try {
            $this->assertEnvironmentIsAllowed();

            $limit = max(1, min(100, $request->query->getInt('limit', 25)));
            $redis = $this->connect();

            return new JsonResponse([
                'status' => 'ok',
                'redisJobs' => $this->listBullJobs($redis, $limit),
                'postgresExecutions' => $this->listN8nExecutions($limit),
                'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
        } catch (AccessDeniedHttpException $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_FORBIDDEN
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/redis-control/command/{command}', methods: ['POST'])]
    public function command(string $command): Response
    {
        try {
            $this->assertEnvironmentIsAllowed();

            $redis = $this->connect();

            return match ($command) {
                'ping' => new JsonResponse([
                    'status' => 'ok',
                    'command' => 'ping',
                    'result' => (string) $redis->ping(),
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]),
                'scan-bull' => new JsonResponse([
                    'status' => 'ok',
                    'command' => 'scan-bull',
                    'matched' => count($this->scanKeys($redis, 'bull:*', 200)),
                    'keys' => $this->scanKeys($redis, 'bull:*', 200),
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]),
                'scan-n8n-cache' => new JsonResponse([
                    'status' => 'ok',
                    'command' => 'scan-n8n-cache',
                    'matched' => count($this->scanKeys($redis, 'n8n:cache:*', 200)),
                    'keys' => $this->scanKeys($redis, 'n8n:cache:*', 200),
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]),
                'clear-n8n-cache' => new JsonResponse([
                    'status' => 'ok',
                    'command' => 'clear-n8n-cache',
                    'deleted' => $this->deleteByPattern($redis, 'n8n:cache:*'),
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]),
                default => new JsonResponse(
                    [
                        'status' => 'error',
                        'message' => 'Unsupported command.',
                        'allowedCommands' => ['ping', 'scan-bull', 'scan-n8n-cache', 'clear-n8n-cache'],
                    ],
                    Response::HTTP_BAD_REQUEST
                ),
            };
        } catch (AccessDeniedHttpException $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_FORBIDDEN
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function assertEnvironmentIsAllowed(): void
    {
        if (!in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            throw new AccessDeniedHttpException('Redis controls are only available in dev/test.');
        }
    }

    private function connect(): \Redis
    {
        if (!class_exists(\Redis::class)) {
            throw new \RuntimeException('Redis extension is not installed.');
        }

        $redis = new \Redis();
        $connected = $redis->connect($this->redisHost(), $this->redisPort(), 1.5);
        if (!$connected) {
            throw new \RuntimeException('Unable to connect to Redis.');
        }

        $password = $this->redisPassword();
        if ($password !== null && $password !== '') {
            $redis->auth($password);
        }

        $database = $this->redisDatabase();
        if ($database !== 0) {
            $redis->select($database);
        }

        return $redis;
    }

    private function redisHost(): string
    {
        return $this->envString('N8N_REDIS_HOST')
            ?? $this->envString('QUEUE_REDIS_HOST')
            ?? 'redis';
    }

    private function redisPort(): int
    {
        return $this->envInt('N8N_REDIS_PORT')
            ?? $this->envInt('QUEUE_REDIS_PORT')
            ?? 6379;
    }

    private function redisPassword(): ?string
    {
        return $this->envString('N8N_REDIS_PASSWORD')
            ?? $this->envString('QUEUE_REDIS_PASSWORD');
    }

    private function redisDatabase(): int
    {
        return $this->envInt('N8N_REDIS_DB')
            ?? $this->envInt('QUEUE_REDIS_DB')
            ?? 0;
    }

    private function envString(string $key): ?string
    {
        $raw = $_SERVER[$key] ?? $_ENV[$key] ?? null;
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }

    private function envInt(string $key): ?int
    {
        $raw = $this->envString($key);
        if ($raw === null || !is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @return array<string, array{exists: bool, type: string, size: int, value?: string}>
     */
    private function collectQueueMetrics(\Redis $redis): array
    {
        $queueKeys = [
            'bull:jobs:id',
            'bull:jobs:wait',
            'bull:jobs:active',
            'bull:jobs:delayed',
            'bull:jobs:completed',
            'bull:jobs:failed',
            'bull:jobs:stalled-check',
        ];

        $metrics = [];
        foreach ($queueKeys as $key) {
            $type = (int) $redis->type($key);
            $exists = $type !== \Redis::REDIS_NOT_FOUND;

            $metric = [
                'exists' => $exists,
                'type' => $this->redisTypeToString($type),
                'size' => $this->sizeForType($redis, $key, $type),
            ];

            if ($type === \Redis::REDIS_STRING) {
                $metric['value'] = (string) $redis->get($key);
            }

            $metrics[$key] = $metric;
        }

        return $metrics;
    }

    private function extractKeyspaceInfo(\Redis $redis): string
    {
        $info = $redis->info('keyspace');
        if (!is_array($info)) {
            return '';
        }

        $parts = [];
        foreach ($info as $database => $value) {
            if (is_string($database) && is_string($value)) {
                $parts[] = sprintf('%s=%s', $database, $value);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @return list<string>
     */
    private function scanKeys(\Redis $redis, string $pattern, int $limit): array
    {
        $iterator = null;
        $keys = [];

        do {
            $batch = $redis->scan($iterator, $pattern, 100);
            if ($batch === false) {
                continue;
            }

            foreach ($batch as $key) {
                if (!is_string($key)) {
                    continue;
                }

                $keys[] = $key;
                if (count($keys) >= $limit) {
                    sort($keys);

                    return $keys;
                }
            }
        } while ($iterator !== 0);

        sort($keys);

        return $keys;
    }

    private function deleteByPattern(\Redis $redis, string $pattern): int
    {
        $keys = $this->scanKeys($redis, $pattern, 500);
        if ($keys === []) {
            return 0;
        }

        return (int) $redis->del($keys);
    }

    private function sizeForType(\Redis $redis, string $key, int $type): int
    {
        return match ($type) {
            \Redis::REDIS_STRING => strlen((string) $redis->get($key)),
            \Redis::REDIS_SET => $redis->sCard($key),
            \Redis::REDIS_LIST => $redis->lLen($key),
            \Redis::REDIS_ZSET => $redis->zCard($key),
            \Redis::REDIS_HASH => $redis->hLen($key),
            default => 0,
        };
    }

    private function redisTypeToString(int $type): string
    {
        return match ($type) {
            \Redis::REDIS_STRING => 'string',
            \Redis::REDIS_SET => 'set',
            \Redis::REDIS_LIST => 'list',
            \Redis::REDIS_ZSET => 'zset',
            \Redis::REDIS_HASH => 'hash',
            \Redis::REDIS_NOT_FOUND => 'none',
            default => 'unknown',
        };
    }

    /**
     * @return list<array{id: string, state: string, name: string, attemptsMade: int, key: string, payload: ?string, timestamp: ?string, processedOn: ?string, finishedOn: ?string}>
     */
    private function listBullJobs(\Redis $redis, int $limit): array
    {
        $stateIndex = [
            'wait' => array_flip($this->toStringArray($redis->lRange('bull:jobs:wait', 0, 2000))),
            'active' => array_flip($this->toStringArray($redis->lRange('bull:jobs:active', 0, 2000))),
            'delayed' => array_flip($this->toStringArray($redis->zRange('bull:jobs:delayed', 0, 2000))),
            'completed' => array_flip($this->toStringArray($redis->zRange('bull:jobs:completed', 0, 2000))),
            'failed' => array_flip($this->toStringArray($redis->zRange('bull:jobs:failed', 0, 2000))),
        ];

        $jobKeys = $this->scanKeys($redis, 'bull:jobs:[0-9]*', max($limit * 4, $limit));
        usort($jobKeys, static fn(string $a, string $b): int => self::extractJobId($b) <=> self::extractJobId($a));

        $jobs = [];
        foreach ($jobKeys as $key) {
            if (count($jobs) >= $limit) {
                break;
            }

            $jobId = (string) self::extractJobId($key);
            if ($jobId === '' || $jobId === '0') {
                continue;
            }

            $type = (int) $redis->type($key);
            if ($type !== \Redis::REDIS_HASH) {
                continue;
            }

            $job = $redis->hGetAll($key);
            if (!is_array($job)) {
                continue;
            }

            $jobs[] = [
                'id' => $jobId,
                'state' => $this->resolveJobState($jobId, $stateIndex),
                'name' => $this->stringOrDefault($job['name'] ?? null, 'unnamed'),
                'attemptsMade' => $this->intOrDefault($job['attemptsMade'] ?? null, 0),
                'key' => $key,
                'payload' => $this->payloadPreview($job['data'] ?? null),
                'timestamp' => $this->millisToIso($job['timestamp'] ?? null),
                'processedOn' => $this->millisToIso($job['processedOn'] ?? null),
                'finishedOn' => $this->millisToIso($job['finishedOn'] ?? null),
            ];
        }

        return $jobs;
    }

    /**
     * @return list<array{id: int, status: ?string, mode: ?string, workflowId: ?string, startedAt: ?string, stoppedAt: ?string, data: array{bookingId: ?string, productId: ?string, supplierId: ?string, quoteId: ?string}}>
     */
    private function listN8nExecutions(int $limit): array
    {
        try {
            $query = <<<'SQL'
SELECT
    e.id,
    e.status,
    e.mode,
    e."workflowId",
    e."startedAt",
    e."stoppedAt",
    ed.data AS "executionData"
FROM execution_entity e
LEFT JOIN execution_data ed ON ed."executionId" = e.id
ORDER BY e.id DESC
LIMIT :limit
SQL;

            $rows = $this->defaultConnection->fetchAllAssociative(
                $query,
                ['limit' => $limit],
                ['limit' => ParameterType::INTEGER]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'status' => $this->nullableString($row['status'] ?? null),
                'mode' => $this->nullableString($row['mode'] ?? null),
                'workflowId' => $this->nullableString($row['workflowId'] ?? null),
                'startedAt' => $this->normalizeDateTime($row['startedAt'] ?? null),
                'stoppedAt' => $this->normalizeDateTime($row['stoppedAt'] ?? null),
                'data' => $this->extractExecutionIdentifiers(
                    is_string($row['executionData'] ?? null) ? $row['executionData'] : null
                ),
            ],
            $rows
        );
    }

    /**
     * @return array{bookingId: ?string, productId: ?string, supplierId: ?string, quoteId: ?string}
     */
    private function extractExecutionIdentifiers(?string $rawData): array
    {
        $identifiers = [
            'bookingId' => null,
            'productId' => null,
            'supplierId' => null,
            'quoteId' => null,
        ];

        if ($rawData === null || trim($rawData) === '') {
            return $identifiers;
        }

        $decoded = $this->decodeExecutionData($rawData);
        if ($decoded === null) {
            return $identifiers;
        }

        $this->collectIdentifiers($decoded, $identifiers);

        return $identifiers;
    }

    private function decodeExecutionData(string $rawData): mixed
    {
        try {
            $parsed = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($parsed)) {
            return null;
        }

        if (!array_key_exists(0, $parsed)) {
            return $parsed;
        }

        $cache = [];
        $resolving = [];

        return $this->resolveFlattedValue($parsed[0], $parsed, $cache, $resolving);
    }

    /**
     * @param array<int, mixed> $store
     * @param array<int, mixed> $cache
     * @param array<int, bool> $resolving
     */
    private function resolveFlattedValue(mixed $value, array $store, array &$cache, array &$resolving): mixed
    {
        if (is_string($value) && ctype_digit($value)) {
            $index = (int) $value;
            if (!array_key_exists($index, $store)) {
                return $value;
            }

            if (array_key_exists($index, $cache)) {
                return $cache[$index];
            }

            if (($resolving[$index] ?? false) === true) {
                return null;
            }

            $resolving[$index] = true;
            $resolved = $this->resolveFlattedValue($store[$index], $store, $cache, $resolving);
            unset($resolving[$index]);
            $cache[$index] = $resolved;

            return $resolved;
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $child) {
            $result[$key] = $this->resolveFlattedValue($child, $store, $cache, $resolving);
        }

        return $result;
    }

    /**
     * @param array{bookingId: ?string, productId: ?string, supplierId: ?string, quoteId: ?string} $identifiers
     */
    private function collectIdentifiers(mixed $value, array &$identifiers, int $depth = 0): void
    {
        if ($depth > 30) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            if (is_string($key) && array_key_exists($key, $identifiers)) {
                $candidate = $this->scalarToString($child);
                if ($candidate !== null && $identifiers[$key] === null) {
                    $identifiers[$key] = $candidate;
                }
            }

            $this->collectIdentifiers($child, $identifiers, $depth + 1);
        }
    }

    private function scalarToString(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param array<string, array<int, string>> $stateIndex
     */
    private function resolveJobState(string $jobId, array $stateIndex): string
    {
        if (array_key_exists($jobId, $stateIndex['failed'] ?? [])) {
            return 'failed';
        }

        if (array_key_exists($jobId, $stateIndex['active'] ?? [])) {
            return 'active';
        }

        if (array_key_exists($jobId, $stateIndex['wait'] ?? [])) {
            return 'wait';
        }

        if (array_key_exists($jobId, $stateIndex['delayed'] ?? [])) {
            return 'delayed';
        }

        if (array_key_exists($jobId, $stateIndex['completed'] ?? [])) {
            return 'completed';
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    private function toStringArray(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $result[] = (string) $value;
            }
        }

        return $result;
    }

    private static function extractJobId(string $key): int
    {
        if (!preg_match('/:(\d+)$/', $key, $matches)) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
    }

    private function payloadPreview(mixed $payload): ?string
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $compact = preg_replace('/\s+/', ' ', $payload);
        if (!is_string($compact)) {
            return null;
        }

        return strlen($compact) > 240 ? substr($compact, 0, 240) . '...' : $compact;
    }

    private function millisToIso(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $seconds = ((float) $value) / 1000;
        $date = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $seconds));
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    private function stringOrDefault(mixed $value, string $fallback): string
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return $value;
    }

    private function intOrDefault(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
