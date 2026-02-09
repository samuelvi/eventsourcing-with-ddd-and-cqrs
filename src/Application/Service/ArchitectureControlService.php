<?php

declare(strict_types=1);

namespace App\Application\Service;

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

    public function __construct(
        private CacheInterface $cache,
        private ReadEntityManager $readEntityManager,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
        private KernelInterface $kernel,
        private \App\Domain\Repository\UserReadRepositoryInterface $userRepository,
        private \App\Domain\Repository\BookingReadRepositoryInterface $bookingRepository,
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
     * @return array<string, int|array<string, string|null>>
     */
    public function getStats(): array
    {
        $checkpoints = $this->mongoStore->findAllCheckpoints();
        $checkpointsMap = [];
        foreach ($checkpoints as $cp) {
            $checkpointsMap[$cp->projectionName] = $cp->lastEventId?->toRfc4122();
        }

        return [
            'events' => $this->mongoStore->countEvents(),
            'users' => $this->userRepository->countAll(),
            'bookings' => $this->bookingRepository->countAll(),
            'snapshots' => $this->mongoStore->countSnapshots(),
            'checkpoints' => $checkpointsMap
        ];
    }

    public function takeSnapshot(): int
    {
        $eventCount = $this->mongoStore->countEvents();
        $snapshot = \App\Domain\Model\Snapshot::take(
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
        // 1. Force both toggles back to enabled for the rebuild process
        $this->enableAll();

        // 2. Clear SQL Read Models
        $this->readEntityManager->execute('TRUNCATE users, bookings RESTART IDENTITY CASCADE');
        
        // 3. Clear Mongo Checkpoints (KEEP EVENTS)
        $this->mongoStore->clearCheckpoints();

        // 4. Fetch all events from Mongo
        $events = $this->mongoStore->findEvents();

        foreach ($events as $storedEvent) {
            $event = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );
            $this->eventBus->dispatch($event);
        }

        return count($events);
    }

    public function reset(): void
    {
        // 1. Force everything back to enabled
        $this->enableAll();

        // 2. Clear SQL Tables (Aggressive + Identity Reset)
        $this->readEntityManager->execute('TRUNCATE users, bookings, products, menus, suppliers RESTART IDENTITY CASCADE');

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
    }
}
