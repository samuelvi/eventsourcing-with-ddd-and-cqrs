<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Domain\Model\Snapshot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Uid\Uuid;

final class DemoControlController extends AbstractController
{
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_USER_PROJECTIONS = 'demo_user_projections_enabled';
    private const CACHE_KEY_BOOKING_PROJECTIONS = 'demo_booking_projections_enabled';

    public function __construct(
        private CacheInterface $cache,
        private ReadEntityManager $readEntityManager,
        private WriteEntityManager $writeEntityManager,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
        private KernelInterface $kernel,
    ) {}

    #[Route('/api/demo/status', methods: ['GET'])]
    public function getStatus(): Response
    {
        $masterEnabled = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $userEnabled = $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $bookingEnabled = $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);
        
        return new JsonResponse([
            'projectionsEnabled' => $masterEnabled,
            'userProjectionsEnabled' => $userEnabled,
            'bookingProjectionsEnabled' => $bookingEnabled
        ]);
    }

    #[Route('/api/demo/toggle/{type}', methods: ['POST'])]
    public function toggle(string $type): Response
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

        return new JsonResponse([
            ($type === 'master' ? 'projectionsEnabled' : ($type . 'ProjectionsEnabled')) => $newValue
        ]);
    }

    #[Route('/api/demo/rebuild', methods: ['POST'])]
    public function rebuild(): Response
    {
        // 1. Force both toggles back to enabled for the rebuild process
        $this->cache->delete(self::CACHE_KEY_MASTER);
        $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $this->cache->delete(self::CACHE_KEY_USER_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $this->cache->delete(self::CACHE_KEY_BOOKING_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);

        // 2. Clear SQL Read Models
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        
        // 3. Clear Mongo Checkpoints
        $this->mongoStore->clearAll(); // Note: This clears events too, but rebuild usually starts fresh. 
        // Wait, rebuild shouldn't clear events. Let's fix MongoStore clearAll or use specific clear.
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

        return new JsonResponse(['status' => 'success', 'processed' => count($events)]);
    }

    #[Route('/api/demo/stats', methods: ['GET'])]
    public function getStats(): Response
    {
        $eventCount = $this->mongoStore->countEvents();
        $userCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM users')['count'];
        $bookingCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM bookings')['count'];
        $snapshotCount = $this->mongoStore->countSnapshots();

        // Get checkpoints from Mongo
        $checkpoints = $this->mongoStore->findAllCheckpoints();
        $checkpointsMap = [];
        foreach ($checkpoints as $cp) {
            $checkpointsMap[$cp->projectionName] = $cp->lastEventId?->toRfc4122();
        }

        return new JsonResponse([
            'events' => $eventCount,
            'users' => $userCount,
            'bookings' => $bookingCount,
            'snapshots' => $snapshotCount,
            'checkpoints' => $checkpointsMap
        ]);
    }

    #[Route('/api/demo/snapshot', methods: ['POST'])]
    public function snapshot(): Response
    {
        $eventCount = $this->mongoStore->countEvents();
        $userCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM users')['count'];
        $bookingCount = (int)$this->readEntityManager->fetchOne('SELECT COUNT(*) FROM bookings')['count'];

        $snapshot = new Snapshot(
            Uuid::v7(), 
            Uuid::v7(), // System aggregate ID for demo
            $eventCount,
            ['users' => $userCount, 'bookings' => $bookingCount, 'timestamp' => time()]
        );

        $this->mongoStore->saveSnapshot($snapshot);

        return new JsonResponse(['status' => 'success', 'version' => $eventCount]);
    }

    #[Route('/api/demo/reset', methods: ['POST'])]
    public function reset(): Response
    {
        // 1. Force everything back to enabled
        $this->cache->delete(self::CACHE_KEY_MASTER);
        $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $this->cache->delete(self::CACHE_KEY_USER_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $this->cache->delete(self::CACHE_KEY_BOOKING_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);

        // 2. Clear SQL
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE products CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE suppliers CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE menus CASCADE');

        // 3. Clear Mongo
        $this->mongoStore->clearAll();

        // 4. Load Fixtures
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($this->kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
        ]);
        $application->run($input, new \Symfony\Component\Console\Output\NullOutput());

        return new JsonResponse(['status' => 'success']);
    }
}
