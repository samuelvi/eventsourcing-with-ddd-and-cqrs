<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\EventSourcing\Snapshot;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class ArchitectureControlService
{
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_USER_PROJECTIONS = 'demo_user_projections_enabled';
    private const CACHE_KEY_BOOKING_PROJECTIONS = 'demo_booking_projections_enabled';
    private const CACHE_KEY_USER_ADDRESS_SCHEMA = 'demo_user_address_schema_enabled';

    public function __construct(
        private CacheInterface $cache,
        private ReadEntityManager $readEntityManager,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
        private KernelInterface $kernel,
        private \App\Domain\Repository\UserReadRepositoryInterface $userRepository,
        private \App\Domain\Repository\BookingReadRepositoryInterface $bookingRepository,
        #[\Symfony\Component\DependencyInjection\Attribute\Target('messaging')]
        private \Doctrine\DBAL\Connection $messagingConnection,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function getStatus(): array
    {
        return [
            'projectionsEnabled' => $this->cache->get(self::CACHE_KEY_MASTER, fn() => true),
            'userProjectionsEnabled' => $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true),
            'bookingProjectionsEnabled' => $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true),
            'userAddressSchemaEnabled' => $this->cache->get(self::CACHE_KEY_USER_ADDRESS_SCHEMA, fn() => false),
        ];
    }

    public function toggle(string $type): bool
    {
        $key = match($type) {
            'master' => self::CACHE_KEY_MASTER,
            'user' => self::CACHE_KEY_USER_PROJECTIONS,
            'booking' => self::CACHE_KEY_BOOKING_PROJECTIONS,
            default => throw new \InvalidArgumentException('Invalid type')
        };
        
        $current = $this->cache->get($key, fn() => true);
        $newValue = !$current;

        $this->cache->delete($key);
        $this->cache->get($key, fn() => $newValue);

        return $newValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $checkpoints = $this->mongoStore->findAllCheckpoints();
        $checkpointsMap = [];
        foreach ($checkpoints as $cp) {
            $checkpointsMap[$cp->projectionName] = $cp->lastEventId?->toRfc4122();
        }

        $asyncCount = 0;
        $failedCount = 0;

        try {
            $asyncCount = $this->toIntCount($this->messagingConnection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :q',
                ['q' => 'async']
            ));
            $failedCount = $this->toIntCount($this->messagingConnection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :q',
                ['q' => 'failed']
            ));
        } catch (\Exception) {
            // Table might not exist yet
        }

        return [
            'events' => $this->mongoStore->countEvents(),
            'users' => $this->userRepository->countAll(),
            'bookings' => $this->bookingRepository->countAll(),
            'snapshots' => $this->mongoStore->countSnapshots(),
            'checkpoints' => $checkpointsMap,
            'queue' => [
                'async' => $asyncCount,
                'failed' => $failedCount
            ]
        ];
    }

    public function takeSnapshot(): int
    {
        $eventCount = $this->mongoStore->countEvents();
        $snapshot = \App\Infrastructure\EventSourcing\Snapshot::take(
            Uuid::v7(), // System aggregate ID for demo
            $eventCount,
            [
                'users' => $this->userRepository->countAll(),
                'bookings' => $this->bookingRepository->countAll(),
                'timestamp' => time()
            ]
        );

        $this->mongoStore->saveSnapshot($snapshot);

        return $eventCount;
    }

    public function rebuild(): int
    {
        return $this->rebuildFromMongo();
    }

    public function clearTransactionalData(): void
    {
        // Keep catalog/quote tables intact for demo recovery flows.
        $this->readEntityManager->execute('TRUNCATE users, bookings RESTART IDENTITY CASCADE');
    }

    public function rebuildFromMongo(): int
    {
        // 1. Force both toggles back to enabled for the rebuild process
        $this->enableAll();

        // 2. Clear SQL Read Models
        $this->clearTransactionalData();
        
        // 3. Clear Mongo Checkpoints (KEEP EVENTS)
        $this->mongoStore->clearCheckpoints();

        // 4. Fast bootstrap from latest snapshots (currently user aggregates).
        $this->seedUserReadModelFromLatestSnapshots();

        // 5. Replay event deltas using streaming merge with latest snapshots
        // to avoid loading millions of rows into PHP memory.
        $snapshotIterator = $this->asIterator($this->mongoStore->iterateLatestSnapshotsByAggregateId());
        $currentSnapshot = $snapshotIterator->valid() ? $snapshotIterator->current() : null;
        $replayedEvents = 0;

        foreach ($this->mongoStore->iterateEventsForReplay() as $storedEvent) {
            $aggregateId = $storedEvent->aggregateId->toRfc4122();

            while (
                $currentSnapshot instanceof Snapshot
                && strcmp($currentSnapshot->aggregateId->toRfc4122(), $aggregateId) < 0
            ) {
                $snapshotIterator->next();
                $currentSnapshot = $snapshotIterator->valid() ? $snapshotIterator->current() : null;
            }

            if (
                $currentSnapshot instanceof Snapshot
                && $currentSnapshot->aggregateId->toRfc4122() === $aggregateId
                && $storedEvent->version <= $currentSnapshot->version
            ) {
                continue;
            }

            $event = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );
            $this->eventBus->dispatch($event);
            $replayedEvents++;
        }

        return $replayedEvents;
    }

    public function reset(): void
    {
        // 1. Force everything back to enabled
        $this->enableAll();

        // 2. Clear SQL Tables (Aggressive + Identity Reset)
        $this->readEntityManager->execute('TRUNCATE users, bookings, products, menus, suppliers, quotes RESTART IDENTITY CASCADE');

        // 3. Clear Mongo (Events, Checkpoints, Snapshots)
        $this->mongoStore->clearAll();

        // 4. Load Fixtures via Console Application
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($this->kernel);
        $application->setAutoExit(false);
        
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--append' => true,
        ]);
        
        $exitCode = $application->run($input, $output);
        
        if ($exitCode !== 0) {
            throw new \RuntimeException('Fixtures failed: ' . $output->fetch());
        }
    }

    public function enableAll(): void
    {
        $this->cache->delete(self::CACHE_KEY_MASTER);
        $this->cache->delete(self::CACHE_KEY_USER_PROJECTIONS);
        $this->cache->delete(self::CACHE_KEY_BOOKING_PROJECTIONS);
        $this->cache->delete(self::CACHE_KEY_USER_ADDRESS_SCHEMA);
    }

    public function enableUserAddressSchemaEvolution(): void
    {
        // Simulate DB schema evolution in a safe/idempotent way for the demo.
        $this->readEntityManager->execute('ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL');

        $this->cache->delete(self::CACHE_KEY_USER_ADDRESS_SCHEMA);
        $this->cache->get(self::CACHE_KEY_USER_ADDRESS_SCHEMA, fn() => true);
    }

    private function toIntCount(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function seedUserReadModelFromLatestSnapshots(): void
    {
        foreach ($this->mongoStore->iterateLatestSnapshotsByAggregateId() as $snapshot) {
            if (!$this->isUserSnapshotState($snapshot->state)) {
                continue;
            }

            $aggregateId = $snapshot->aggregateId->toRfc4122();

            // Deleted users are intentionally not upserted, but we still skip historical
            // events up to snapshot version to avoid replaying full history.
            $isDeleted = (bool) ($snapshot->state['deleted'] ?? false);
            if ($isDeleted) {
                continue;
            }

            $name = $this->toNullableString($snapshot->state['name'] ?? null);
            $email = $this->toNullableString($snapshot->state['email'] ?? null);
            if ($name === null || $email === null) {
                continue;
            }

            $address = $this->toNullableString($snapshot->state['address'] ?? null);

            $this->readEntityManager->execute(
                <<<'SQL'
                INSERT INTO users (id, name, email, address, created_at)
                VALUES (:id, :name, :email, :address, :created_at)
                ON CONFLICT (id) DO UPDATE
                SET name = EXCLUDED.name,
                    email = EXCLUDED.email,
                    address = EXCLUDED.address
                SQL,
                [
                    'id' => $aggregateId,
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'created_at' => $snapshot->createdAt->format('Y-m-d H:i:s')
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isUserSnapshotState(array $state): bool
    {
        return array_key_exists('name', $state) && array_key_exists('email', $state);
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @template T
     * @param iterable<T> $items
     * @return \Iterator<T>
     */
    private function asIterator(iterable $items): \Iterator
    {
        if ($items instanceof \Iterator) {
            $items->rewind();

            return $items;
        }

        return (function () use ($items): \Generator {
            foreach ($items as $item) {
                yield $item;
            }
        })();
    }
}
